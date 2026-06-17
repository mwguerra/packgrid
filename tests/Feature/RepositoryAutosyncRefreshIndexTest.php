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
