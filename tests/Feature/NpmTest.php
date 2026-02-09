<?php

use App\Adapters\ComposerAdapter;
use App\Adapters\NpmAdapter;
use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Models\Repository;
use App\Models\SyncLog;
use App\Services\AdapterFactory;
use App\Services\GitHubClient;
use App\Services\NpmMetadataStore;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('package format enum has correct values', function () {
    expect(PackageFormat::Composer->value)->toBe('composer')
        ->and(PackageFormat::Npm->value)->toBe('npm')
        ->and(PackageFormat::Composer->manifestFile())->toBe('composer.json')
        ->and(PackageFormat::Npm->manifestFile())->toBe('package.json')
        ->and(PackageFormat::Composer->archiveExtension())->toBe('zip')
        ->and(PackageFormat::Npm->archiveExtension())->toBe('tgz');
});

test('adapter factory creates correct adapter for format', function () {
    $factory = app(AdapterFactory::class);

    $composerAdapter = $factory->make(PackageFormat::Composer);
    expect($composerAdapter)->toBeInstanceOf(ComposerAdapter::class)
        ->and($composerAdapter->getFormat())->toBe(PackageFormat::Composer);

    $npmAdapter = $factory->make(PackageFormat::Npm);
    expect($npmAdapter)->toBeInstanceOf(NpmAdapter::class)
        ->and($npmAdapter->getFormat())->toBe(PackageFormat::Npm);
});

test('adapter factory creates adapter from string', function () {
    $factory = app(AdapterFactory::class);

    $composerAdapter = $factory->makeFromString('composer');
    expect($composerAdapter)->toBeInstanceOf(ComposerAdapter::class);

    $npmAdapter = $factory->makeFromString('npm');
    expect($npmAdapter)->toBeInstanceOf(NpmAdapter::class);
});

test('adapter factory throws exception for unknown format', function () {
    $factory = app(AdapterFactory::class);

    expect(fn () => $factory->makeFromString('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unknown package format: unknown');
});

test('npm adapter normalizes version correctly', function () {
    $adapter = app(NpmAdapter::class);

    // Tags without v prefix
    expect($adapter->normalizeVersion('1.0.0', 'tag'))->toBe('1.0.0');

    // Tags with v prefix
    expect($adapter->normalizeVersion('v1.0.0', 'tag'))->toBe('1.0.0');

    // Branches become 0.0.0-branchname
    expect($adapter->normalizeVersion('main', 'branch'))->toBe('0.0.0-main');
    expect($adapter->normalizeVersion('feature/test', 'branch'))->toBe('0.0.0-feature-test');
});

test('npm adapter generates correct package name', function () {
    $adapter = app(NpmAdapter::class);

    // With name in manifest
    $name = $adapter->getPackageName(['name' => 'my-package'], 'owner/repo');
    expect($name)->toBe('my-package');

    // Fallback to scoped name
    $fallbackName = $adapter->getPackageName([], 'owner/repo');
    expect($fallbackName)->toBe('@owner/repo');
});

test('npm adapter builds correct dist url', function () {
    config(['app.url' => 'https://packgrid.test']);

    $adapter = app(NpmAdapter::class);
    $url = $adapter->buildDistUrl('owner/repo', 'v1.0.0');

    expect($url)->toBe('https://packgrid.test/npm/-/owner/repo/v1.0.0.tgz');
});

test('npm adapter builds registry metadata', function () {
    $adapter = app(NpmAdapter::class);

    $versions = [
        '1.0.0' => [
            'name' => 'test-package',
            'version' => '1.0.0',
            'description' => 'Test',
            'repository' => ['type' => 'git', 'url' => 'https://github.com/test/pkg'],
        ],
        '1.1.0' => [
            'name' => 'test-package',
            'version' => '1.1.0',
            'description' => 'Test updated',
            'repository' => ['type' => 'git', 'url' => 'https://github.com/test/pkg'],
        ],
    ];

    $metadata = $adapter->buildRegistryMetadata('test-package', $versions);

    expect($metadata['name'])->toBe('test-package')
        ->and($metadata['dist-tags']['latest'])->toBe('1.1.0')
        ->and($metadata['versions'])->toHaveCount(2)
        ->and($metadata)->toHaveKey('time');
});

test('npm metadata store sanitizes package names correctly', function () {
    $store = app(NpmMetadataStore::class);

    // Scoped package
    expect($store->sanitizePackageName('@scope/package'))
        ->toBe('_at_scope_slash_package');

    // Non-scoped package
    expect($store->sanitizePackageName('simple-package'))
        ->toBe('simple-package');

    // Unsanitize
    expect($store->unsanitizePackageName('_at_scope_slash_package'))
        ->toBe('@scope/package');
});

test('repository model casts format correctly', function () {
    $composerRepo = Repository::factory()->create([
        'format' => 'composer',
    ]);

    expect($composerRepo->format)->toBe(PackageFormat::Composer);

    $npmRepo = Repository::factory()->create([
        'format' => 'npm',
    ]);

    expect($npmRepo->format)->toBe(PackageFormat::Npm);
});

test('repository defaults to composer format', function () {
    $repository = Repository::factory()->create();

    expect($repository->format)->toBe(PackageFormat::Composer);
});

test('npm repository sync generates npm metadata', function () {
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/repos/test/npm-pkg/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'tagsha123']],
            ], 200);
        }

        if (str_contains($url, '/repos/test/npm-pkg/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'branchsha456']],
            ], 200);
        }

        if (str_contains($url, '/repos/test/npm-pkg/contents/package.json')) {
            return Http::response([
                'content' => base64_encode(json_encode([
                    'name' => '@test/npm-pkg',
                    'version' => '1.0.0',
                    'description' => 'Test NPM package',
                ])),
            ], 200);
        }

        return Http::response([], 404);
    });

    $repository = Repository::factory()->create([
        'repo_full_name' => 'test/npm-pkg',
        'url' => 'https://github.com/test/npm-pkg',
        'visibility' => RepositoryVisibility::PublicRepo,
        'format' => PackageFormat::Npm,
    ]);

    app(RepositorySyncService::class)->sync($repository);

    expect(SyncLog::count())->toBe(1);

    // Check NPM metadata was stored
    $store = app(NpmMetadataStore::class);
    $metadata = $store->readPackage('@test/npm-pkg');

    expect($metadata)->not->toBeNull()
        ->and($metadata['name'])->toBe('@test/npm-pkg');
});

