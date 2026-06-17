# Repository Autosync, Per-Tag Downloads & GitHub API Caching — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-repository `autosync` flag that keeps Composer/NPM registry responses fresh on request, a 1-minute GitHub API read cache with per-repo coalescing, and a "Tags & Downloads" view on the repository admin page.

**Architecture:** Keep the Composer v1 inline `packages.json` format. A new `RepositoryAutosyncService` centralises "sync this repo if `autosync` is on and it's stale, without stampeding" (freshness gate + `Cache::lock` + best-effort). It is invoked before serving the Composer index, the NPM packument, and both download endpoints. `GitHubClient`'s read methods gain a short `Cache::remember`. A `RepositoryTagReport` read-model assembles available versions (from the on-disk metadata) joined with per-version download counts (from `download_logs`).

**Tech Stack:** Laravel 11, Filament 4/5, Pest 4, SQLite (tests), Composer v1 inline registry, `Illuminate\Http\Client`, `Illuminate\Support\Facades\Cache`.

## Global Constraints

- Keep Composer **v1 inline** `packages.json` — no `metadata-url`, no package-name → Repository mapping.
- Never block a registry request on a sync failure — swallow and serve current data.
- Cache only **successful** GitHub **read** calls; never cache zipball/tarball streams or error responses.
- Conventional commits; **never** add Claude as a co-author.
- All new tests live in `tests/Feature/` (Pest binds `TestCase` + `RefreshDatabase` to `Feature` only — facades/DB are unavailable in `tests/Unit`).
- Filament imports follow the project: form fields `Filament\Forms\Components\*`, infolist entries `Filament\Infolists\Components\*`, layout `Filament\Schemas\Components\*`.
- User-facing strings go through `__()` and must be added to all four locale files: `lang/en.json`, `lang/pt_BR.json`, `lang/es.json`, `lang/fr.json`.
- Defaults: `autosync` column default `false`; freshness window 60s; GitHub cache TTL 60s; lock TTL 30s — all configurable.

---

### Task 1: `autosync` column on repositories

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_add_autosync_to_repositories_table.php` (via artisan)
- Modify: `app/Models/Repository.php:20-46` (fillable + casts)
- Test: `tests/Feature/RepositoryAutosyncColumnTest.php`

**Interfaces:**
- Produces: `repositories.autosync` boolean column (default `false`); `Repository->autosync` cast to `bool`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RepositoryAutosyncColumnTest.php`:

```php
<?php

use App\Models\Repository;

test('autosync defaults to false and casts to boolean', function () {
    $repo = Repository::factory()->create();

    expect($repo->autosync)->toBeFalse();

    $repo->update(['autosync' => 1]);
    $repo->refresh();

    expect($repo->autosync)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositoryAutosyncColumnTest`
Expected: FAIL — `autosync` column does not exist / attribute is null.

- [ ] **Step 3: Create the migration via artisan**

Run: `php artisan make:migration add_autosync_to_repositories_table --table=repositories`

Edit the generated file so `up()`/`down()` are:

```php
public function up(): void
{
    Schema::table('repositories', function (Blueprint $table) {
        $table->boolean('autosync')->default(false)->after('clone_enabled');
    });
}

public function down(): void
{
    Schema::table('repositories', function (Blueprint $table) {
        $table->dropColumn('autosync');
    });
}
```

- [ ] **Step 4: Add `autosync` to the model fillable + casts**

In `app/Models/Repository.php`, add `'autosync',` to `$fillable` (after `'clone_count',`) and `'autosync' => 'boolean',` to `$casts` (after `'clone_count' => 'integer',`):

```php
protected $fillable = [
    // ... existing ...
    'clone_enabled',
    'clone_count',
    'autosync',
    'last_sync_at',
    'last_error',
    'ref_filter',
];

protected $casts = [
    // ... existing ...
    'clone_enabled' => 'boolean',
    'clone_count' => 'integer',
    'autosync' => 'boolean',
    'last_sync_at' => 'datetime',
];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=RepositoryAutosyncColumnTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/Repository.php tests/Feature/RepositoryAutosyncColumnTest.php
git commit -m "feat(repositories): add per-repository autosync flag column"
```

---

### Task 2: Make `needsSync()` config-driven + add autosync config

**Files:**
- Modify: `app/Models/Repository.php:68-71` (`needsSync()`)
- Modify: `config/packgrid.php` (new `autosync` block)
- Test: `tests/Feature/RepositoryNeedsSyncTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Repository::needsSync(): bool` honoring `config('packgrid.autosync.fresh_seconds', 60)`; config keys `packgrid.autosync.fresh_seconds` (60) and `packgrid.autosync.lock_seconds` (30).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RepositoryNeedsSyncTest.php`:

```php
<?php

use App\Models\Repository;

test('needsSync uses the configured freshness window', function () {
    config(['packgrid.autosync.fresh_seconds' => 120]);

    $repo = Repository::factory()->make(['last_sync_at' => now()->subSeconds(90)]);
    expect($repo->needsSync())->toBeFalse(); // 90s < 120s window

    $repo->last_sync_at = now()->subSeconds(150);
    expect($repo->needsSync())->toBeTrue(); // 150s > 120s window
});

