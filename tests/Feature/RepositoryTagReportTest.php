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
