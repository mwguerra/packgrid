# Repository autosync, per-tag downloads, and GitHub API caching — Design

- **Date:** 2026-06-17
- **Status:** Approved (pending spec review)
- **Author:** Marcelo W. Guerra (with Claude Code)

## 1. Summary

Three related changes to the `Repository` domain:

1. **Tags & downloads on the view page** (`admin/repositories/{id}`): show every available
   version/tag for a repository together with how many times each was downloaded.
2. **Per-repository `autosync` flag**: when enabled, a Composer request triggers a sync of
   the repository *before* the response is served, so the served metadata reflects GitHub
   as closely as the freshness window allows.
3. **1-minute GitHub API cache + request coalescing**: cache the read-only GitHub calls and
   guard concurrent syncs so many near-simultaneous PackGrid requests for the same repository
   do not fan out into duplicate GitHub requests.

The registry stays on the **Composer v1 inline format** (`packages.json` with all versions
embedded, no `metadata-url`). No migration to Composer v2 lazy metadata.

## 2. Background — verified current behavior

- `packages.json` is **inline**: `PackageIndexBuilder::rebuild()` reads every enabled repo's
  `repositories/{id}.json` and embeds all versions directly into `packages.json`. There is no
  `metadata-url`, so a Composer client gets everything from a single `GET /packages.json`; it
  does **not** make per-package requests. (`app/Services/PackageIndexBuilder.php`)
- `PackageMetadataController::index()` serves `packages.json` straight from disk; it does **not**
  sync. `show()` serves the per-package file (written but unused by Composer in inline mode).
  (`app/Http/Controllers/PackageMetadataController.php`)
- The dist download already best-effort syncs **any** stale repo:
  `if ($repository->needsSync()) { try sync; catch swallow }`
  (`app/Http/Controllers/PackageProxyController.php:35-41`,
  `app/Http/Controllers/NpmProxyController.php:39-45`).
- `Repository::needsSync()` returns true when `last_sync_at` is null or older than **1 minute**
  (hard-coded `subMinute()`). (`app/Models/Repository.php:68-71`)
- `RepositorySyncService::sync()` builds metadata, writes `repositories/{id}.json`, **rebuilds the
  full index**, updates `package_count`/`last_sync_at`/`last_error`, and writes a `SyncLog`.
  (`app/Services/RepositorySyncService.php`)
- `GitHubClient` uses the `Http` facade with **no caching**. Read methods: `getRepository`,
  `listTags`, `listBranches`, `getBranch`, `getFileContent` (→ `getComposerJson`,
  `ComposerAdapter::getManifest`). Stream methods: `downloadZipball`, `downloadTarball`.
  (`app/Services/GitHubClient.php`)
- `DownloadLog::logDownload($repository, $ref, $format, $token)` increments
  `repositories.download_count` and stores a row keyed by `package_version = $ref`, where `$ref`
  is the **raw git ref** from the dist URL. (`app/Models/DownloadLog.php`,
  `PackageProxyController:45`, `NpmProxyController:49`)
- `ComposerAdapter::normalizeVersion($ref, $type)` returns the ref as-is for **tags** and
  `dev-{ref}` for **branches**; `buildDistUrl()` uses the **raw `$ref`**.
  (`app/Adapters/ComposerAdapter.php:102-121`)
- Versions for a repo are read via `PackageMetadataStore::readRepositoryMetadata($id)` →
  `{ "vendor/pkg": { "v1.0.0": {…}, "dev-main": {…} } }`. (`app/Services/PackageMetadataStore.php`)
- Existing rate limit: `packgrid-registry` limiter, default 600/min per token or IP.
  (`config/packgrid.php`, `AppServiceProvider`)

## 3. Goals / Non-goals

**Goals**
- Surface available tags/versions and per-tag download counts on the repository view page.
- An opt-in per-repository `autosync` flag that keeps registry responses fresh on request.
- Bound GitHub API load under bursts via a short cache + per-repo coalescing lock.
- `autosync` is the single switch governing **all** on-request syncing (metadata index +
  both download endpoints).

