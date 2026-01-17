<?php

use App\Enums\DockerMediaType;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use App\Models\Token;
use App\Models\User;
use App\Services\Docker\BlobStorageService;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\withBasicAuth;

beforeEach(function () {
    Storage::fake('local');
    User::factory()->create();
    // Token must be at least 20 characters for DockerRegistryAuth middleware
    $this->token = Token::factory()->create(['token' => 'test-docker-token-12345']);
});

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function dockerAuth()
{
    return withBasicAuth('token', 'test-docker-token-12345');
}

function dockerAuthHeaders(): array
{
    return [
        'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('token:test-docker-token-12345'),
    ];
}

function createTestManifestContent(): string
{
    return json_encode([
        'schemaVersion' => 2,
        'mediaType' => DockerMediaType::ManifestV2->value,
        'config' => [
            'mediaType' => DockerMediaType::ContainerConfig->value,
            'digest' => 'sha256:'.fake()->sha256(),
            'size' => 1024,
        ],
        'layers' => [
            [
                'mediaType' => DockerMediaType::LayerTarGzip->value,
                'digest' => 'sha256:'.fake()->sha256(),
                'size' => 10240,
            ],
        ],
    ]);
}

// =============================================================================
// VERSION ENDPOINT TESTS
// =============================================================================

describe('Docker Version Endpoint', function () {
    it('returns 200 OK for version check', function () {
        $this->get('/v2/')
            ->assertOk()
            ->assertHeader('Docker-Distribution-Api-Version', 'registry/2.0');
    });

    it('version endpoint does not require authentication', function () {
        $this->get('/v2/')
            ->assertOk();
    });
});

// =============================================================================
// CATALOG ENDPOINT TESTS
// =============================================================================

describe('Docker Catalog Endpoint', function () {
    it('lists all repositories', function () {
        DockerRepository::factory()->create(['name' => 'myorg/app1']);
        DockerRepository::factory()->create(['name' => 'myorg/app2']);

        dockerAuth()
            ->get('/v2/_catalog')
            ->assertOk()
            ->assertJsonStructure(['repositories'])
            ->assertJsonCount(2, 'repositories')
            ->assertJsonPath('repositories.0', 'myorg/app1')
            ->assertJsonPath('repositories.1', 'myorg/app2');
    });

    it('requires authentication', function () {
        DockerRepository::factory()->create(['name' => 'myorg/app']);

        $this->get('/v2/_catalog')
            ->assertUnauthorized();
    });

    it('supports pagination with n parameter', function () {
        for ($i = 1; $i <= 5; $i++) {
            DockerRepository::factory()->create(['name' => "repo-{$i}"]);
        }

        dockerAuth()
            ->get('/v2/_catalog?n=2')
            ->assertOk()
            ->assertJsonCount(2, 'repositories');
    });
});

// =============================================================================
// TAGS ENDPOINT TESTS
// =============================================================================

describe('Docker Tags Endpoint', function () {
    it('lists tags for repository', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $manifest = DockerManifest::factory()->forRepository($repository)->create();
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('v1.0.0')->create();
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        dockerAuth()
            ->get('/v2/myorg/myapp/tags/list')
            ->assertOk()
            ->assertJsonStructure(['name', 'tags'])
            ->assertJsonFragment(['name' => 'myorg/myapp'])
            ->assertJsonFragment(['tags' => ['latest', 'v1.0.0']]);
    });

    it('returns 404 for non-existent repository', function () {
        dockerAuth()
            ->get('/v2/nonexistent/repo/tags/list')
            ->assertNotFound();
    });

    it('requires authentication', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        $this->get('/v2/myorg/myapp/tags/list')
            ->assertUnauthorized();
    });
});

// =============================================================================
// MANIFEST ENDPOINT TESTS
// =============================================================================

describe('Docker Manifest GET', function () {
    it('gets manifest by tag', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = createTestManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);

        $manifest = DockerManifest::factory()->forRepository($repository)->create([
            'digest' => $digest,
            'content' => $content,
            'media_type' => DockerMediaType::ManifestV2,
        ]);
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        dockerAuth()
            ->get('/v2/myorg/myapp/manifests/latest')
            ->assertOk()
            ->assertHeader('Content-Type', DockerMediaType::ManifestV2->value)
            ->assertHeader('Docker-Content-Digest', $digest);
    });

    it('gets manifest by digest', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = createTestManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);

        DockerManifest::factory()->forRepository($repository)->create([
            'digest' => $digest,
            'content' => $content,
            'media_type' => DockerMediaType::ManifestV2,
        ]);

        dockerAuth()
            ->get("/v2/myorg/myapp/manifests/{$digest}")
            ->assertOk()
            ->assertHeader('Docker-Content-Digest', $digest);
    });

    it('returns 404 for non-existent manifest', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        dockerAuth()
            ->get('/v2/myorg/myapp/manifests/nonexistent')
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'MANIFEST_UNKNOWN');
    });

    it('returns 404 for non-existent repository', function () {
        dockerAuth()
            ->get('/v2/nonexistent/repo/manifests/latest')
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'NAME_UNKNOWN');
    });
});

