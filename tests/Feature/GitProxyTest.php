<?php

use App\Enums\PackageFormat;
use App\Models\Credential;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Token;
use App\Support\PackgridSettings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    PackgridSettings::clearCache();
    $settings = Setting::firstOrCreate([], [
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
        'git_enabled' => true,
    ]);
    $settings->update(['git_enabled' => true]);
    PackgridSettings::clearCache();
});

// =============================================================================
// INFO/REFS ENDPOINT TESTS
// =============================================================================

describe('Git info/refs endpoint', function () {
    it('returns 403 when service is not git-upload-pack', function () {
        $repository = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
        ]);

        $this->get('/git/acme/tools.git/info/refs?service=git-receive-pack')
            ->assertForbidden();
    });

    it('returns 403 when service param is missing', function () {
        $repository = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
        ]);

        $this->get('/git/acme/tools.git/info/refs')
            ->assertForbidden();
    });

    it('returns 404 when repository not found', function () {
        $this->get('/git/acme/nonexistent.git/info/refs?service=git-upload-pack')
            ->assertNotFound();
    });

    it('returns 404 when repository exists but clone_enabled is false', function () {
        Repository::factory()->create([
            'repo_full_name' => 'acme/tools',
            'clone_enabled' => false,
        ]);

        $this->get('/git/acme/tools.git/info/refs?service=git-upload-pack')
            ->assertNotFound();
    });

    it('returns 404 when repository is disabled', function () {
        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
            'enabled' => false,
        ]);

        $this->get('/git/acme/tools.git/info/refs?service=git-upload-pack')
            ->assertNotFound();
    });

    it('returns 200 with correct content-type for valid request', function () {
        $credential = Credential::factory()->create(['token' => 'github_pat_test123']);
        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
            'credential_id' => $credential->id,
        ]);

        Http::fake([
            'https://github.com/acme/tools.git/info/refs?service=git-upload-pack' => Http::response('mock-git-refs', 200),
        ]);

        $response = $this->get('/git/acme/tools.git/info/refs?service=git-upload-pack');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-git-upload-pack-advertisement');
    });

    it('respects token clone repo scoping — denies access', function () {
        $allowedRepo = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/allowed',
        ]);
        $deniedRepo = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/denied',
        ]);

        $token = Token::factory()->create(['token' => 'test-clone-token']);
        $token->cloneRepositories()->attach($allowedRepo);

        Http::fake([
            'https://github.com/acme/denied.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->withHeader('Authorization', 'Bearer test-clone-token')
            ->get('/git/acme/denied.git/info/refs?service=git-upload-pack')
            ->assertForbidden();
    });

    it('respects token clone repo scoping — allows access', function () {
        $allowedRepo = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/allowed',
        ]);

        $token = Token::factory()->create(['token' => 'test-clone-token']);
        $token->cloneRepositories()->attach($allowedRepo);

        Http::fake([
            'https://github.com/acme/allowed.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->withHeader('Authorization', 'Bearer test-clone-token')
            ->get('/git/acme/allowed.git/info/refs?service=git-upload-pack')
            ->assertOk();
    });
});

// =============================================================================
// UPLOAD-PACK ENDPOINT TESTS
// =============================================================================

describe('Git upload-pack endpoint', function () {
    it('returns 200 with correct content-type', function () {
        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
            'clone_count' => 0,
        ]);

        Http::fake([
            'https://github.com/acme/tools.git/git-upload-pack' => Http::response('mock-pack-data', 200),
        ]);

        $response = $this->post('/git/acme/tools.git/git-upload-pack', [], [
            'Content-Type' => 'application/x-git-upload-pack-request',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-git-upload-pack-result');
    });

    it('increments clone_count on repository', function () {
        $repository = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
            'clone_count' => 5,
        ]);

        Http::fake([
            'https://github.com/acme/tools.git/git-upload-pack' => Http::response('mock-pack-data', 200),
        ]);

        $this->post('/git/acme/tools.git/git-upload-pack');

        $repository->refresh();
        expect($repository->clone_count)->toBe(6);
    });

    it('creates DownloadLog with PackageFormat::Git', function () {
        $repository = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
        ]);

        $token = Token::factory()->create(['token' => 'test-git-token']);

        Http::fake([
            'https://github.com/acme/tools.git/git-upload-pack' => Http::response('mock-pack-data', 200),
        ]);

        $this->withHeader('Authorization', 'Bearer test-git-token')
            ->post('/git/acme/tools.git/git-upload-pack');

        $log = DownloadLog::first();
        expect($log)->not->toBeNull()
            ->and($log->repository_id)->toBe($repository->id)
            ->and($log->token_id)->toBe($token->id)
            ->and($log->package_version)->toBe('clone')
            ->and($log->format)->toBe(PackageFormat::Git);
    });

    it('creates DownloadLog without token when no tokens exist', function () {
        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
        ]);

        Http::fake([
            'https://github.com/acme/tools.git/git-upload-pack' => Http::response('mock-pack-data', 200),
        ]);

        $this->post('/git/acme/tools.git/git-upload-pack');

        $log = DownloadLog::first();
        expect($log)->not->toBeNull()
            ->and($log->token_id)->toBeNull()
            ->and($log->format)->toBe(PackageFormat::Git);
    });
});

