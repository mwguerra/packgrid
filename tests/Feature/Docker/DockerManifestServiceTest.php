<?php

use App\Enums\DockerMediaType;
use App\Models\DockerActivity;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use App\Services\Docker\BlobStorageService;
use App\Services\Docker\ManifestService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->manifestService = app(ManifestService::class);
    $this->blobService = app(BlobStorageService::class);
    $this->repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
});

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function createManifestContent(array $layers = [], ?string $configDigest = null): string
{
    $configDigest = $configDigest ?? 'sha256:'.fake()->sha256();
    $layerDigests = $layers ?: ['sha256:'.fake()->sha256()];

    return json_encode([
        'schemaVersion' => 2,
        'mediaType' => DockerMediaType::ManifestV2->value,
        'config' => [
            'mediaType' => DockerMediaType::ContainerConfig->value,
            'digest' => $configDigest,
            'size' => 1024,
        ],
        'layers' => array_map(fn ($digest) => [
            'mediaType' => DockerMediaType::LayerTarGzip->value,
            'digest' => $digest,
            'size' => 10240,
        ], $layerDigests),
    ]);
}

function createManifestListContent(): string
{
    return json_encode([
        'schemaVersion' => 2,
        'mediaType' => DockerMediaType::ManifestList->value,
        'manifests' => [
            [
                'mediaType' => DockerMediaType::ManifestV2->value,
                'digest' => 'sha256:'.fake()->sha256(),
                'size' => 1024,
                'platform' => [
                    'architecture' => 'amd64',
                    'os' => 'linux',
                ],
            ],
            [
                'mediaType' => DockerMediaType::ManifestV2->value,
                'digest' => 'sha256:'.fake()->sha256(),
                'size' => 1024,
                'platform' => [
                    'architecture' => 'arm64',
                    'os' => 'linux',
                ],
            ],
        ],
    ]);
}

// =============================================================================
// MANIFEST STORAGE TESTS
// =============================================================================

describe('ManifestService Store', function () {
    it('stores a manifest with tag reference', function () {
        $content = createManifestContent();
        $expectedDigest = 'sha256:'.hash('sha256', $content);

        $manifest = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($manifest)->toBeInstanceOf(DockerManifest::class)
            ->and($manifest->digest)->toBe($expectedDigest)
            ->and($manifest->media_type)->toBe(DockerMediaType::ManifestV2)
            ->and($manifest->content)->toBe($content);

        // Tag should be created
        expect(DockerTag::where('name', 'latest')->exists())->toBeTrue();
    });

    it('stores a manifest with digest reference', function () {
        $content = createManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);

        $manifest = $this->manifestService->storeManifest(
            $this->repository,
            $digest,
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($manifest->digest)->toBe($digest);

        // No tag should be created for digest reference
        expect(DockerTag::count())->toBe(0);
    });

    it('updates existing tag to point to new manifest', function () {
        $content1 = createManifestContent(['sha256:'.fake()->sha256()]);
        $content2 = createManifestContent(['sha256:'.fake()->sha256()]);

        $manifest1 = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content1,
            DockerMediaType::ManifestV2->value
        );

        $manifest2 = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        $tag = DockerTag::where('name', 'latest')->first();

        expect($tag->docker_manifest_id)->toBe($manifest2->id)
            ->and($manifest1->id)->not->toBe($manifest2->id);
    });

    it('rejects invalid JSON content', function () {
        expect(fn () => $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            'not valid json',
            DockerMediaType::ManifestV2->value
        ))->toThrow(RuntimeException::class, 'not valid JSON');
    });

    it('parses layer digests from manifest', function () {
        $layerDigest = 'sha256:'.fake()->sha256();
        $content = createManifestContent([$layerDigest]);

        $manifest = $this->manifestService->storeManifest(
            $this->repository,
            'v1.0.0',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($manifest->layer_digests)->toContain($layerDigest);
    });

    it('parses config digest from manifest', function () {
        $configDigest = 'sha256:'.fake()->sha256();
        $content = createManifestContent([], $configDigest);

        $manifest = $this->manifestService->storeManifest(
            $this->repository,
            'v1.0.0',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($manifest->config_digest)->toBe($configDigest);
    });

    it('logs push activity', function () {
        $content = createManifestContent();

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect(DockerActivity::where('type', 'push')->count())->toBe(1);
    });

    it('increments repository push count', function () {
        $content = createManifestContent();

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $this->repository->refresh();
        expect($this->repository->push_count)->toBe(1);
    });
});