describe('Docker Manifest HEAD', function () {
    it('checks manifest exists by tag', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = createTestManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);

        $manifest = DockerManifest::factory()->forRepository($repository)->create([
            'digest' => $digest,
            'content' => $content,
            'media_type' => DockerMediaType::ManifestV2,
        ]);
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        dockerAuth()
            ->head('/v2/myorg/myapp/manifests/latest')
            ->assertOk()
            ->assertHeader('Docker-Content-Digest', $digest);
    });

    it('returns 404 for non-existent manifest', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        dockerAuth()
            ->head('/v2/myorg/myapp/manifests/nonexistent')
            ->assertNotFound();
    });
});

describe('Docker Manifest PUT', function () {
    it('uploads manifest with tag', function () {
        $content = createTestManifestContent();

        test()->call('PUT', '/v2/myorg/newapp/manifests/v1.0.0', [], [], [], array_merge([
            'CONTENT_TYPE' => DockerMediaType::ManifestV2->value,
        ], dockerAuthHeaders()), $content)
            ->assertStatus(201)
            ->assertHeader('Docker-Content-Digest');

        expect(DockerRepository::where('name', 'myorg/newapp')->exists())->toBeTrue()
            ->and(DockerTag::where('name', 'v1.0.0')->exists())->toBeTrue();
    });

    it('creates repository on first push', function () {
        $content = createTestManifestContent();

        test()->call('PUT', '/v2/newrepo/manifests/latest', [], [], [], array_merge([
            'CONTENT_TYPE' => DockerMediaType::ManifestV2->value,
        ], dockerAuthHeaders()), $content)
            ->assertStatus(201);

        expect(DockerRepository::where('name', 'newrepo')->exists())->toBeTrue();
    });

    it('rejects push to disabled repository', function () {
        DockerRepository::factory()->disabled()->create(['name' => 'myorg/disabled']);
        $content = createTestManifestContent();

        test()->call('PUT', '/v2/myorg/disabled/manifests/latest', [], [], [], array_merge([
            'CONTENT_TYPE' => DockerMediaType::ManifestV2->value,
        ], dockerAuthHeaders()), $content)
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'DENIED');
    });

    it('rejects invalid manifest content', function () {
        test()->call('PUT', '/v2/myorg/myapp/manifests/latest', [], [], [], array_merge([
            'CONTENT_TYPE' => DockerMediaType::ManifestV2->value,
        ], dockerAuthHeaders()), 'not valid json')
            ->assertBadRequest()
            ->assertJsonPath('errors.0.code', 'MANIFEST_INVALID');
    });
});

describe('Docker Manifest DELETE', function () {
    it('deletes manifest by digest', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = createTestManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);

        $manifest = DockerManifest::factory()->forRepository($repository)->create([
            'digest' => $digest,
            'content' => $content,
        ]);
        DockerTag::factory()->forRepository($repository)->forManifest($manifest)->named('latest')->create();

        dockerAuth()
            ->delete("/v2/myorg/myapp/manifests/{$digest}")
            ->assertStatus(202);

        expect(DockerManifest::where('digest', $digest)->exists())->toBeFalse();
    });

    it('returns 404 for non-existent manifest', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $nonExistentDigest = 'sha256:'.str_repeat('a', 64);

        dockerAuth()
            ->delete("/v2/myorg/myapp/manifests/{$nonExistentDigest}")
            ->assertNotFound();
    });
});

// =============================================================================
// BLOB ENDPOINT TESTS
// =============================================================================

describe('Docker Blob GET', function () {
    it('downloads blob by digest', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = 'Blob content for download';
        $digest = 'sha256:'.hash('sha256', $content);

        $blobService = app(BlobStorageService::class);
        $blob = $blobService->storeBlob($digest, $content);
        $blobService->linkBlobToRepository($blob, $repository);

        dockerAuth()
            ->get("/v2/myorg/myapp/blobs/{$digest}")
            ->assertOk()
            ->assertHeader('Docker-Content-Digest', $digest);
    });

    it('returns 404 for non-existent blob', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $nonExistentDigest = 'sha256:'.str_repeat('a', 64);

        dockerAuth()
            ->get("/v2/myorg/myapp/blobs/{$nonExistentDigest}")
            ->assertNotFound();
    });
});

