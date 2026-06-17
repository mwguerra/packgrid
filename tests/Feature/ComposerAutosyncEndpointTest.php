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
