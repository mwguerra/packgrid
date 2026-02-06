<?php

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Models\Token;
use App\Services\GitHubClient;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Http;

// =============================================================================
// DOWNLOAD LOG MODEL TESTS
// =============================================================================

describe('DownloadLog::logDownload', function () {
    it('creates a record and increments the download counter', function () {
        $repository = Repository::factory()->create(['download_count' => 0]);
        $token = Token::factory()->create();

        $log = DownloadLog::logDownload($repository, 'v1.0.0', PackageFormat::Composer, $token);

        expect($log)->toBeInstanceOf(DownloadLog::class)
            ->and($log->repository_id)->toBe($repository->id)
            ->and($log->token_id)->toBe($token->id)
            ->and($log->package_version)->toBe('v1.0.0')
            ->and($log->format)->toBe(PackageFormat::Composer);

        $repository->refresh();
        expect($repository->download_count)->toBe(1);
    });

    it('works with null token for public access', function () {
        $repository = Repository::factory()->create(['download_count' => 0]);

        $log = DownloadLog::logDownload($repository, 'v2.0.0', PackageFormat::Npm, null);

        expect($log->token_id)->toBeNull();

        $repository->refresh();
        expect($repository->download_count)->toBe(1);
    });
});

// =============================================================================
// CASCADE / NULL ON DELETE TESTS
// =============================================================================

describe('Relationship Constraints', function () {
    it('cascade deletes download logs when repository is deleted', function () {
        $repository = Repository::factory()->create();
        DownloadLog::factory()->count(3)->forRepository($repository)->create();

        expect(DownloadLog::where('repository_id', $repository->id)->count())->toBe(3);

        $repository->delete();

        expect(DownloadLog::where('repository_id', $repository->id)->count())->toBe(0);
    });

    it('nullifies token_id when token is deleted', function () {
        $token = Token::factory()->create();
        $log = DownloadLog::factory()->withToken($token)->create();

        expect($log->token_id)->toBe($token->id);

        $token->delete();

        $log->refresh();
        expect($log->token_id)->toBeNull();
    });
});

// =============================================================================
// COMPOSER DOWNLOAD ENDPOINT TESTS
// =============================================================================

describe('Composer Download Endpoint', function () {
    it('logs the download with correct format and token_id', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/tools',
            'format' => PackageFormat::Composer,
            'download_count' => 0,
            'last_sync_at' => now(),
        ]);

        $token = Token::factory()->create(['token' => 'test-composer-token']);

        Http::fake([
            'https://api.github.com/repos/acme/tools/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->withBasicAuth('user', 'test-composer-token')
            ->get('/dist/acme/tools/v1.0.0.zip')
            ->assertOk();

        $log = DownloadLog::first();
        expect($log)->not->toBeNull()
            ->and($log->repository_id)->toBe($repository->id)
            ->and($log->token_id)->toBe($token->id)
            ->and($log->package_version)->toBe('v1.0.0')
            ->and($log->format)->toBe(PackageFormat::Composer);

        $repository->refresh();
        expect($repository->download_count)->toBe(1);
    });

    it('logs download without token when no tokens exist', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/tools',
            'format' => PackageFormat::Composer,
            'download_count' => 0,
            'last_sync_at' => now(),
        ]);

        Http::fake([
            'https://api.github.com/repos/acme/tools/zipball/v2.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/tools/v2.0.0.zip')
            ->assertOk();

        $log = DownloadLog::first();
        expect($log)->not->toBeNull()
            ->and($log->token_id)->toBeNull()
            ->and($log->format)->toBe(PackageFormat::Composer);
    });
});

// =============================================================================
// NPM DOWNLOAD ENDPOINT TESTS
// =============================================================================

describe('NPM Download Endpoint', function () {
    it('logs the download with correct format', function () {
        $repository = Repository::factory()->npm()->create([
            'repo_full_name' => 'acme/npm-tools',
            'download_count' => 0,
            'last_sync_at' => now(),
        ]);

        Http::fake([
            'https://api.github.com/repos/acme/npm-tools/tarball/v1.0.0' => Http::response('tgz-content', 200),
        ]);

        $this->get('/npm/-/acme/npm-tools/v1.0.0.tgz')
            ->assertOk();

        $log = DownloadLog::first();
        expect($log)->not->toBeNull()
            ->and($log->repository_id)->toBe($repository->id)
            ->and($log->package_version)->toBe('v1.0.0')
            ->and($log->format)->toBe(PackageFormat::Npm);

        $repository->refresh();
        expect($repository->download_count)->toBe(1);
    });
});

// =============================================================================
// AUTO-SYNC ON DOWNLOAD TESTS
// =============================================================================

describe('Auto-Sync on Download', function () {
    it('triggers sync when last_sync_at is stale', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/stale',
            'format' => PackageFormat::Composer,
            'download_count' => 0,
            'last_sync_at' => now()->subMinutes(2),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldReceive('sync')->once()->with(
            Mockery::on(fn ($repo) => $repo->id === $repository->id)
        )->andReturn(new \App\Models\SyncLog);
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/stale/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/stale/v1.0.0.zip')
            ->assertOk();
    });

    it('does not trigger sync when last_sync_at is recent', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/fresh',
            'format' => PackageFormat::Composer,
            'download_count' => 0,
            'last_sync_at' => now()->subSeconds(30),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldNotReceive('sync');
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/fresh/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/fresh/v1.0.0.zip')
            ->assertOk();
    });

    it('does not block download when sync fails', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/sync-fail',
            'format' => PackageFormat::Composer,
            'download_count' => 0,
            'last_sync_at' => now()->subMinutes(5),
        ]);

        $syncService = Mockery::mock(RepositorySyncService::class);
        $syncService->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Sync failed'));
        app()->instance(RepositorySyncService::class, $syncService);

        Http::fake([
            'https://api.github.com/repos/acme/sync-fail/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/sync-fail/v1.0.0.zip')
            ->assertOk();

        expect(DownloadLog::count())->toBe(1);
    });
});