describe('Docker Blob HEAD', function () {
    it('checks blob exists', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = 'Blob content to check';
        $digest = 'sha256:'.hash('sha256', $content);

        $blobService = app(BlobStorageService::class);
        $blob = $blobService->storeBlob($digest, $content);
        $blobService->linkBlobToRepository($blob, $repository);

        dockerAuth()
            ->head("/v2/myorg/myapp/blobs/{$digest}")
            ->assertOk()
            ->assertHeader('Docker-Content-Digest', $digest)
            ->assertHeader('Content-Length', strlen($content));
    });
});

// =============================================================================
// BLOB UPLOAD ENDPOINT TESTS
// =============================================================================

describe('Docker Blob Upload', function () {
    it('starts blob upload', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        dockerAuth()
            ->post('/v2/myorg/myapp/blobs/uploads')
            ->assertStatus(202)
            ->assertHeader('Location')
            ->assertHeader('Docker-Upload-UUID');
    });

    it('creates repository on upload start', function () {
        dockerAuth()
            ->post('/v2/newrepo/blobs/uploads')
            ->assertStatus(202);

        expect(DockerRepository::where('name', 'newrepo')->exists())->toBeTrue();
    });

    it('supports cross-repository mount', function () {
        $sourceRepo = DockerRepository::factory()->create(['name' => 'source/repo']);
        $content = 'Mountable blob content';
        $digest = 'sha256:'.hash('sha256', $content);

        $blobService = app(BlobStorageService::class);
        $blob = $blobService->storeBlob($digest, $content);
        $blobService->linkBlobToRepository($blob, $sourceRepo);

        dockerAuth()
            ->post("/v2/target/repo/blobs/uploads?mount={$digest}&from=source/repo")
            ->assertStatus(201)
            ->assertHeader('Docker-Content-Digest', $digest);

        expect(DockerRepository::where('name', 'target/repo')->exists())->toBeTrue();
    });

    it('falls back to upload when mount source not found', function () {
        $nonExistentDigest = 'sha256:'.str_repeat('a', 64);

        dockerAuth()
            ->post("/v2/myorg/myapp/blobs/uploads?mount={$nonExistentDigest}&from=other/repo")
            ->assertStatus(202)
            ->assertHeader('Docker-Upload-UUID');
    });
});

describe('Docker Blob Upload Completion', function () {
    it('completes single-request upload', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $content = 'Single upload content';
        $digest = 'sha256:'.hash('sha256', $content);

        // Start upload
        $startResponse = dockerAuth()
            ->post('/v2/myorg/myapp/blobs/uploads')
            ->assertStatus(202);

        $uploadUuid = $startResponse->headers->get('Docker-Upload-UUID');

        // Complete upload with content
        test()->call('PUT', "/v2/myorg/myapp/blobs/uploads/{$uploadUuid}?digest={$digest}", [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/octet-stream',
        ], dockerAuthHeaders()), $content)
            ->assertStatus(201)
            ->assertHeader('Docker-Content-Digest', $digest);
    });
});

// =============================================================================
// AUTHENTICATION TESTS
// =============================================================================

describe('Docker Authentication', function () {
    it('rejects requests without authentication', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        $this->get('/v2/_catalog')
            ->assertUnauthorized();
    });

    it('accepts valid token via basic auth', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        dockerAuth()
            ->get('/v2/_catalog')
            ->assertOk();
    });

    it('rejects invalid token', function () {
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        withBasicAuth('token', 'invalid-token')
            ->get('/v2/_catalog')
            ->assertUnauthorized();
    });

    it('rejects disabled token', function () {
        $this->token->forceFill(['enabled' => false])->save();
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        dockerAuth()
            ->get('/v2/_catalog')
            ->assertUnauthorized();
    });

    it('rejects expired token', function () {
        $this->token->forceFill(['expires_at' => now()->subDay()])->save();
        DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        dockerAuth()
            ->get('/v2/_catalog')
            ->assertUnauthorized();
    });
});

// =============================================================================
// ERROR RESPONSE FORMAT TESTS
// =============================================================================

describe('Docker Error Responses', function () {
    it('returns errors in OCI format', function () {
        dockerAuth()
            ->get('/v2/nonexistent/repo/manifests/latest')
            ->assertNotFound()
            ->assertJsonStructure([
                'errors' => [
                    ['code', 'message', 'detail'],
                ],
            ]);
    });

    it('includes Docker-Distribution-Api-Version header on errors', function () {
        dockerAuth()
            ->get('/v2/nonexistent/repo/manifests/latest')
            ->assertHeader('Docker-Distribution-Api-Version', 'registry/2.0');
    });
});