test('needsSync defaults to a 60 second window', function () {
    $repo = Repository::factory()->make(['last_sync_at' => now()->subSeconds(90)]);
    expect($repo->needsSync())->toBeTrue();

    $repo->last_sync_at = null;
    expect($repo->needsSync())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositoryNeedsSyncTest`
Expected: FAIL — first test fails because `needsSync()` is hard-coded to `subMinute()` (90s > 60s ⇒ returns true, expected false).

- [ ] **Step 3: Update `needsSync()`**

Replace `app/Models/Repository.php:68-71`:

```php
public function needsSync(): bool
{
    $seconds = (int) config('packgrid.autosync.fresh_seconds', 60);

    return $this->last_sync_at === null
        || $this->last_sync_at->lt(now()->subSeconds($seconds));
}
```

- [ ] **Step 4: Add the config block**

In `config/packgrid.php`, add before the closing `];` (after the `retention` block):

```php
    /*
    |--------------------------------------------------------------------------
    | Autosync
    |--------------------------------------------------------------------------
    |
    | When a repository has autosync enabled, registry requests (Composer index,
    | NPM packument, downloads) refresh it before responding. fresh_seconds is
    | the freshness window: a repo synced within this window is not re-synced.
    | lock_seconds is the per-repo coalescing lock TTL.
    |
    */
    'autosync' => [
        'fresh_seconds' => (int) env('PACKGRID_AUTOSYNC_FRESH_SECONDS', 60),
        'lock_seconds' => (int) env('PACKGRID_AUTOSYNC_LOCK_SECONDS', 30),
    ],
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=RepositoryNeedsSyncTest`
Expected: PASS.

- [ ] **Step 6: Run the existing download tests to confirm no regression yet**

Run: `php artisan test --filter=DownloadTrackingTest`
Expected: PASS (behavior unchanged at this point — 60s default matches the old `subMinute()`).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Repository.php config/packgrid.php tests/Feature/RepositoryNeedsSyncTest.php
git commit -m "feat(autosync): make sync freshness window configurable"
```

---

### Task 3: GitHub API read cache

**Files:**
- Modify: `app/Services/GitHubClient.php`
- Modify: `config/packgrid.php` (new `github_cache` block)
- Test: `tests/Feature/GitHubClientCacheTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `getRepository`, `listTags`, `listBranches`, `getBranch`, `getFileContent` cached for `config('packgrid.github_cache.ttl', 60)` seconds; `ttl = 0` disables. Stream methods unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GitHubClientCacheTest.php`:

```php
<?php

use App\Services\GitHubClient;
use Illuminate\Support\Facades\Http;

test('listTags is cached within the ttl', function () {
    config(['packgrid.github_cache.ttl' => 60]);
    Http::fake([
        'https://api.github.com/repos/acme/tools/tags' => Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200),
    ]);

    $client = app(GitHubClient::class);
    $client->listTags('acme/tools');
    $client->listTags('acme/tools');

    Http::assertSentCount(1);
});

test('the cache expires after the ttl', function () {
    config(['packgrid.github_cache.ttl' => 60]);
    Http::fake([
        'https://api.github.com/repos/acme/tools/tags' => Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200),
    ]);

    $client = app(GitHubClient::class);
    $client->listTags('acme/tools');
    $this->travel(61)->seconds();
    $client->listTags('acme/tools');

    Http::assertSentCount(2);
});

test('error responses are not cached', function () {
    config(['packgrid.github_cache.ttl' => 60]);
    Http::fakeSequence('https://api.github.com/repos/acme/tools/tags')
        ->push(['message' => 'rate limited'], 429)
        ->push([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200);

    $client = app(GitHubClient::class);

    expect(fn () => $client->listTags('acme/tools'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);

    expect($client->listTags('acme/tools'))->toHaveCount(1);
    Http::assertSentCount(2);
});

test('ttl of zero disables caching', function () {
    config(['packgrid.github_cache.ttl' => 0]);
    Http::fake([
        'https://api.github.com/repos/acme/tools/tags' => Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200),
    ]);

    $client = app(GitHubClient::class);
    $client->listTags('acme/tools');
    $client->listTags('acme/tools');

    Http::assertSentCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GitHubClientCacheTest`
Expected: FAIL — first test sends 2 requests (no caching yet).

- [ ] **Step 3: Add the config block**

In `config/packgrid.php`, add (next to the `autosync` block from Task 2):

```php
    /*
    |--------------------------------------------------------------------------
    | GitHub API Cache
    |--------------------------------------------------------------------------
    |
    | Read-only GitHub API calls (tags, branches, file contents, repo metadata)
    | are cached for this many seconds so bursts of registry requests for the
    | same repository do not fan out into duplicate GitHub requests. Set to 0
    | to disable. Binary downloads (zipball/tarball) are never cached.
    |
    */
    'github_cache' => [
        'ttl' => (int) env('PACKGRID_GITHUB_CACHE_TTL', 60),
    ],
```

- [ ] **Step 4: Wrap the read methods in a cache helper**

In `app/Services/GitHubClient.php`, add imports at the top:

```php
use Closure;
use Illuminate\Support\Facades\Cache;
```

Add two private helpers (place them next to the existing `request()` method):

```php
private function remember(string $key, Closure $fetch): array
{
    $ttl = (int) config('packgrid.github_cache.ttl', 60);

    return $ttl > 0 ? Cache::remember($key, $ttl, $fetch) : $fetch();
}

private function credKey(?Credential $credential): string
{
    return (string) ($credential?->getKey() ?? 'anon');
}
```

Now wrap each read method. Replace the bodies as follows (leave `testCredential`, `downloadZipball`, `downloadTarball`, `request`, and `getComposerJson` untouched — `getComposerJson` already routes through `getFileContent`):

```php
public function getRepository(string $fullName, ?Credential $credential = null): array
{
    return $this->remember('gh:repo:'.$this->credKey($credential).':'.$fullName, fn (): array => $this->request($credential)
        ->get(self::API_BASE.'/repos/'.$fullName)
        ->throw()
        ->json());
}

public function listTags(string $fullName, ?Credential $credential = null): array
{
    return $this->remember('gh:tags:'.$this->credKey($credential).':'.$fullName, fn (): array => $this->request($credential)
        ->get(self::API_BASE.'/repos/'.$fullName.'/tags')
        ->throw()
        ->json());
}

public function listBranches(string $fullName, ?Credential $credential = null): array
{
    return $this->remember('gh:branches:'.$this->credKey($credential).':'.$fullName, fn (): array => $this->request($credential)
        ->get(self::API_BASE.'/repos/'.$fullName.'/branches', ['per_page' => 100])
        ->throw()
        ->json());
}

public function getBranch(string $fullName, string $branch, ?Credential $credential = null): array
{
    return $this->remember('gh:branch:'.$this->credKey($credential).':'.$fullName.':'.$branch, fn (): array => $this->request($credential)
        ->get(self::API_BASE.'/repos/'.$fullName.'/branches/'.$branch)
        ->throw()
        ->json());
}

public function getFileContent(string $fullName, string $path, string $ref, ?Credential $credential = null): array
{
    $key = 'gh:contents:'.$this->credKey($credential).':'.$fullName.':'.$ref.':'.sha1($path);

    return $this->remember($key, fn (): array => $this->request($credential)
        ->get(self::API_BASE.'/repos/'.$fullName.'/contents/'.$path, ['ref' => $ref])
        ->throw()
        ->json());
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=GitHubClientCacheTest`
Expected: PASS.

- [ ] **Step 6: Run the broader sync/registry suites to confirm no regression**

Run: `php artisan test --filter="PackgridTest|NpmTest"`
Expected: PASS (each test uses a fresh `array` cache store, so caching does not leak between tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/GitHubClient.php config/packgrid.php tests/Feature/GitHubClientCacheTest.php
git commit -m "feat(github): cache read-only GitHub API calls with a configurable ttl"
```

---

### Task 4: `RepositorySyncService::sync()` optional index rebuild

**Files:**
- Modify: `app/Services/RepositorySyncService.php:24-53`
- Test: `tests/Feature/RepositorySyncRebuildTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `RepositorySyncService::sync(Repository $repository, bool $rebuildIndex = true): SyncLog` — when `false`, the index rebuild (`PackageIndexBuilder`/`NpmIndexBuilder`) is skipped; per-repo metadata + `package_count` are still written.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RepositorySyncRebuildTest.php`:

```php
<?php

use App\Models\Repository;
use App\Services\PackageIndexBuilder;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function fakeComposerGitHub(string $fullName): void
{
    Http::fake(function ($request) use ($fullName) {
        $url = $request->url();
        if (str_contains($url, "/repos/{$fullName}/tags")) {
            return Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 'sha1']]], 200);
        }
        if (str_contains($url, "/repos/{$fullName}/branches")) {
            return Http::response([['name' => 'main', 'commit' => ['sha' => 'sha2']]], 200);
        }
        if (str_contains($url, "/repos/{$fullName}/contents/composer.json")) {
            return Http::response(['content' => base64_encode(json_encode(['name' => $fullName, 'type' => 'library']))], 200);
        }

        return Http::response([], 404);
    });
}