test('npm endpoint returns 404 for non-existent package', function () {
    Storage::fake('local');

    $this->get('/npm/non-existent-package')
        ->assertStatus(404)
        ->assertJson(['error' => 'Not found']);
});

test('npm endpoint returns package metadata', function () {
    Storage::fake('local');

    $store = app(NpmMetadataStore::class);
    $store->writePackage('test-package', [
        'name' => 'test-package',
        'versions' => ['1.0.0' => []],
        'dist-tags' => ['latest' => '1.0.0'],
    ]);

    $this->get('/npm/test-package')
        ->assertOk()
        ->assertJson([
            'name' => 'test-package',
            'dist-tags' => ['latest' => '1.0.0'],
        ]);
});

test('npm scoped package endpoint returns package metadata', function () {
    Storage::fake('local');

    $store = app(NpmMetadataStore::class);
    $store->writePackage('@scope/package', [
        'name' => '@scope/package',
        'versions' => ['1.0.0' => []],
        'dist-tags' => ['latest' => '1.0.0'],
    ]);

    $this->get('/npm/@scope/package')
        ->assertOk()
        ->assertJson([
            'name' => '@scope/package',
            'dist-tags' => ['latest' => '1.0.0'],
        ]);
});

test('npm tarball endpoint returns 404 for non-existent repository', function () {
    $this->get('/npm/-/owner/repo/v1.0.0.tgz')
        ->assertStatus(404);
});

test('npm endpoints require authentication when tokens exist', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->create();

    $this->get('/npm/test-package')
        ->assertStatus(401)
        ->assertJson(['error' => 'Authentication required.']);
});

test('npm endpoints accept valid token via HTTP Basic Auth', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->create([
        'token' => 'npm-test-token',
    ]);

    $store = app(NpmMetadataStore::class);
    $store->writePackage('test-package', [
        'name' => 'test-package',
        'versions' => [],
    ]);

    $this->withBasicAuth('npm', 'npm-test-token')
        ->get('/npm/test-package')
        ->assertOk();
});

test('npm endpoints accept valid token via Bearer token', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->create([
        'token' => 'npm-bearer-token',
    ]);

    $store = app(NpmMetadataStore::class);
    $store->writePackage('test-package', [
        'name' => 'test-package',
        'versions' => [],
    ]);

    $this->withHeader('Authorization', 'Bearer npm-bearer-token')
        ->get('/npm/test-package')
        ->assertOk();
});

test('github client can download tarball', function () {
    Http::fake([
        'https://api.github.com/repos/owner/repo/tarball/v1.0.0' => Http::response('tarball-content', 200),
    ]);

    $client = app(GitHubClient::class);
    $response = $client->downloadTarball('owner/repo', 'v1.0.0');

    expect($response->body())->toBe('tarball-content');
});
