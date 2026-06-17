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
}
