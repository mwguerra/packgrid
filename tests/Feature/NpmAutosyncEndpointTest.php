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
