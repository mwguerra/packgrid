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
