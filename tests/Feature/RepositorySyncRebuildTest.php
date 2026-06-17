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
    $repo = Repository::factory()->create(['repo_full_name' => 'acme/tools', 'url' => 'https://github.com/acme/tools', 'ref_filter' => 'v1.0.0']);

    $indexBuilder = Mockery::mock(PackageIndexBuilder::class);
    $indexBuilder->shouldNotReceive('rebuild');
    app()->instance(PackageIndexBuilder::class, $indexBuilder);

    $log = app(RepositorySyncService::class)->sync($repo, rebuildIndex: false);

    expect($log->status->value)->toBe('success');
    $repo->refresh();
    expect($repo->package_count)->toBe(1); // per-repo metadata still computed
});