**Non-goals**
- No Composer v2 / lazy `metadata-url` rearchitecture.
- No package-name → Repository mapping (not needed; we sync by repo, not by package — see §5.5
  for why the NPM per-package endpoint still refreshes by format rather than by package).
- No change to the 4-hourly scheduler or the dashboard's 5h staleness threshold.

## 4. Decisions (confirmed)

| # | Decision |
|---|----------|
| Format | Keep Composer v1 inline `packages.json`. |
| Autosync trigger (Composer) | At `GET /packages.json` (sync stale autosync Composer repos, then serve). |
| Autosync trigger (NPM) | At the packument endpoints `GET /npm/{package}` (+ scoped variants): refresh stale autosync **NPM** repos by format, rebuild the NPM index, then serve. |
| Download alignment | Both dist (`/dist`) and npm tarball downloads gated by the `autosync` flag instead of the generic `needsSync()`. |
| Tags display | All synced versions (tags **and** `dev-*` branches), each with its download count (0 if none). |
| Sync failure during a request | Serve current (last-known) data; never block the request. |
| Coalescing | 60s GitHub read cache + freshness gate (skip if synced < 60s ago) + per-repo lock. All configurable. |

## 5. Detailed design

### 5.1 Data model

Migration `add_autosync_to_repositories_table`:

```php
$table->boolean('autosync')->default(false)->after('clone_enabled');
```

`Repository`:
- add `'autosync'` to `$fillable`
- add `'autosync' => 'boolean'` to `$casts`
- make `needsSync()` threshold config-driven (still 60s by default):

```php
public function needsSync(): bool
{
    $seconds = (int) config('packgrid.autosync.fresh_seconds', 60);

    return $this->last_sync_at === null
        || $this->last_sync_at->lt(now()->subSeconds($seconds));
}
```

`needsSync()` is used only by the on-request sync paths; the dashboard uses the separate
`DASHBOARD_STALE_HOURS`/`scopeStale`/`syncStatus`, which are unchanged.

### 5.2 Config (`config/packgrid.php`)

```php
'github_cache' => [
    'ttl' => (int) env('PACKGRID_GITHUB_CACHE_TTL', 60), // seconds; 0 disables caching
],
'autosync' => [
    'fresh_seconds' => (int) env('PACKGRID_AUTOSYNC_FRESH_SECONDS', 60), // freshness gate
    'lock_seconds'  => (int) env('PACKGRID_AUTOSYNC_LOCK_SECONDS', 30),  // per-repo lock TTL
],
```

**Why 1 minute is OK:** it matches the existing `needsSync()` window, gives near-real-time
freshness (a new tag appears within ≤ ~1 min worst case), and the 4-hourly scheduler remains the
safety net. All three values are configurable, so the window can be tuned without code changes.

### 5.3 GitHub API cache (`GitHubClient`)

Wrap the **read-only** methods in `Cache::remember`:
`getRepository`, `listTags`, `listBranches`, `getBranch`, `getFileContent`.

- **Never** cache `downloadZipball` / `downloadTarball` (large binary streams).
- **Only** cache successful responses. Keep `->throw()` so non-2xx still raise — the exception
  path must not write to cache (so 404/429/5xx are never cached and retry next call).
- TTL = `config('packgrid.github_cache.ttl')`. If `0`, bypass the cache entirely (behavior
  identical to today) — useful as a kill switch and to keep existing tests stable.