// =============================================================================
// MANIFEST RETRIEVAL TESTS
// =============================================================================

describe('ManifestService Retrieve', function () {
    it('gets manifest by tag reference', function () {
        $content = createManifestContent();
        $storedManifest = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $manifest = $this->manifestService->getManifest($this->repository, 'latest');

        expect($manifest)->not->toBeNull()
            ->and($manifest->id)->toBe($storedManifest->id);
    });

    it('gets manifest by digest reference', function () {
        $content = createManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);
        $storedManifest = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $manifest = $this->manifestService->getManifest($this->repository, $digest);

        expect($manifest)->not->toBeNull()
            ->and($manifest->id)->toBe($storedManifest->id);
    });

    it('returns null for non-existent tag', function () {
        $manifest = $this->manifestService->getManifest($this->repository, 'nonexistent');

        expect($manifest)->toBeNull();
    });

    it('returns null for non-existent digest', function () {
        $manifest = $this->manifestService->getManifest(
            $this->repository,
            'sha256:'.str_repeat('a', 64)
        );

        expect($manifest)->toBeNull();
    });

    it('gets manifest by digest globally', function () {
        $content = createManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);
        $storedManifest = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $manifest = $this->manifestService->getManifestByDigest($digest);

        expect($manifest)->not->toBeNull()
            ->and($manifest->id)->toBe($storedManifest->id);
    });
});

// =============================================================================
// MANIFEST DELETION TESTS
// =============================================================================

describe('ManifestService Delete', function () {
    it('deletes manifest by digest', function () {
        $content = createManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $result = $this->manifestService->deleteManifest($this->repository, $digest);

        expect($result)->toBeTrue()
            ->and(DockerManifest::where('digest', $digest)->exists())->toBeFalse()
            ->and(DockerTag::where('name', 'latest')->exists())->toBeFalse();
    });

    it('returns false for non-existent manifest', function () {
        $result = $this->manifestService->deleteManifest(
            $this->repository,
            'sha256:'.str_repeat('a', 64)
        );

        expect($result)->toBeFalse();
    });

    it('logs delete activity', function () {
        $content = createManifestContent();
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $this->manifestService->deleteManifest($this->repository, 'sha256:'.hash('sha256', $content));

        expect(DockerActivity::where('type', 'delete')->count())->toBe(1);
    });
});

// =============================================================================
// TAG OPERATIONS TESTS
// =============================================================================