test('sync rebuilds the index by default', function () {
    Storage::fake('local');
    fakeComposerGitHub('acme/tools');
    $repo = Repository::factory()->create(['repo_full_name' => 'acme/tools', 'url' => 'https://github.com/acme/tools']);

    app(RepositorySyncService::class)->sync($repo);

    expect(Storage::disk('local')->exists('packgrid/packages.json'))->toBeTrue();
});

test('sync can skip the index rebuild', function () {
    Storage::fake('local');
    fakeComposerGitHub('acme/tools');
    $repo = Repository::factory()->create(['repo_full_name' => 'acme/tools', 'url' => 'https://github.com/acme/tools']);

    $indexBuilder = Mockery::mock(PackageIndexBuilder::class);
    $indexBuilder->shouldNotReceive('rebuild');
    app()->instance(PackageIndexBuilder::class, $indexBuilder);

    $log = app(RepositorySyncService::class)->sync($repo, rebuildIndex: false);

    expect($log->status->value)->toBe('success');
    $repo->refresh();
    expect($repo->package_count)->toBe(1); // per-repo metadata still computed
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositorySyncRebuildTest`
Expected: FAIL — `sync()` has no `$rebuildIndex` parameter, so it always calls `rebuild()` and the `shouldNotReceive('rebuild')` expectation fails.

- [ ] **Step 3: Add the parameter and gate the rebuild**

In `app/Services/RepositorySyncService.php`, change the signature and the two rebuild calls:

```php
public function sync(Repository $repository, bool $rebuildIndex = true): SyncLog
{
    // ... unchanged setup ...

    if ($format === PackageFormat::Npm) {
        $this->npmStore->writeRepositoryMetadata($repository->id, $packages);
        if ($rebuildIndex) {
            $this->npmIndexBuilder->rebuild();
        }
    } else {
        $this->composerStore->writeRepositoryMetadata($repository->id, $packages);
        if ($rebuildIndex) {
            $this->composerIndexBuilder->rebuild();
        }
    }

    // ... unchanged package_count / last_sync_at / SyncLog handling ...
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RepositorySyncRebuildTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/RepositorySyncService.php tests/Feature/RepositorySyncRebuildTest.php
git commit -m "feat(sync): allow deferring the index rebuild during sync"
```

---

### Task 5: `RepositoryAutosyncService::maybeSync()`

**Files:**
- Create: `app/Services/RepositoryAutosyncService.php`
- Test: `tests/Feature/RepositoryAutosyncServiceTest.php`

**Interfaces:**
- Consumes: `RepositorySyncService::sync(Repository, bool $rebuildIndex)` (Task 4); `Repository::needsSync()` (Task 2); `Repository->autosync` (Task 1).
- Produces: `RepositoryAutosyncService::maybeSync(Repository $repo, bool $rebuildIndex = true): void` — syncs only when `autosync` is on and the repo is stale; uses a per-repo `Cache::lock`; swallows sync errors.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RepositoryAutosyncServiceTest.php`:

```php
<?php

use App\Models\Repository;
use App\Models\SyncLog;
use App\Services\RepositoryAutosyncService;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Cache;

describe('maybeSync', function () {
    it('syncs a stale repo when autosync is on', function () {
        $repo = Repository::factory()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

        $sync = Mockery::mock(RepositorySyncService::class);
        $sync->shouldReceive('sync')->once()
            ->withArgs(fn ($r, $rebuild = true) => $r->id === $repo->id)
            ->andReturn(new SyncLog);
        app()->instance(RepositorySyncService::class, $sync);

        app(RepositoryAutosyncService::class)->maybeSync($repo);
    });

    it('does nothing when autosync is off', function () {
        $repo = Repository::factory()->create(['autosync' => false, 'last_sync_at' => now()->subMinutes(5)]);

        $sync = Mockery::mock(RepositorySyncService::class);
        $sync->shouldNotReceive('sync');
        app()->instance(RepositorySyncService::class, $sync);

        app(RepositoryAutosyncService::class)->maybeSync($repo);
    });

    it('does nothing when the repo is fresh', function () {
        $repo = Repository::factory()->create(['autosync' => true, 'last_sync_at' => now()->subSeconds(30)]);

        $sync = Mockery::mock(RepositorySyncService::class);
        $sync->shouldNotReceive('sync');
        app()->instance(RepositorySyncService::class, $sync);

        app(RepositoryAutosyncService::class)->maybeSync($repo);
    });

    it('skips when another worker holds the lock', function () {
        $repo = Repository::factory()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

        Cache::lock('packgrid:repo-sync:'.$repo->id, 30)->get(); // held, not released

        $sync = Mockery::mock(RepositorySyncService::class);
        $sync->shouldNotReceive('sync');
        app()->instance(RepositorySyncService::class, $sync);

        app(RepositoryAutosyncService::class)->maybeSync($repo);
    });

    it('does not throw when the sync fails', function () {
        $repo = Repository::factory()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

        $sync = Mockery::mock(RepositorySyncService::class);
        $sync->shouldReceive('sync')->once()->andThrow(new RuntimeException('boom'));
        app()->instance(RepositorySyncService::class, $sync);

        app(RepositoryAutosyncService::class)->maybeSync($repo); // must not throw
    })->throwsNoExceptions();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositoryAutosyncServiceTest`
Expected: FAIL — class `RepositoryAutosyncService` does not exist.

- [ ] **Step 3: Create the service**

Create `app/Services/RepositoryAutosyncService.php`:

```php
<?php

namespace App\Services;

use App\Enums\PackageFormat;
use App\Models\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RepositoryAutosyncService
{
    public function __construct(
        private readonly RepositorySyncService $sync,
        private readonly PackageIndexBuilder $composerIndex,
        private readonly NpmIndexBuilder $npmIndex,
    ) {}

    /**
     * Sync one repository on request when autosync is enabled and it is stale.
     * Best-effort: failures and lock contention never propagate.
     */
    public function maybeSync(Repository $repository, bool $rebuildIndex = true): void
    {
        if (! $repository->autosync || ! $repository->needsSync()) {
            return;
        }

        $lock = Cache::lock(
            'packgrid:repo-sync:'.$repository->id,
            (int) config('packgrid.autosync.lock_seconds', 30)
        );

        if (! $lock->get()) {
            return; // another request is already syncing this repo
        }

        try {
            $repository->refresh();

            if (! $repository->needsSync()) {
                return; // synced by another request while we waited for the lock
            }

            $this->sync->sync($repository, $rebuildIndex);
        } catch (Throwable) {
            // never block the request; the error is persisted on the repo / SyncLog
        } finally {
            $lock->release();
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RepositoryAutosyncServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/RepositoryAutosyncService.php tests/Feature/RepositoryAutosyncServiceTest.php
git commit -m "feat(autosync): add coordinator that syncs a stale repo on request"
```

---

### Task 6: `RepositoryAutosyncService::refreshIndex()`

**Files:**
- Modify: `app/Services/RepositoryAutosyncService.php`
- Test: `tests/Feature/RepositoryAutosyncRefreshIndexTest.php`

**Interfaces:**
- Consumes: `maybeSync()` (Task 5); `PackageIndexBuilder::rebuild()`, `NpmIndexBuilder::rebuild()`.
- Produces: `RepositoryAutosyncService::refreshIndex(PackageFormat $format): void` — syncs all enabled+autosync+stale repos of `$format` (deferring per-repo rebuild), then rebuilds that format's index once.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RepositoryAutosyncRefreshIndexTest.php`:

```php
<?php

use App\Enums\PackageFormat;
use App\Models\Repository;
use App\Models\SyncLog;
use App\Services\RepositoryAutosyncService;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('syncs only stale autosync repos of the requested format', function () {
    Storage::fake('local');

    $staleComposer = Repository::factory()->create(['format' => PackageFormat::Composer, 'autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);
    $freshComposer = Repository::factory()->create(['format' => PackageFormat::Composer, 'autosync' => true, 'last_sync_at' => now()->subSeconds(10)]);
    $noFlagComposer = Repository::factory()->create(['format' => PackageFormat::Composer, 'autosync' => false, 'last_sync_at' => now()->subMinutes(5)]);
    $staleNpm = Repository::factory()->npm()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

    $synced = [];
    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldReceive('sync')->andReturnUsing(function ($repo) use (&$synced) {
        $synced[] = $repo->id;

        return new SyncLog;
    });
    app()->instance(RepositorySyncService::class, $sync);

    app(RepositoryAutosyncService::class)->refreshIndex(PackageFormat::Composer);

    expect($synced)->toBe([$staleComposer->id]);
});

it('rebuilds the composer index with freshly synced data', function () {
    Storage::fake('local');
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/repos/acme/tools/tags')) {
            return Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 'sha1']]], 200);
        }
        if (str_contains($url, '/repos/acme/tools/branches')) {
            return Http::response([['name' => 'main', 'commit' => ['sha' => 'sha2']]], 200);
        }
        if (str_contains($url, '/repos/acme/tools/contents/composer.json')) {
            return Http::response(['content' => base64_encode(json_encode(['name' => 'acme/tools', 'type' => 'library']))], 200);
        }

        return Http::response([], 404);
    });

    Repository::factory()->create([
        'repo_full_name' => 'acme/tools',
        'url' => 'https://github.com/acme/tools',
        'format' => PackageFormat::Composer,
        'autosync' => true,
        'last_sync_at' => now()->subMinutes(5),
    ]);

    app(RepositoryAutosyncService::class)->refreshIndex(PackageFormat::Composer);

    $packages = json_decode(Storage::disk('local')->get('packgrid/packages.json'), true);
    expect($packages['packages']['acme/tools'])->toHaveKey('v1.0.0');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositoryAutosyncRefreshIndexTest`
Expected: FAIL — `refreshIndex()` is not defined.

- [ ] **Step 3: Add `refreshIndex()`**

Append this method to `app/Services/RepositoryAutosyncService.php` (inside the class):

```php
/**
 * Refresh every stale autosync repository of a format, then rebuild that
 * format's registry index once. Used before serving registry metadata.
 */
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
        $this->maybeSync($repo, rebuildIndex: false);
    }

    match ($format) {
        PackageFormat::Npm => $this->npmIndex->rebuild(),
        default => $this->composerIndex->rebuild(),
    };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RepositoryAutosyncRefreshIndexTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/RepositoryAutosyncService.php tests/Feature/RepositoryAutosyncRefreshIndexTest.php
git commit -m "feat(autosync): refresh stale autosync repos before serving the index"
```

---

### Task 7: Hook autosync into the Composer `packages.json` endpoint

**Files:**
- Modify: `app/Http/Controllers/PackageMetadataController.php:11-14`
- Test: `tests/Feature/ComposerAutosyncEndpointTest.php`

**Interfaces:**
- Consumes: `RepositoryAutosyncService::refreshIndex(PackageFormat::Composer)` (Task 6).
- Produces: `GET /packages.json` refreshes stale autosync Composer repos before serving.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ComposerAutosyncEndpointTest.php`:

```php
<?php

use App\Models\Repository;
use App\Models\SyncLog;
use App\Services\PackageMetadataStore;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    app(PackageMetadataStore::class)->writePackagesIndex(['packages' => []]);
});

it('syncs a stale autosync composer repo before serving packages.json', function () {
    $repo = Repository::factory()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldReceive('sync')->once()
        ->withArgs(fn ($r, $rebuild = true) => $r->id === $repo->id)
        ->andReturn(new SyncLog);
    app()->instance(RepositorySyncService::class, $sync);

    $this->get('/packages.json')->assertOk();
});

it('does not sync when autosync is off', function () {
    Repository::factory()->create(['autosync' => false, 'last_sync_at' => now()->subMinutes(5)]);

    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldNotReceive('sync');
    app()->instance(RepositorySyncService::class, $sync);

    $this->get('/packages.json')->assertOk();
});

it('still serves the index when the autosync sync fails', function () {
    Repository::factory()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldReceive('sync')->once()->andThrow(new RuntimeException('github down'));
    app()->instance(RepositorySyncService::class, $sync);

    $this->get('/packages.json')->assertOk()->assertJsonStructure(['packages']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ComposerAutosyncEndpointTest`
Expected: FAIL — first test fails because `index()` never triggers a sync.

- [ ] **Step 3: Wire the coordinator into the controller**

Replace `app/Http/Controllers/PackageMetadataController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Services\PackageMetadataStore;
use App\Services\RepositoryAutosyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageMetadataController extends Controller
{
    public function index(PackageMetadataStore $store, RepositoryAutosyncService $autosync): JsonResponse
    {
        $autosync->refreshIndex(PackageFormat::Composer);

        return response()->json($store->readPackagesIndex());
    }

    public function show(Request $request, PackageMetadataStore $store, string $vendor, string $package): JsonResponse
    {
        $metadata = $store->readPackage($vendor, $package);

        if ($metadata === null) {
            abort(404);
        }

        return response()->json($metadata);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ComposerAutosyncEndpointTest`
Expected: PASS.

- [ ] **Step 5: Confirm the existing packages.json test still passes**

Run: `php artisan test --filter=PackgridTest`
Expected: PASS (existing repos default `autosync = false`, so the index is served unchanged).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PackageMetadataController.php tests/Feature/ComposerAutosyncEndpointTest.php
git commit -m "feat(autosync): refresh autosync repos before serving packages.json"
```

---

### Task 8: Hook autosync into the NPM packument endpoint

**Files:**
- Modify: `app/Http/Controllers/NpmMetadataController.php`
- Test: `tests/Feature/NpmAutosyncEndpointTest.php`

**Interfaces:**
- Consumes: `RepositoryAutosyncService::refreshIndex(PackageFormat::Npm)` (Task 6).
- Produces: `GET /npm/{package}` and scoped variants refresh stale autosync NPM repos before serving.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NpmAutosyncEndpointTest.php`:

```php
<?php

use App\Models\Repository;
use App\Models\SyncLog;
use App\Services\NpmMetadataStore;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    app(NpmMetadataStore::class)->writePackage('test-package', ['name' => 'test-package', 'versions' => []]);
});

it('syncs a stale autosync npm repo before serving the packument', function () {
    $repo = Repository::factory()->npm()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldReceive('sync')->once()
        ->withArgs(fn ($r, $rebuild = true) => $r->id === $repo->id)
        ->andReturn(new SyncLog);
    app()->instance(RepositorySyncService::class, $sync);

    $this->get('/npm/test-package')->assertOk();
});

it('does not sync composer repos on an npm packument request', function () {
    Repository::factory()->create(['format' => \App\Enums\PackageFormat::Composer, 'autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldNotReceive('sync');
    app()->instance(RepositorySyncService::class, $sync);

    $this->get('/npm/test-package')->assertOk();
});

it('serves the packument even if the autosync sync fails', function () {
    Repository::factory()->npm()->create(['autosync' => true, 'last_sync_at' => now()->subMinutes(5)]);

    $sync = Mockery::mock(RepositorySyncService::class);
    $sync->shouldReceive('sync')->once()->andThrow(new RuntimeException('github down'));
    app()->instance(RepositorySyncService::class, $sync);

    $this->get('/npm/test-package')->assertOk()->assertJson(['name' => 'test-package']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NpmAutosyncEndpointTest`
Expected: FAIL — the packument endpoint does not trigger a sync.

- [ ] **Step 3: Wire the coordinator into all three NPM metadata methods**

In `app/Http/Controllers/NpmMetadataController.php`, add imports:

```php
use App\Enums\PackageFormat;
use App\Services\RepositoryAutosyncService;
```

Add a private helper and call it at the start of `show()`, `showScoped()`, and `showScopedEncoded()` (before each `$store->readPackage(...)` call):

```php
private function refreshNpm(RepositoryAutosyncService $autosync): void
{
    $autosync->refreshIndex(PackageFormat::Npm);
}
```

Then update each method signature to inject `RepositoryAutosyncService $autosync` and call `$this->refreshNpm($autosync);` first. For example `show()` becomes:

```php
public function show(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $package): JsonResponse
{
    $this->refreshNpm($autosync);

    $metadata = $store->readPackage($package);

    if ($metadata === null) {
        return response()->json(['error' => 'Not found'], 404);
    }

    return response()->json($metadata);
}
```

Apply the same change to `showScoped(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $scope, string $package)` and `showScopedEncoded(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $scopedPackage)` — inject the service, call `$this->refreshNpm($autosync);` as the first line, and keep the rest of each method body unchanged. (Filament/Laravel resolves the injected service before the route string parameters.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=NpmAutosyncEndpointTest`
Expected: PASS.

- [ ] **Step 5: Confirm the existing NPM endpoint tests still pass**

Run: `php artisan test --filter=NpmTest`
Expected: PASS (existing repos default `autosync = false`).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NpmMetadataController.php tests/Feature/NpmAutosyncEndpointTest.php
git commit -m "feat(autosync): refresh autosync repos before serving npm packuments"
```

---

### Task 9: Gate the download endpoints by the autosync flag

**Files:**
- Modify: `app/Http/Controllers/PackageProxyController.php:35-41`
- Modify: `app/Http/Controllers/NpmProxyController.php:39-45`
- Modify: `tests/Feature/DownloadTrackingTest.php:159-227` (the "Auto-Sync on Download" describe block)

**Interfaces:**
- Consumes: `RepositoryAutosyncService::maybeSync(Repository)` (Task 5).
- Produces: downloads sync the repo on request **only** when `autosync` is on and the repo is stale.

- [ ] **Step 1: Update the "Auto-Sync on Download" tests to the new contract**

Replace the `describe('Auto-Sync on Download', ...)` block in `tests/Feature/DownloadTrackingTest.php` (lines 159-227) with:

```php
// =============================================================================
// AUTO-SYNC ON DOWNLOAD TESTS (gated by the autosync flag)
// =============================================================================

describe('Auto-Sync on Download', function () {
    it('triggers sync when autosync is on and last_sync_at is stale', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/stale',
            'format' => PackageFormat::Composer,
            'autosync' => true,
            'download_count' => 0,
            'last_sync_at' => now()->subMinutes(2),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldReceive('sync')->once()
            ->withArgs(fn ($repo, $rebuild = true) => $repo->id === $repository->id)
            ->andReturn(new \App\Models\SyncLog);
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/stale/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/stale/v1.0.0.zip')->assertOk();
    });

    it('does not trigger sync when autosync is off', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/noflag',
            'format' => PackageFormat::Composer,
            'autosync' => false,
            'download_count' => 0,
            'last_sync_at' => now()->subMinutes(5),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldNotReceive('sync');
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/noflag/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/noflag/v1.0.0.zip')->assertOk();
    });

    it('does not trigger sync when last_sync_at is recent', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/fresh',
            'format' => PackageFormat::Composer,
            'autosync' => true,
            'download_count' => 0,
            'last_sync_at' => now()->subSeconds(30),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldNotReceive('sync');
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/fresh/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/fresh/v1.0.0.zip')->assertOk();
    });

    it('does not block download when sync fails', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/sync-fail',
            'format' => PackageFormat::Composer,
            'autosync' => true,
            'download_count' => 0,
            'last_sync_at' => now()->subMinutes(5),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Sync failed'));
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/sync-fail/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/sync-fail/v1.0.0.zip')->assertOk();

        expect(DownloadLog::count())->toBe(1);
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=DownloadTrackingTest`
Expected: FAIL — the controller still uses `needsSync()` directly (ignores the flag and the new 2-arg `sync()` matcher).

- [ ] **Step 3: Use the coordinator in the Composer download controller**

In `app/Http/Controllers/PackageProxyController.php`, replace the import `use App\Services\RepositorySyncService;` with `use App\Services\RepositoryAutosyncService;` and replace lines 35-41 (the `if ($repository->needsSync()) { ... }` block) with:

```php
        app(RepositoryAutosyncService::class)->maybeSync($repository);
```

- [ ] **Step 4: Use the coordinator in the NPM download controller**

In `app/Http/Controllers/NpmProxyController.php`, replace the import `use App\Services\RepositorySyncService;` with `use App\Services\RepositoryAutosyncService;` and replace lines 39-45 (the `if ($repository->needsSync()) { ... }` block) with:

```php
        app(RepositoryAutosyncService::class)->maybeSync($repository);
```

(Keep the `$ref = preg_replace('/\.tgz$/', '', $ref);` line above it untouched.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=DownloadTrackingTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PackageProxyController.php app/Http/Controllers/NpmProxyController.php tests/Feature/DownloadTrackingTest.php
git commit -m "feat(autosync): gate download-time sync behind the autosync flag"
```

---

### Task 10: Add the `autosync` toggle to the Filament form

**Files:**
- Modify: `app/Filament/Resources/RepositoryResource.php:140-161` (Sync Options section)
- Modify: `lang/en.json`, `lang/pt_BR.json`, `lang/es.json`, `lang/fr.json`
- Test: `tests/Feature/RepositoryResourceTest.php` (add cases to the `EditRepository Page` describe block)

**Interfaces:**
- Consumes: `Repository->autosync` (Task 1).
- Produces: an `autosync` Toggle in the form's Sync Options section.

- [ ] **Step 1: Write the failing test**

Add these two tests inside the `describe('EditRepository Page', ...)` block in `tests/Feature/RepositoryResourceTest.php`:

```php
    it('exposes the autosync toggle', function () {
        $repository = Repository::factory()->create();

        livewire(EditRepository::class, ['record' => $repository->id])
            ->assertFormFieldExists('autosync');
    });

    it('can enable autosync', function () {
        $repository = Repository::factory()->create(['autosync' => false]);

        livewire(EditRepository::class, ['record' => $repository->id])
            ->fillForm(['autosync' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->autosync)->toBeTrue();
    });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositoryResourceTest`
Expected: FAIL — `assertFormFieldExists('autosync')` fails (field not present).

- [ ] **Step 3: Add the toggle to the Sync Options section**

In `app/Filament/Resources/RepositoryResource.php`, inside the `Section::make(__('repository.section.sync_options'))` schema array, add the `autosync` toggle after the `enabled` toggle (before `clone_enabled`):

```php
                        Toggle::make('enabled')
                            ->label(__('common.enabled'))
                            ->helperText(__('repository.field.enabled_helper'))
                            ->default(true),
                        Toggle::make('autosync')
                            ->label(__('repository.field.autosync'))
                            ->helperText(__('repository.field.autosync_helper'))
                            ->default(false),
                        Toggle::make('clone_enabled')
```

- [ ] **Step 4: Add the translation keys to all four locales**

Add these keys to each locale file (place them next to the existing `repository.field.enabled_helper` key). Use the values shown per language:

`lang/en.json`:
```json
    "repository.field.autosync": "Autosync",
    "repository.field.autosync_helper": "Sync this repository from GitHub on every Composer/NPM request before responding (cached for ~1 minute to avoid hammering GitHub).",
```

`lang/pt_BR.json`:
```json
    "repository.field.autosync": "Autosync",
    "repository.field.autosync_helper": "Sincroniza este repositório do GitHub a cada requisição Composer/NPM antes de responder (com cache de ~1 minuto para não sobrecarregar o GitHub).",
```

`lang/es.json`:
```json
    "repository.field.autosync": "Autosync",
    "repository.field.autosync_helper": "Sincroniza este repositorio desde GitHub en cada solicitud de Composer/NPM antes de responder (con caché de ~1 minuto para no saturar GitHub).",
```

`lang/fr.json`:
```json
    "repository.field.autosync": "Autosync",
    "repository.field.autosync_helper": "Synchronise ce dépôt depuis GitHub à chaque requête Composer/NPM avant de répondre (mis en cache ~1 minute pour ne pas surcharger GitHub).",
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=RepositoryResourceTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/RepositoryResource.php lang/en.json lang/pt_BR.json lang/es.json lang/fr.json tests/Feature/RepositoryResourceTest.php
git commit -m "feat(autosync): add autosync toggle to the repository form"
```

---

### Task 11: `RepositoryTagReport` read-model

**Files:**
- Create: `app/Support/RepositoryTagReport.php`
- Test: `tests/Feature/RepositoryTagReportTest.php`

**Interfaces:**
- Consumes: `PackageMetadataStore::readRepositoryMetadata($id)`, `NpmMetadataStore::readRepositoryMetadata($id)`, `download_logs` (`DownloadLog`).
- Produces: `RepositoryTagReport::rows(Repository $repository): array` — list of `['package' => string, 'version' => string, 'downloads' => int]`, tags first (semver-desc), branch/dev versions last. Download counts reconcile `v`/`dev-`/`0.0.0-` prefixes between the metadata version key and the logged `package_version`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RepositoryTagReportTest.php`:

```php
<?php

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\PackageMetadataStore;
use App\Support\RepositoryTagReport;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('lists available composer versions with reconciled download counts', function () {
    $repo = Repository::factory()->create(['format' => PackageFormat::Composer]);

    app(PackageMetadataStore::class)->writeRepositoryMetadata($repo->id, [
        'acme/tools' => [
            'v1.0.0' => ['name' => 'acme/tools', 'version' => 'v1.0.0'],
            'v2.0.0' => ['name' => 'acme/tools', 'version' => 'v2.0.0'],
            'dev-main' => ['name' => 'acme/tools', 'version' => 'dev-main'],
        ],
    ]);

    // tag download logged as the raw ref "v1.0.0"; branch download logged as raw ref "main"
    DownloadLog::factory()->count(2)->forRepository($repo)->create(['package_version' => 'v1.0.0']);
    DownloadLog::factory()->forRepository($repo)->create(['package_version' => 'main']);

    $rows = app(RepositoryTagReport::class)->rows($repo);

    expect($rows)->toHaveCount(3);

    $byVersion = collect($rows)->keyBy('version');
    expect($byVersion['v1.0.0']['downloads'])->toBe(2)
        ->and($byVersion['v2.0.0']['downloads'])->toBe(0)
        ->and($byVersion['dev-main']['downloads'])->toBe(1);

    // tags before branch versions; newest tag first
    expect($rows[0]['version'])->toBe('v2.0.0')
        ->and($rows[1]['version'])->toBe('v1.0.0')
        ->and($rows[2]['version'])->toBe('dev-main');
});

it('returns an empty array when the repository has no metadata', function () {
    $repo = Repository::factory()->create();

    expect(app(RepositoryTagReport::class)->rows($repo))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositoryTagReportTest`
Expected: FAIL — class `RepositoryTagReport` does not exist.

- [ ] **Step 3: Create the read-model**

Create `app/Support/RepositoryTagReport.php`:

```php
<?php

namespace App\Support;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\NpmMetadataStore;
use App\Services\PackageMetadataStore;

class RepositoryTagReport
{
    public function __construct(
        private readonly PackageMetadataStore $composerStore,
        private readonly NpmMetadataStore $npmStore,
    ) {}

    /**
     * @return array<int, array{package: string, version: string, downloads: int}>
     */
    public function rows(Repository $repository): array
    {
        $store = $repository->format === PackageFormat::Npm ? $this->npmStore : $this->composerStore;
        $metadata = $store->readRepositoryMetadata($repository->id) ?? [];

        $downloads = DownloadLog::query()
            ->where('repository_id', $repository->id)
            ->selectRaw('package_version, COUNT(*) as total')
            ->groupBy('package_version')
            ->pluck('total', 'package_version');

        $canonical = [];
        foreach ($downloads as $version => $total) {
            $key = $this->canonical((string) $version);
            $canonical[$key] = ($canonical[$key] ?? 0) + (int) $total;
        }

        $rows = [];
        foreach ($metadata as $packageName => $versions) {
            foreach (array_keys($versions) as $version) {
                $rows[] = [
                    'package' => $packageName,
                    'version' => $version,
                    'downloads' => (int) ($canonical[$this->canonical((string) $version)] ?? 0),
                ];
            }
        }

        return $this->sortRows($rows);
    }

    private function isBranch(string $version): bool
    {
        return str_starts_with($version, 'dev-') || str_starts_with($version, '0.0.0-');
    }

    private function canonical(string $version): string
    {
        $version = preg_replace('/^dev-/', '', $version);
        $version = preg_replace('/^0\.0\.0-/', '', $version);

        return preg_replace('/^v(?=\d)/', '', $version);
    }

    /**
     * @param  array<int, array{package: string, version: string, downloads: int}>  $rows
     * @return array<int, array{package: string, version: string, downloads: int}>
     */
    private function sortRows(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $aBranch = $this->isBranch($a['version']);
            $bBranch = $this->isBranch($b['version']);

            if ($aBranch !== $bBranch) {
                return $aBranch <=> $bBranch; // tags (false) first
            }

            if ($aBranch) {
                return strcmp($a['version'], $b['version']);
            }

            return version_compare($this->canonical($b['version']), $this->canonical($a['version']));
        });

        return $rows;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RepositoryTagReportTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/RepositoryTagReport.php tests/Feature/RepositoryTagReportTest.php
git commit -m "feat(repositories): add tag report read-model joining versions with downloads"
```

---

### Task 12: "Tags & Downloads" section on the repository view page

**Files:**
- Modify: `app/Filament/Resources/RepositoryResource.php:375-509` (`infolist()`)
- Modify: `lang/en.json`, `lang/pt_BR.json`, `lang/es.json`, `lang/fr.json`
- Test: `tests/Feature/RepositoryResourceTest.php` (add a `describe` block)

**Interfaces:**
- Consumes: `RepositoryTagReport::rows(Repository)` (Task 11).
- Produces: a collapsible "Tags & Downloads" infolist section listing each version with its package and download count.

- [ ] **Step 1: Write the failing test**

Add this block to `tests/Feature/RepositoryResourceTest.php` (after the `ViewRepository Sync action` describe block). Note the imports `PackageMetadataStore` and `DownloadLog` are needed — add `use App\Services\PackageMetadataStore;` and `use App\Models\DownloadLog;` at the top of the file if not present:

```php
describe('ViewRepository Tags & Downloads', function () {
    it('renders available versions with their download counts', function () {
        $repo = Repository::factory()->create(['format' => PackageFormat::Composer]);

        app(PackageMetadataStore::class)->writeRepositoryMetadata($repo->id, [
            'acme/tools' => [
                'v1.0.0' => ['name' => 'acme/tools', 'version' => 'v1.0.0'],
                'v2.0.0' => ['name' => 'acme/tools', 'version' => 'v2.0.0'],
            ],
        ]);
        DownloadLog::factory()->count(2)->forRepository($repo)->create(['package_version' => 'v1.0.0']);

        livewire(ViewRepository::class, ['record' => $repo->getKey()])
            ->assertOk()
            ->assertSee('v1.0.0')
            ->assertSee('v2.0.0');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="Tags & Downloads"`
Expected: FAIL — the version strings are not rendered (no section yet).

- [ ] **Step 3: Add the section to the infolist**

In `app/Filament/Resources/RepositoryResource.php`, add an import at the top:

```php
use App\Support\RepositoryTagReport;
```

Then, inside `infolist()`, add a new section to the `->schema([ ... ])` array, after the `Section::make(__('repository.section.recent_syncs'))` section:

```php
                Section::make(__('repository.section.tags_downloads'))
                    ->icon('heroicon-o-tag')
                    ->description(__('repository.section.tags_downloads_description'))
                    ->schema([
                        RepeatableEntry::make('tags')
                            ->label('')
                            ->state(fn (Repository $record): array => app(RepositoryTagReport::class)->rows($record))
                            ->schema([
                                TextEntry::make('version')
                                    ->label(__('repository.tags.version'))
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('package')
                                    ->label(__('repository.tags.package')),
                                TextEntry::make('downloads')
                                    ->label(__('repository.tags.downloads'))
                                    ->badge()
                                    ->color('info'),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
```

- [ ] **Step 4: Add the translation keys to all four locales**

`lang/en.json`:
```json
    "repository.section.tags_downloads": "Tags & Downloads",
    "repository.section.tags_downloads_description": "Available versions for this repository and how often each has been downloaded.",
    "repository.tags.version": "Version",
    "repository.tags.package": "Package",
    "repository.tags.downloads": "Downloads",
```

`lang/pt_BR.json`:
```json
    "repository.section.tags_downloads": "Tags e Downloads",
    "repository.section.tags_downloads_description": "Versões disponíveis deste repositório e quantas vezes cada uma foi baixada.",
    "repository.tags.version": "Versão",
    "repository.tags.package": "Pacote",
    "repository.tags.downloads": "Downloads",
```

`lang/es.json`:
```json
    "repository.section.tags_downloads": "Tags y Descargas",
    "repository.section.tags_downloads_description": "Versiones disponibles de este repositorio y cuántas veces se ha descargado cada una.",
    "repository.tags.version": "Versión",
    "repository.tags.package": "Paquete",
    "repository.tags.downloads": "Descargas",
```

`lang/fr.json`:
```json
    "repository.section.tags_downloads": "Tags et Téléchargements",
    "repository.section.tags_downloads_description": "Versions disponibles de ce dépôt et nombre de téléchargements de chacune.",
    "repository.tags.version": "Version",
    "repository.tags.package": "Paquet",
    "repository.tags.downloads": "Téléchargements",
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter="Tags & Downloads"`
Expected: PASS.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS (all suites green).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/RepositoryResource.php lang/en.json lang/pt_BR.json lang/es.json lang/fr.json tests/Feature/RepositoryResourceTest.php
git commit -m "feat(repositories): show tags and per-tag downloads on the view page"
```

---

## Final Verification

- [ ] Run the entire test suite in parallel: `php artisan test --parallel`
- [ ] Confirm all suites are green and there are no skipped/incomplete tests related to this work.
- [ ] Manually sanity-check (optional): enable autosync on a repo, run `composer update` against the registry, confirm a `SyncLog` row is created and `last_sync_at` advances.

## Self-Review (completed by plan author)

- **Spec coverage:** Tags+downloads view (Tasks 11–12); `autosync` flag column + form (Tasks 1, 10); autosync at Composer index (Task 7), NPM packument (Task 8), both downloads (Task 9); GitHub 60s cache (Task 3); freshness gate (Task 2) + per-repo lock (Task 5) + deferred rebuild (Task 4, 6); config (Tasks 2, 3); reconciliation of `package_version` ↔ version key (Task 11). All spec sections map to a task.
- **Placeholder scan:** No TBD/TODO; every code/test step contains complete code and exact commands.
- **Type consistency:** `sync(Repository, bool $rebuildIndex = true)`, `maybeSync(Repository, bool $rebuildIndex = true)`, `refreshIndex(PackageFormat)`, `rows(Repository): array` are used consistently across producing and consuming tasks. Config keys (`packgrid.autosync.fresh_seconds`, `packgrid.autosync.lock_seconds`, `packgrid.github_cache.ttl`) match across tasks.
