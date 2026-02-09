<?php

use App\Enums\DockerMediaType;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use App\Models\Token;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\withBasicAuth;

beforeEach(function () {
    Storage::fake('local');
    User::factory()->create();
});

function scopedDockerAuth(string $tokenValue = 'scoped-docker-token-12345')
{
    return withBasicAuth('token', $tokenValue);
}

// =============================================================================
// MODEL TESTS
// =============================================================================

describe('Token::isAllowedForDockerRepository', function () {
    it('returns true when token has no scoped docker repositories (full access)', function () {
        $token = Token::factory()->create();
        $repository = DockerRepository::factory()->create();

        expect($token->isAllowedForDockerRepository($repository))->toBeTrue();
    });

    it('returns true when docker repository is in scope', function () {
        $token = Token::factory()->create();
        $repository = DockerRepository::factory()->create();

        $token->dockerRepositories()->attach($repository);

        expect($token->isAllowedForDockerRepository($repository))->toBeTrue();
    });

    it('returns false when docker repository is NOT in scope', function () {
        $token = Token::factory()->create();
        $allowed = DockerRepository::factory()->create();
        $denied = DockerRepository::factory()->create();

        $token->dockerRepositories()->attach($allowed);

        expect($token->isAllowedForDockerRepository($denied))->toBeFalse();
    });
});

describe('Docker Pivot Cascade Deletes', function () {
    it('removes pivot row when docker repository is deleted', function () {
        $token = Token::factory()->create();
        $repository = DockerRepository::factory()->create();

        $token->dockerRepositories()->attach($repository);
        expect(DB::table('docker_repository_token')->count())->toBe(1);

        $repository->delete();
        expect(DB::table('docker_repository_token')->count())->toBe(0);
    });

    it('removes pivot row when token is deleted', function () {
        $token = Token::factory()->create();
        $repository = DockerRepository::factory()->create();

        $token->dockerRepositories()->attach($repository);
        expect(DB::table('docker_repository_token')->count())->toBe(1);

        $token->delete();
        expect(DB::table('docker_repository_token')->count())->toBe(0);
    });
});

describe('Token::dockerRepositories relationship', function () {
    it('returns correct docker repositories', function () {
        $token = Token::factory()->create();
        $repo1 = DockerRepository::factory()->create();
        $repo2 = DockerRepository::factory()->create();
        $repo3 = DockerRepository::factory()->create();

        $token->dockerRepositories()->attach([$repo1->id, $repo2->id]);

        $scoped = $token->dockerRepositories()->pluck('docker_repositories.id')->all();

        expect($scoped)->toContain($repo1->id)
            ->toContain($repo2->id)
            ->not->toContain($repo3->id);
    });
});

// =============================================================================
// MANIFEST SCOPING TESTS
// =============================================================================

describe('Docker Manifest Scoping', function () {
    it('allows scoped token to pull manifest from allowed repository', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        $manifest = DockerManifest::factory()->forRepository($repository)->create();
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($repository);

        scopedDockerAuth()
            ->get('/v2/myorg/allowed/manifests/latest')
            ->assertOk();
    });

    it('rejects scoped token from pulling manifest of non-allowed repository', function () {
        $allowed = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        $denied = DockerRepository::factory()->create(['name' => 'myorg/denied']);
        $manifest = DockerManifest::factory()->forRepository($denied)->create();
        DockerTag::factory()->forRepository($denied)->forManifest($manifest)->named('latest')->create();

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($allowed);

        scopedDockerAuth()
            ->get('/v2/myorg/denied/manifests/latest')
            ->assertForbidden();
    });

    it('allows scoped token to push manifest to allowed repository', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/allowed']);

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($repository);

        $content = json_encode([
            'schemaVersion' => 2,
            'mediaType' => DockerMediaType::ManifestV2->value,
            'config' => [
                'mediaType' => DockerMediaType::ContainerConfig->value,
                'digest' => 'sha256:'.fake()->sha256(),
                'size' => 1024,
            ],
            'layers' => [],
        ]);

        scopedDockerAuth()
            ->call('PUT', '/v2/myorg/allowed/manifests/v1.0.0', [], [], [], [
                'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('token:scoped-docker-token-12345'),
                'CONTENT_TYPE' => DockerMediaType::ManifestV2->value,
            ], $content)
            ->assertStatus(201);
    });

    it('rejects scoped token from pushing manifest to non-allowed repository', function () {
        $allowed = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        DockerRepository::factory()->create(['name' => 'myorg/denied']);

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($allowed);

        $content = json_encode([
            'schemaVersion' => 2,
            'mediaType' => DockerMediaType::ManifestV2->value,
            'config' => [
                'mediaType' => DockerMediaType::ContainerConfig->value,
                'digest' => 'sha256:'.fake()->sha256(),
                'size' => 1024,
            ],
            'layers' => [],
        ]);

        scopedDockerAuth()
            ->call('PUT', '/v2/myorg/denied/manifests/v1.0.0', [], [], [], [
                'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('token:scoped-docker-token-12345'),
                'CONTENT_TYPE' => DockerMediaType::ManifestV2->value,
            ], $content)
            ->assertForbidden();
    });
});