describe('ManifestService Tags', function () {
    it('lists tags for repository', function () {
        $content1 = createManifestContent(['sha256:'.fake()->sha256()]);
        $content2 = createManifestContent(['sha256:'.fake()->sha256()]);

        $this->manifestService->storeManifest(
            $this->repository,
            'v1.0.0',
            $content1,
            DockerMediaType::ManifestV2->value
        );
        $this->manifestService->storeManifest(
            $this->repository,
            'v2.0.0',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        $tags = $this->manifestService->listTags($this->repository);

        expect($tags)->toContain('v1.0.0')
            ->and($tags)->toContain('v2.0.0');
    });

    it('paginates tags with limit', function () {
        for ($i = 1; $i <= 5; $i++) {
            $content = createManifestContent(['sha256:'.fake()->sha256()]);
            $this->manifestService->storeManifest(
                $this->repository,
                "v{$i}.0.0",
                $content,
                DockerMediaType::ManifestV2->value
            );
        }

        $tags = $this->manifestService->listTags($this->repository, 2);

        expect($tags)->toHaveCount(2);
    });

    it('paginates tags with last marker', function () {
        for ($i = 1; $i <= 5; $i++) {
            $content = createManifestContent(['sha256:'.fake()->sha256()]);
            $this->manifestService->storeManifest(
                $this->repository,
                "v{$i}.0.0",
                $content,
                DockerMediaType::ManifestV2->value
            );
        }

        $tags = $this->manifestService->listTags($this->repository, 2, 'v2.0.0');

        expect($tags)->toHaveCount(2)
            ->and($tags[0])->toBe('v3.0.0');
    });

    it('checks tag exists', function () {
        $content = createManifestContent();
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($this->manifestService->tagExists($this->repository, 'latest'))->toBeTrue()
            ->and($this->manifestService->tagExists($this->repository, 'nonexistent'))->toBeFalse();
    });

    it('deletes tag without deleting manifest if other tags exist', function () {
        $content = createManifestContent();

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );
        $this->manifestService->storeManifest(
            $this->repository,
            'stable',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $digest = 'sha256:'.hash('sha256', $content);

        $this->manifestService->deleteTag($this->repository, 'latest');

        expect(DockerTag::where('name', 'latest')->exists())->toBeFalse()
            ->and(DockerTag::where('name', 'stable')->exists())->toBeTrue()
            ->and(DockerManifest::where('digest', $digest)->exists())->toBeTrue();
    });

    it('deletes manifest when last tag is deleted', function () {
        $content = createManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        $this->manifestService->deleteTag($this->repository, 'latest');

        expect(DockerTag::where('name', 'latest')->exists())->toBeFalse()
            ->and(DockerManifest::where('digest', $digest)->exists())->toBeFalse();
    });
});

// =============================================================================
// MANIFEST EXISTS TESTS
// =============================================================================

describe('ManifestService Exists', function () {
    it('checks manifest exists by tag', function () {
        $content = createManifestContent();
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($this->manifestService->manifestExists($this->repository, 'latest'))->toBeTrue()
            ->and($this->manifestService->manifestExists($this->repository, 'nonexistent'))->toBeFalse();
    });

    it('checks manifest exists by digest', function () {
        $content = createManifestContent();
        $digest = 'sha256:'.hash('sha256', $content);
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestV2->value
        );

        expect($this->manifestService->manifestExists($this->repository, $digest))->toBeTrue()
            ->and($this->manifestService->manifestExists($this->repository, 'sha256:'.str_repeat('a', 64)))->toBeFalse();
    });
});

// =============================================================================
// TAG OVERWRITE / ORPHAN CLEANUP TESTS
// =============================================================================