// =============================================================================
// FEATURE TOGGLE TESTS
// =============================================================================

describe('Git feature toggle', function () {
    it('returns 503 when git feature is disabled', function () {
        Setting::query()->update(['git_enabled' => false]);
        PackgridSettings::clearCache();

        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
        ]);

        $this->get('/git/acme/tools.git/info/refs?service=git-upload-pack')
            ->assertStatus(503);
    });

    it('returns 503 for upload-pack when git feature is disabled', function () {
        Setting::query()->update(['git_enabled' => false]);
        PackgridSettings::clearCache();

        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/tools',
        ]);

        $this->post('/git/acme/tools.git/git-upload-pack')
            ->assertStatus(503);
    });
});

// =============================================================================
// TOKEN CLONE SCOPING TESTS
// =============================================================================

describe('Token clone repository scoping', function () {
    it('allows clone of any repo when token has no clone restrictions', function () {
        $repository = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/anything',
        ]);

        $token = Token::factory()->create(['token' => 'unrestricted-token']);
        // No cloneRepositories attached = unrestricted

        Http::fake([
            'https://github.com/acme/anything.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->withHeader('Authorization', 'Bearer unrestricted-token')
            ->get('/git/acme/anything.git/info/refs?service=git-upload-pack')
            ->assertOk();
    });

    it('denies clone of repos not in the token clone scope', function () {
        $scopedRepo = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/scoped',
        ]);
        $otherRepo = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/other',
        ]);

        $token = Token::factory()->create(['token' => 'scoped-token']);
        $token->cloneRepositories()->attach($scopedRepo);

        Http::fake([
            'https://github.com/acme/other.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->withHeader('Authorization', 'Bearer scoped-token')
            ->get('/git/acme/other.git/info/refs?service=git-upload-pack')
            ->assertForbidden();
    });

    it('allows clone of repos in the token clone scope', function () {
        $scopedRepo = Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/scoped',
        ]);

        $token = Token::factory()->create(['token' => 'scoped-token']);
        $token->cloneRepositories()->attach($scopedRepo);

        Http::fake([
            'https://github.com/acme/scoped.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->withHeader('Authorization', 'Bearer scoped-token')
            ->get('/git/acme/scoped.git/info/refs?service=git-upload-pack')
            ->assertOk();
    });
});

// =============================================================================
// GITHUB AUTH HEADER TESTS
// =============================================================================

describe('GitHub authentication', function () {
    it('sends Bearer scheme for github_pat_ tokens', function () {
        $credential = Credential::factory()->create(['token' => 'github_pat_abc123']);
        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/private',
            'credential_id' => $credential->id,
        ]);

        Http::fake([
            'https://github.com/acme/private.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->get('/git/acme/private.git/info/refs?service=git-upload-pack')
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer github_pat_');
        });
    });

    it('sends token scheme for classic tokens', function () {
        $credential = Credential::factory()->create(['token' => 'ghp_classic123']);
        Repository::factory()->cloneEnabled()->create([
            'repo_full_name' => 'acme/classic',
            'credential_id' => $credential->id,
        ]);

        Http::fake([
            'https://github.com/acme/classic.git/info/refs*' => Http::response('mock-refs', 200),
        ]);

        $this->get('/git/acme/classic.git/info/refs?service=git-upload-pack')
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_starts_with($request->header('Authorization')[0] ?? '', 'token ghp_');
        });
    });
});
