<?php

use App\Enums\PackageFormat;
use App\Models\Repository;
use App\Models\Token;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// =============================================================================
// MODEL TESTS
// =============================================================================

describe('Token::isAllowedForRepository', function () {
    it('returns true when token has no scoped repositories (full access)', function () {
        $token = Token::factory()->create();
        $repository = Repository::factory()->create();

        expect($token->isAllowedForRepository($repository))->toBeTrue();
    });

    it('returns true when repository is in scope', function () {
        $token = Token::factory()->create();
        $repository = Repository::factory()->create();

        $token->repositories()->attach($repository);

        expect($token->isAllowedForRepository($repository))->toBeTrue();
    });

    it('returns false when repository is NOT in scope', function () {
        $token = Token::factory()->create();
        $allowed = Repository::factory()->create();
        $denied = Repository::factory()->create();

        $token->repositories()->attach($allowed);

        expect($token->isAllowedForRepository($denied))->toBeFalse();
    });
});

describe('Pivot Cascade Deletes', function () {
    it('removes pivot row when repository is deleted', function () {
        $token = Token::factory()->create();
        $repository = Repository::factory()->create();

        $token->repositories()->attach($repository);
        expect(DB::table('repository_token')->count())->toBe(1);

        $repository->delete();
        expect(DB::table('repository_token')->count())->toBe(0);
    });

    it('removes pivot row when token is deleted', function () {
        $token = Token::factory()->create();
        $repository = Repository::factory()->create();

        $token->repositories()->attach($repository);
        expect(DB::table('repository_token')->count())->toBe(1);

        $token->delete();
        expect(DB::table('repository_token')->count())->toBe(0);
    });
});

describe('Token::repositories relationship', function () {
    it('returns correct repositories', function () {
        $token = Token::factory()->create();
        $repo1 = Repository::factory()->create();
        $repo2 = Repository::factory()->create();
        $repo3 = Repository::factory()->create();

        $token->repositories()->attach([$repo1->id, $repo2->id]);

        $scoped = $token->repositories()->pluck('repositories.id')->all();

        expect($scoped)->toContain($repo1->id)
            ->toContain($repo2->id)
            ->not->toContain($repo3->id);
    });
});

// =============================================================================
// COMPOSER DOWNLOAD ENFORCEMENT TESTS
// =============================================================================

describe('Composer Download Scoping', function () {
    it('allows scoped token to download from allowed repository', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/allowed-pkg',
            'format' => PackageFormat::Composer,
            'last_sync_at' => now(),
        ]);

        $token = Token::factory()->create(['token' => 'scoped-composer-token']);
        $token->repositories()->attach($repository);

        Http::fake([
            'https://api.github.com/repos/acme/allowed-pkg/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->withBasicAuth('user', 'scoped-composer-token')
            ->get('/dist/acme/allowed-pkg/v1.0.0.zip')
            ->assertOk();
    });

    it('rejects scoped token from non-allowed repository', function () {
        $allowed = Repository::factory()->create([
            'repo_full_name' => 'acme/allowed-only',
            'format' => PackageFormat::Composer,
            'last_sync_at' => now(),
        ]);

        Repository::factory()->create([
            'repo_full_name' => 'acme/forbidden-pkg',
            'format' => PackageFormat::Composer,
            'last_sync_at' => now(),
        ]);

        $token = Token::factory()->create(['token' => 'scoped-deny-token']);
        $token->repositories()->attach($allowed);

        $this->withBasicAuth('user', 'scoped-deny-token')
            ->get('/dist/acme/forbidden-pkg/v1.0.0.zip')
            ->assertForbidden();
    });

    it('allows unscoped token to download from any repository', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/any-pkg',
            'format' => PackageFormat::Composer,
            'last_sync_at' => now(),
        ]);

        Token::factory()->create(['token' => 'unscoped-token']);

        Http::fake([
            'https://api.github.com/repos/acme/any-pkg/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->withBasicAuth('user', 'unscoped-token')
            ->get('/dist/acme/any-pkg/v1.0.0.zip')
            ->assertOk();
    });

    it('allows public access when no tokens exist', function () {
        $repository = Repository::factory()->create([
            'repo_full_name' => 'acme/public-pkg',
            'format' => PackageFormat::Composer,
            'last_sync_at' => now(),
        ]);

        Http::fake([
            'https://api.github.com/repos/acme/public-pkg/zipball/v1.0.0' => Http::response('zip-content', 200),
        ]);

        $this->get('/dist/acme/public-pkg/v1.0.0.zip')
            ->assertOk();
    });
});

// =============================================================================
// NPM DOWNLOAD ENFORCEMENT TESTS
// =============================================================================

describe('NPM Download Scoping', function () {
    it('allows scoped token to download allowed npm package', function () {
        $repository = Repository::factory()->npm()->create([
            'repo_full_name' => 'acme/npm-allowed',
            'last_sync_at' => now(),
        ]);

        $token = Token::factory()->create(['token' => 'npm-scoped-token']);
        $token->repositories()->attach($repository);

        Http::fake([
            'https://api.github.com/repos/acme/npm-allowed/tarball/v1.0.0' => Http::response('tgz-content', 200),
        ]);

        $this->withToken('npm-scoped-token')
            ->get('/npm/-/acme/npm-allowed/v1.0.0.tgz')
            ->assertOk();
    });

    it('rejects scoped token from non-allowed npm package', function () {
        $allowed = Repository::factory()->npm()->create([
            'repo_full_name' => 'acme/npm-yes',
            'last_sync_at' => now(),
        ]);

        Repository::factory()->npm()->create([
            'repo_full_name' => 'acme/npm-no',
            'last_sync_at' => now(),
        ]);

        $token = Token::factory()->create(['token' => 'npm-deny-token']);
        $token->repositories()->attach($allowed);

        $this->withToken('npm-deny-token')
            ->get('/npm/-/acme/npm-no/v1.0.0.tgz')
            ->assertForbidden();
    });
});