describe('ManifestService Tag Overwrite Cleanup', function () {
    it('deletes the previously-tagged manifest when a tag is moved to a new manifest', function () {
        $content1 = createManifestContent(['sha256:'.fake()->sha256()]);
        $content2 = createManifestContent(['sha256:'.fake()->sha256()]);

        $manifest1 = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content1,
            DockerMediaType::ManifestV2->value
        );

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        expect(DockerManifest::find($manifest1->id))->toBeNull();
    });

    it('keeps the previously-tagged manifest if another tag still references it', function () {
        $content1 = createManifestContent(['sha256:'.fake()->sha256()]);
        $content2 = createManifestContent(['sha256:'.fake()->sha256()]);

        $manifest1 = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content1,
            DockerMediaType::ManifestV2->value
        );
        $this->manifestService->storeManifest(
            $this->repository,
            'stable',
            $content1,
            DockerMediaType::ManifestV2->value
        );

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        expect(DockerManifest::find($manifest1->id))->not->toBeNull();
    });

    it('unlinks blobs of the orphaned manifest from the repository', function () {
        $configDigest = 'sha256:'.hash('sha256', 'config-1');
        $layerDigest = 'sha256:'.hash('sha256', 'layer-1');

        $configBlob = $this->blobService->storeBlob($configDigest, 'config-1');
        $layerBlob = $this->blobService->storeBlob($layerDigest, 'layer-1');
        $this->blobService->linkBlobToRepository($configBlob, $this->repository);
        $this->blobService->linkBlobToRepository($layerBlob, $this->repository);

        $content1 = createManifestContent([$layerDigest], $configDigest);
        $content2 = createManifestContent(['sha256:'.fake()->sha256()]);

        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content1,
            DockerMediaType::ManifestV2->value
        );
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        $configBlob->refresh();
        $layerBlob->refresh();

        expect($this->repository->blobs()->where('docker_blob_id', $configBlob->id)->exists())->toBeFalse()
            ->and($this->repository->blobs()->where('docker_blob_id', $layerBlob->id)->exists())->toBeFalse()
            ->and($configBlob->reference_count)->toBe(0)
            ->and($layerBlob->reference_count)->toBe(0);
    });

    it('does not unlink blobs that are still reachable from a tagged manifest', function () {
        // Shared config + layer between two manifests in the same repo
        $sharedConfig = 'sha256:'.hash('sha256', 'shared-config');
        $sharedLayer = 'sha256:'.hash('sha256', 'shared-layer');

        $sharedConfigBlob = $this->blobService->storeBlob($sharedConfig, 'shared-config');
        $sharedLayerBlob = $this->blobService->storeBlob($sharedLayer, 'shared-layer');
        $this->blobService->linkBlobToRepository($sharedConfigBlob, $this->repository);
        $this->blobService->linkBlobToRepository($sharedLayerBlob, $this->repository);

        $content1 = createManifestContent([$sharedLayer], $sharedConfig);
        $content2 = createManifestContent(['sha256:'.fake()->sha256()]);

        // Push manifest1 as 'stable' (keeps it tagged), and as 'latest'
        $this->manifestService->storeManifest(
            $this->repository,
            'stable',
            $content1,
            DockerMediaType::ManifestV2->value
        );
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content1,
            DockerMediaType::ManifestV2->value
        );

        // Now overwrite 'latest' with a new manifest. manifest1 still has 'stable' tag.
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        $sharedConfigBlob->refresh();
        $sharedLayerBlob->refresh();

        expect($this->repository->blobs()->where('docker_blob_id', $sharedConfigBlob->id)->exists())->toBeTrue()
            ->and($this->repository->blobs()->where('docker_blob_id', $sharedLayerBlob->id)->exists())->toBeTrue();
    });

    it('keeps blobs shared between the orphaned manifest and another tagged manifest', function () {
        // Shared blob is used by both manifest1 (about to be orphaned) and manifest2 (tagged 'stable')
        $sharedLayer = 'sha256:'.hash('sha256', 'shared-layer');
        $exclusiveLayer = 'sha256:'.hash('sha256', 'exclusive-layer');

        $sharedBlob = $this->blobService->storeBlob($sharedLayer, 'shared-layer');
        $exclusiveBlob = $this->blobService->storeBlob($exclusiveLayer, 'exclusive-layer');
        $this->blobService->linkBlobToRepository($sharedBlob, $this->repository);
        $this->blobService->linkBlobToRepository($exclusiveBlob, $this->repository);

        $content1 = createManifestContent([$sharedLayer, $exclusiveLayer]);
        $content2 = createManifestContent([$sharedLayer]);
        $content3 = createManifestContent(['sha256:'.fake()->sha256()]);

        // manifest1 -> 'latest', manifest2 -> 'stable' (both reference sharedLayer)
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content1,
            DockerMediaType::ManifestV2->value
        );
        $this->manifestService->storeManifest(
            $this->repository,
            'stable',
            $content2,
            DockerMediaType::ManifestV2->value
        );

        // Move 'latest' to manifest3 -> manifest1 becomes orphaned
        $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content3,
            DockerMediaType::ManifestV2->value
        );

        $sharedBlob->refresh();
        $exclusiveBlob->refresh();

        // sharedLayer is still reachable via 'stable' -> manifest2, so it must stay linked
        expect($this->repository->blobs()->where('docker_blob_id', $sharedBlob->id)->exists())->toBeTrue()
            ->and($sharedBlob->reference_count)->toBeGreaterThan(0);

        // exclusiveLayer was only reachable via the now-orphaned manifest1, so it must be unlinked
        expect($this->repository->blobs()->where('docker_blob_id', $exclusiveBlob->id)->exists())->toBeFalse()
            ->and($exclusiveBlob->reference_count)->toBe(0);
    });
});

// =============================================================================
// MANIFEST LIST/MULTI-ARCH TESTS
// =============================================================================

describe('ManifestService Multi-Arch', function () {
    it('stores manifest list', function () {
        $content = createManifestListContent();

        $manifest = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestList->value
        );

        expect($manifest->media_type)->toBe(DockerMediaType::ManifestList)
            ->and($manifest->layer_digests)->toHaveCount(2);
    });

    it('extracts platform info from manifest list', function () {
        $content = createManifestListContent();

        $manifest = $this->manifestService->storeManifest(
            $this->repository,
            'latest',
            $content,
            DockerMediaType::ManifestList->value
        );

        expect($manifest->architecture)->toBe('amd64')
            ->and($manifest->os)->toBe('linux');
    });
});