- Cache key includes method, credential identity, repo and params, e.g.:
  - `gh:repo:{credKey}:{fullName}`
  - `gh:tags:{credKey}:{fullName}`
  - `gh:branches:{credKey}:{fullName}`
  - `gh:branch:{credKey}:{fullName}:{branch}`
  - `gh:contents:{credKey}:{fullName}:{ref}:{sha1(path)}`

  where `credKey` is the credential id (or `anon`). Including the credential id keeps private
  data from leaking across credentials while preserving hit rate (a repo's credential is stable).

Implementation note: cache the **decoded array** returned by each method, not the `Response`
object. Factor the HTTP call into a private closure passed to `Cache::remember`, e.g.:

```php
public function listTags(string $fullName, ?Credential $credential = null): array
{
    return $this->remember('gh:tags:'.$this->credKey($credential).':'.$fullName, fn () =>
        $this->request($credential)->get(self::API_BASE.'/repos/'.$fullName.'/tags')->throw()->json()
    );
}

private function remember(string $key, \Closure $fetch): array
{
    $ttl = (int) config('packgrid.github_cache.ttl', 60);

    return $ttl > 0 ? Cache::remember($key, $ttl, $fetch) : $fetch();
}
```

### 5.4 Autosync coordinator (new `App\Services\RepositoryAutosyncService`)

Single home for "sync this repo if the flag says so and it's stale, without stampeding".
Used by both the metadata index and the download controllers.

```php
class RepositoryAutosyncService
{
    public function __construct(
        private RepositorySyncService $sync,
        private PackageIndexBuilder $composerIndex,
        private NpmIndexBuilder $npmIndex,
    ) {}

    /** Sync one repo on request when autosync is on and it's stale. Best-effort. */
    public function maybeSync(Repository $repo, bool $rebuildIndex = true): void
    {
        if (! $repo->autosync || ! $repo->needsSync()) {
            return;
        }

        $lock = Cache::lock('packgrid:repo-sync:'.$repo->id, (int) config('packgrid.autosync.lock_seconds', 30));

        if (! $lock->get()) {
            return; // another request is already syncing this repo → serve current
        }

        try {
            $repo->refresh();
            if (! $repo->needsSync()) {
                return; // someone synced it while we waited for the lock
            }
            $this->sync->sync($repo, rebuildIndex: $rebuildIndex);
        } catch (\Throwable) {
            // never block the request; error is already persisted on the repo / SyncLog
        } finally {
            $lock->release();
        }
    }

    /** Refresh all stale autosync repos of a format before serving the index. */
    public function refreshIndex(PackageFormat $format): void
    {
        $repos = Repository::query()
            ->where('enabled', true)
            ->where('autosync', true)
            ->where('format', $format)
            ->get()
            ->filter->needsSync();

        if ($repos->isEmpty()) {
            return;
        }

        foreach ($repos as $repo) {
            $this->maybeSync($repo, rebuildIndex: false); // defer index rebuild
        }

        // Rebuild the matching index once after the batch.
        match ($format) {
            PackageFormat::Npm => $this->npmIndex->rebuild(),
            default            => $this->composerIndex->rebuild(),
        };
    }
}
```

`RepositorySyncService::sync()` gains an optional `bool $rebuildIndex = true` parameter; when
`false` it skips the `->rebuild()` call (existing callers are unaffected; the coordinator
rebuilds once after the batch). Per-repo `package_count` is still updated regardless.

### 5.5 Hook points

**Metadata index** — `PackageMetadataController::index()`:

```php
public function index(PackageMetadataStore $store, RepositoryAutosyncService $autosync): JsonResponse
{
    $autosync->refreshIndex(PackageFormat::Composer);

    return response()->json($store->readPackagesIndex());
}
```

When no autosync repo is stale this is a single DB query + the existing disk read — negligible
overhead on the hot path. When some are stale, the first request within the window pays the sync
cost; concurrent requests skip via the per-repo lock, and subsequent requests skip via the
freshness gate.

**NPM packument** — `NpmMetadataController::show()`, `showScoped()`, `showScopedEncoded()`. The
NPM metadata flow is **per-package** (`GET /npm/{package}`) and, like Composer, has **no
package-name → Repository mapping**. Rather than introduce one, each packument request refreshes
**all stale autosync NPM repos** by format (same model as the Composer index), then serves:

```php
public function show(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $package): JsonResponse
{
    $autosync->refreshIndex(PackageFormat::Npm); // rebuilds the per-package packuments
    $metadata = $store->readPackage($package);
    // … unchanged: 404 when null, else json …
}
```

`NpmIndexBuilder::rebuild()` re-merges all enabled NPM repos and rewrites every per-package
packument, so after the refresh the requested package's packument is fresh. The three controller
methods share the one `refreshIndex(PackageFormat::Npm)` call (extract a tiny private helper to
avoid repetition). Note: `npm install` fires packument requests per package, often concurrently —
the **first** request in the window pays the sync cost; the rest hit the freshness gate, and
contended repos serve current data via the lock (best-effort freshness, per §6). The freshness
window can be raised via config if the first-request latency matters for large NPM fleets.

**Downloads** — replace the inline `if ($repository->needsSync()) { … }` block in **both**
`PackageProxyController::download()` and `NpmProxyController::download()` with:

```php
app(RepositoryAutosyncService::class)->maybeSync($repository);
```

This changes behavior: a repo with `autosync = false` no longer auto-syncs at download time
(it relies on the scheduler / manual sync), matching the unified "autosync governs on-request
syncing" model the user requested.

### 5.6 Tags & downloads on the view page

A new collapsible Section **"Tags & Downloads"** on `ViewRepository`'s infolist.

Data assembly (helper, e.g. on the page class or a small read-model `RepositoryTagReport`):

1. Read versions: `PackageMetadataStore::readRepositoryMetadata($repo->id)` →
   `{ package => { version => data } }`. Flatten to rows `(package, version)`. Empty/`null` →
   render an empty-state placeholder ("not synced yet / no versions").
2. Read downloads: aggregate `DownloadLog` for the repo:
   `selectRaw('package_version, count(*) as downloads')->groupBy('package_version')` →
   map `package_version => count`.
3. **Reconcile** version key ↔ logged `package_version`:
   - tag versions (`v1.0.0`): the key equals the logged ref → direct match.
   - branch versions (`dev-main`): the logged ref is the raw branch (`main`) → match the key,
     and if absent, also try the `dev-` stripped form.
   - downloads of refs no longer present in metadata are not shown as rows (kept simple); the
     repo's aggregate `download_count` already reflects the all-time total elsewhere.
4. Sort: stable/tag versions first, descending (use `Composer\Semver` ordering; fall back to
   `version_compare`/natural sort on parse failure), `dev-*` grouped after. Cap the rendered list
   (e.g. 100 rows) with a note if truncated.

Rendering: a `RepeatableEntry` (or a `ViewEntry` with a small Blade table) fed by the assembled
array, each row showing the version (badge), download count (badge), and the package name when a
repo exposes more than one package. Mirrors the existing infolist style in `RepositoryResource`.

## 6. Concurrency model

Three layers, all ~60s and configurable:

1. **GitHub read cache (60s):** even if two repos/syncs race, identical GitHub reads return cached
   data; the binary downloads are never cached.
2. **Freshness gate (60s):** `needsSync()` short-circuits a sync when `last_sync_at` is < 60s old.
3. **Per-repo lock:** `Cache::lock('packgrid:repo-sync:{id}')`, non-blocking `get()`. A concurrent
   request that loses the lock serves current data instead of duplicating the sync. The lock
   holder re-checks `needsSync()` after acquiring (double-checked) to avoid a redundant sync.

Together these bound GitHub calls to roughly one sync per repo per window regardless of request
volume, satisfying the "many near-simultaneous requests for the same repo" concern.

## 7. Edge cases

- **Sync failure under autosync:** swallowed; current metadata served (200). Error is recorded on
  `repositories.last_error` + `SyncLog` by `RepositorySyncService` as today.
- **GitHub 429 / rate limit:** not cached; the freshness gate + lock still prevent a flood, and the
  request degrades gracefully to current data.
- **First request after a batch goes stale:** bears the sync cost for the stale autosync repos
  (serial). Bounded by the (typically small) number of opted-in repos; concurrent requests skip via
  the lock. If this proves heavy, the freshness window can be raised via config.
- **Cache store:** `Cache::lock` requires a lock-capable store (redis/database/memcached/array).
  Confirm the configured cache store supports locks in the target environment; the `array`/redis
  stores used in tests do.
- **`github_cache.ttl = 0`:** disables caching (parity with today) — keeps existing `Http::fake`
  tests that assert request counts valid unless they opt into the cache.
- **Branch vs tag download reconciliation:** handled in §5.6 step 3.

## 8. Coverage matrix

The `autosync` flag governs on-request syncing across **all** registry surfaces:

| Surface | Hook | Refresh scope |
|---------|------|---------------|
| Composer metadata index (`/packages.json`) | `PackageMetadataController::index()` | all stale autosync Composer repos |
| NPM packument (`/npm/{package}` + scoped) | `NpmMetadataController::show/showScoped/showScopedEncoded()` | all stale autosync NPM repos |
| Composer dist download (`/dist/...`) | `PackageProxyController::download()` | the requested repo only |
| NPM tarball download (`/npm/-/...`) | `NpmProxyController::download()` | the requested repo only |

The download endpoints resolve a single `Repository` (by `repo_full_name`) and sync just that one;
the metadata endpoints have no package→repo mapping and so refresh all stale autosync repos of the
matching format. The Composer per-package endpoint (`/p/{vendor}/{package}.json`) is unused by
Composer v1 clients in inline mode and is left untouched.

## 9. Testing strategy

**Unit — `GitHubClient` cache** (`Http::fake`, assert request counts):
- second `listTags`/`listBranches`/`getFileContent` within TTL makes **no** HTTP call;
- after TTL expiry a new call hits HTTP;
- a 404/429/5xx is **not** cached (next call retries);
- `ttl = 0` bypasses the cache (always hits HTTP).

**Unit — `Repository::needsSync()`** honors `autosync.fresh_seconds`.

**Feature — autosync at `packages.json`:**
- autosync repo stale → sync runs, served index includes the new version (mock builder/GitHub);
- autosync **off** → no sync;
- synced < `fresh_seconds` ago → no sync;
- sync throws → response is 200 with current data;
- lock held by another worker → repo is skipped (served current).

**Feature — autosync at the NPM packument:**
- autosync NPM repo stale → packument request refreshes it and the served packument includes the
  new version (mock builder/GitHub);
- autosync **off** → no sync; non-NPM repos are not touched (format filter);
- a second packument request within the window does not re-sync (freshness gate).

**Feature — downloads gated by flag:**
- `autosync = true` + stale → syncs before streaming (both Composer dist and NPM tarball);
- `autosync = false` → does **not** sync; download still streams.

**Feature/Filament — Tags & Downloads section:**
- renders each available version with its download count (incl. a version with 0 downloads);
- tag version matches its logged download count;
- branch version (`dev-main`) reconciles with a `main` download log;
- empty state when the repo has never synced.

All suites run in parallel where safe.

## 10. Affected files

- **New:** `database/migrations/..._add_autosync_to_repositories_table.php`,
  `app/Services/RepositoryAutosyncService.php`, tests.
- **Changed:** `app/Models/Repository.php` (fillable/cast, `needsSync()`),
  `app/Services/GitHubClient.php` (caching), `app/Services/RepositorySyncService.php`
  (`$rebuildIndex` param), `app/Http/Controllers/PackageMetadataController.php` (Composer index hook),
  `app/Http/Controllers/NpmMetadataController.php` (NPM packument hook),
  `app/Http/Controllers/PackageProxyController.php` + `NpmProxyController.php` (use coordinator),
  `app/Filament/Resources/RepositoryResource.php` (autosync `Toggle`),
  `app/Filament/Resources/RepositoryResource/Pages/ViewRepository.php` (Tags & Downloads section),
  `config/packgrid.php` (new config blocks).

## 11. Open questions

None blocking.