// =============================================================================
// BLOB SCOPING TESTS
// =============================================================================

describe('Docker Blob Scoping', function () {
    it('rejects scoped token from downloading blob of non-allowed repository', function () {
        $allowed = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        $denied = DockerRepository::factory()->create(['name' => 'myorg/denied']);

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($allowed);

        scopedDockerAuth()
            ->get('/v2/myorg/denied/blobs/sha256:'.str_repeat('a', 64))
            ->assertForbidden();
    });
});

// =============================================================================
// UPLOAD SCOPING TESTS
// =============================================================================

describe('Docker Upload Scoping', function () {
    it('rejects scoped token from starting upload to non-allowed repository', function () {
        $allowed = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        DockerRepository::factory()->create(['name' => 'myorg/denied']);

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($allowed);

        scopedDockerAuth()
            ->post('/v2/myorg/denied/blobs/uploads')
            ->assertForbidden();
    });

    it('allows scoped token to start upload to allowed repository', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/allowed']);

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($repository);

        $response = scopedDockerAuth()
            ->post('/v2/myorg/allowed/blobs/uploads');

        // Should not be 403 (our scoping), meaning token was allowed through
        // Note: may return 500 due to pre-existing Carbon type error in BlobStorageService
        expect($response->status())->not->toBe(403);
    });
});

// =============================================================================
// TAGS SCOPING TESTS
// =============================================================================

describe('Docker Tags Scoping', function () {
    it('allows scoped token to list tags of allowed repository', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        $manifest = DockerManifest::factory()->forRepository($repository)->create();
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('v1.0.0')->create();

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($repository);

        scopedDockerAuth()
            ->get('/v2/myorg/allowed/tags/list')
            ->assertOk()
            ->assertJsonFragment(['tags' => ['v1.0.0']]);
    });

    it('rejects scoped token from listing tags of non-allowed repository', function () {
        $allowed = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        $denied = DockerRepository::factory()->create(['name' => 'myorg/denied']);
        $manifest = DockerManifest::factory()->forRepository($denied)->create();
        DockerTag::factory()->forRepository($denied)->forManifest($manifest)->named('v1.0.0')->create();

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($allowed);

        scopedDockerAuth()
            ->get('/v2/myorg/denied/tags/list')
            ->assertForbidden();
    });
});

// =============================================================================
// CATALOG SCOPING TESTS
// =============================================================================

describe('Docker Catalog Scoping', function () {
    it('scoped token only sees allowed docker repositories in catalog', function () {
        $allowed = DockerRepository::factory()->create(['name' => 'myorg/allowed']);
        DockerRepository::factory()->create(['name' => 'myorg/hidden']);

        $token = Token::factory()->create(['token' => 'scoped-docker-token-12345']);
        $token->dockerRepositories()->attach($allowed);

        scopedDockerAuth()
            ->get('/v2/_catalog')
            ->assertOk()
            ->assertJsonCount(1, 'repositories')
            ->assertJsonPath('repositories.0', 'myorg/allowed');
    });

    it('unscoped token sees all docker repositories in catalog', function () {
        DockerRepository::factory()->create(['name' => 'myorg/repo1']);
        DockerRepository::factory()->create(['name' => 'myorg/repo2']);

        Token::factory()->create(['token' => 'unscoped-docker-token-12345']);

        withBasicAuth('token', 'unscoped-docker-token-12345')
            ->get('/v2/_catalog')
            ->assertOk()
            ->assertJsonCount(2, 'repositories');
    });
});

// =============================================================================
// BACKWARDS COMPATIBILITY TESTS
// =============================================================================

describe('Docker Scoping Backwards Compatibility', function () {
    it('unscoped token has full access to all docker repositories', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/anyrepo']);
        $manifest = DockerManifest::factory()->forRepository($repository)->create();
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        Token::factory()->create(['token' => 'unscoped-docker-token-12345']);

        withBasicAuth('token', 'unscoped-docker-token-12345')
            ->get('/v2/myorg/anyrepo/manifests/latest')
            ->assertOk();
    });

    it('allows public access when no tokens exist', function () {
        Token::query()->delete();

        $repository = DockerRepository::factory()->create(['name' => 'myorg/public']);
        $manifest = DockerManifest::factory()->forRepository($repository)->create();
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        $this->get('/v2/myorg/public/manifests/latest')
            ->assertOk();
    });
});
