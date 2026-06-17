<?php

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\NpmMetadataStore;
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

it('lists available npm versions with reconciled download counts', function () {
    $repo = Repository::factory()->npm()->create();

    app(NpmMetadataStore::class)->writeRepositoryMetadata($repo->id, [
        'acme-tools' => [
            '1.0.0' => ['name' => 'acme-tools', 'version' => '1.0.0'],
            '2.0.0' => ['name' => 'acme-tools', 'version' => '2.0.0'],
            '0.0.0-main' => ['name' => 'acme-tools', 'version' => '0.0.0-main'],
        ],
    ]);

    // tag download logged as the raw ref "1.0.0"; branch download logged as raw ref "main"
    DownloadLog::factory()->count(3)->npm()->forRepository($repo)->create(['package_version' => '1.0.0']);
    DownloadLog::factory()->npm()->forRepository($repo)->create(['package_version' => 'main']);

    $rows = app(RepositoryTagReport::class)->rows($repo);

    expect($rows)->toHaveCount(3);

    $byVersion = collect($rows)->keyBy('version');
    expect($byVersion['1.0.0']['downloads'])->toBe(3)
        ->and($byVersion['2.0.0']['downloads'])->toBe(0)
        ->and($byVersion['0.0.0-main']['downloads'])->toBe(1);

    // tags before branch versions; newest tag first
    expect($rows[0]['version'])->toBe('2.0.0')
        ->and($rows[1]['version'])->toBe('1.0.0')
        ->and($rows[2]['version'])->toBe('0.0.0-main');
});

it('reconciles the 0.0.0- branch prefix against the raw logged ref', function () {
    $repo = Repository::factory()->create(['format' => PackageFormat::Composer]);

    app(PackageMetadataStore::class)->writeRepositoryMetadata($repo->id, [
        'acme/tools' => [
            '0.0.0-feature' => ['name' => 'acme/tools', 'version' => '0.0.0-feature'],
        ],
    ]);

    // the download is logged under the raw branch ref "feature", not "0.0.0-feature"
    DownloadLog::factory()->count(2)->forRepository($repo)->create(['package_version' => 'feature']);

    $rows = app(RepositoryTagReport::class)->rows($repo);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['version'])->toBe('0.0.0-feature')
        ->and($rows[0]['downloads'])->toBe(2);
});

it('orders pre-release tags below their final release', function () {
    $repo = Repository::factory()->create(['format' => PackageFormat::Composer]);

    app(PackageMetadataStore::class)->writeRepositoryMetadata($repo->id, [
        'acme/tools' => [
            'v1.0.0' => ['name' => 'acme/tools', 'version' => 'v1.0.0'],
            'v1.0.0-rc1' => ['name' => 'acme/tools', 'version' => 'v1.0.0-rc1'],
            'v1.0.0-beta' => ['name' => 'acme/tools', 'version' => 'v1.0.0-beta'],
        ],
    ]);

    $versions = array_column(app(RepositoryTagReport::class)->rows($repo), 'version');

    // semver-desc: final release first, then rc, then beta (all are tags, not branches)
    expect($versions)->toBe(['v1.0.0', 'v1.0.0-rc1', 'v1.0.0-beta']);
});

it('reports each package separately when versions overlap across packages', function () {
    $repo = Repository::factory()->create(['format' => PackageFormat::Composer]);

    app(PackageMetadataStore::class)->writeRepositoryMetadata($repo->id, [
        'acme/tools' => [
            'v1.0.0' => ['name' => 'acme/tools', 'version' => 'v1.0.0'],
        ],
        'acme/helpers' => [
            'v1.0.0' => ['name' => 'acme/helpers', 'version' => 'v1.0.0'],
        ],
    ]);

    DownloadLog::factory()->count(4)->forRepository($repo)->create(['package_version' => 'v1.0.0']);

    $rows = app(RepositoryTagReport::class)->rows($repo);

    // both packages surface their own row for the shared version string
    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('package')->sort()->values()->all())
        ->toBe(['acme/helpers', 'acme/tools']);

    // download counts are keyed only by (reconciled) version, so both rows reflect the same total
    foreach ($rows as $row) {
        expect($row['version'])->toBe('v1.0.0')
            ->and($row['downloads'])->toBe(4);
    }
});

it('returns an empty array when the repository has no metadata', function () {
    $repo = Repository::factory()->create();

    expect(app(RepositoryTagReport::class)->rows($repo))->toBe([]);
});
