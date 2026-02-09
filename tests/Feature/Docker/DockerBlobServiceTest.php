<?php

use App\Models\DockerBlob;
use App\Models\DockerRepository;
use App\Services\Docker\BlobStorageService;
use App\Services\Docker\DigestService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->digestService = app(DigestService::class);
    $this->blobService = app(BlobStorageService::class);
});

// =============================================================================
// BLOB STORAGE TESTS
// =============================================================================

describe('BlobStorageService Store', function () {
    it('stores a blob and creates database record', function () {
        $content = 'Test blob content';
        $digest = 'sha256:'.hash('sha256', $content);

        $blob = $this->blobService->storeBlob($digest, $content);

        expect($blob)->toBeInstanceOf(DockerBlob::class)
            ->and($blob->digest)->toBe($digest)
            ->and($blob->size)->toBe(strlen($content))
            ->and($blob->reference_count)->toBe(0);

        expect(Storage::disk('local')->exists($blob->storage_path))->toBeTrue();
    });

    it('returns existing blob if digest already exists', function () {
        $content = 'Duplicate content';
        $digest = 'sha256:'.hash('sha256', $content);

        $blob1 = $this->blobService->storeBlob($digest, $content);
        $blob2 = $this->blobService->storeBlob($digest, $content);

        expect($blob1->id)->toBe($blob2->id)
            ->and(DockerBlob::count())->toBe(1);
    });

    it('throws exception when digest does not match content', function () {
        $content = 'Test content';
        $wrongDigest = 'sha256:'.hash('sha256', 'Different content');

        expect(fn () => $this->blobService->storeBlob($wrongDigest, $content))
            ->toThrow(RuntimeException::class, 'Content digest does not match');
    });

    it('stores blob from file', function () {
        $content = 'File blob content';
        $digest = 'sha256:'.hash('sha256', $content);
        $tempFile = tempnam(sys_get_temp_dir(), 'blob_');
        file_put_contents($tempFile, $content);

        $blob = $this->blobService->storeBlobFromFile($digest, $tempFile);

        expect($blob)->toBeInstanceOf(DockerBlob::class)
            ->and($blob->digest)->toBe($digest)
            ->and($blob->size)->toBe(strlen($content));

        // Temp file should be deleted
        expect(file_exists($tempFile))->toBeFalse();
    });
});

// =============================================================================
// BLOB RETRIEVAL TESTS
// =============================================================================

describe('BlobStorageService Retrieve', function () {
    it('checks if blob exists by digest', function () {
        $content = 'Test content';
        $digest = 'sha256:'.hash('sha256', $content);

        expect($this->blobService->blobExists($digest))->toBeFalse();

        $this->blobService->storeBlob($digest, $content);

        expect($this->blobService->blobExists($digest))->toBeTrue();
    });

    it('gets blob by digest', function () {
        $content = 'Retrievable content';
        $digest = 'sha256:'.hash('sha256', $content);
        $storedBlob = $this->blobService->storeBlob($digest, $content);

        $retrievedBlob = $this->blobService->getBlob($digest);

        expect($retrievedBlob)->not->toBeNull()
            ->and($retrievedBlob->id)->toBe($storedBlob->id);
    });

    it('returns null for non-existent blob', function () {
        $digest = 'sha256:'.str_repeat('a', 64);

        expect($this->blobService->getBlob($digest))->toBeNull();
    });

    it('gets blob content', function () {
        $content = 'Content to retrieve';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);

        $retrievedContent = $this->blobService->getBlobContent($blob);

        expect($retrievedContent)->toBe($content);
    });
});

// =============================================================================
// BLOB-REPOSITORY LINKING TESTS
// =============================================================================

describe('BlobStorageService Linking', function () {
    it('links blob to repository', function () {
        $content = 'Linkable content';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $repository = DockerRepository::factory()->create();

        $this->blobService->linkBlobToRepository($blob, $repository);

        $blob->refresh();
        expect($blob->reference_count)->toBe(1)
            ->and($repository->blobs()->where('docker_blob_id', $blob->id)->exists())->toBeTrue();
    });

    it('does not duplicate links', function () {
        $content = 'Link once content';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $repository = DockerRepository::factory()->create();

        $this->blobService->linkBlobToRepository($blob, $repository);
        $this->blobService->linkBlobToRepository($blob, $repository);

        $blob->refresh();
        expect($blob->reference_count)->toBe(1)
            ->and($repository->blobs()->count())->toBe(1);
    });

    it('unlinks blob from repository', function () {
        $content = 'Unlinkable content';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $repository = DockerRepository::factory()->create();

        $this->blobService->linkBlobToRepository($blob, $repository);
        $this->blobService->unlinkBlobFromRepository($blob, $repository);

        $blob->refresh();
        expect($blob->reference_count)->toBe(0)
            ->and($repository->blobs()->where('docker_blob_id', $blob->id)->exists())->toBeFalse();
    });
});

// =============================================================================
// CROSS-MOUNT TESTS
// =============================================================================

describe('BlobStorageService Mount', function () {
    it('mounts blob from one repository to another', function () {
        $content = 'Mountable content';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $sourceRepo = DockerRepository::factory()->create();
        $targetRepo = DockerRepository::factory()->create();

        $this->blobService->linkBlobToRepository($blob, $sourceRepo);
        $result = $this->blobService->mountBlob($digest, $sourceRepo, $targetRepo);

        $blob->refresh();
        expect($result)->toBeTrue()
            ->and($blob->reference_count)->toBe(2)
            ->and($targetRepo->blobs()->where('docker_blob_id', $blob->id)->exists())->toBeTrue();
    });

    it('fails to mount non-existent blob', function () {
        $sourceRepo = DockerRepository::factory()->create();
        $targetRepo = DockerRepository::factory()->create();
        $nonExistentDigest = 'sha256:'.str_repeat('a', 64);

        $result = $this->blobService->mountBlob($nonExistentDigest, $sourceRepo, $targetRepo);

        expect($result)->toBeFalse();
    });

    it('fails to mount blob not linked to source repository', function () {
        $content = 'Unmounted content';
        $digest = 'sha256:'.hash('sha256', $content);
        $this->blobService->storeBlob($digest, $content);
        $sourceRepo = DockerRepository::factory()->create();
        $targetRepo = DockerRepository::factory()->create();

        $result = $this->blobService->mountBlob($digest, $sourceRepo, $targetRepo);

        expect($result)->toBeFalse();
    });
});

// =============================================================================
// CHUNKED UPLOAD TESTS
// =============================================================================

describe('BlobStorageService Chunked Upload', function () {
    it('initializes chunked upload', function () {
        $repository = DockerRepository::factory()->create();

        $upload = $this->blobService->initChunkedUpload($repository);

        expect($upload)->not->toBeNull()
            ->and($upload->docker_repository_id)->toBe($repository->id)
            ->and($upload->status->value)->toBe('pending')
            ->and($upload->uploaded_bytes)->toBe(0)
            ->and(file_exists($upload->temp_path))->toBeTrue();
    });

    it('appends chunk to upload', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $chunk = 'First chunk data';

        $this->blobService->appendChunk($upload, $chunk, 0, strlen($chunk) - 1);

        $upload->refresh();
        expect($upload->status->value)->toBe('uploading')
            ->and($upload->uploaded_bytes)->toBe(strlen($chunk))
            ->and(file_get_contents($upload->temp_path))->toBe($chunk);
    });

    it('completes chunked upload', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $content = 'Complete upload content';

        $this->blobService->appendChunk($upload, $content, 0, strlen($content) - 1);
        $blob = $this->blobService->completeChunkedUpload($upload);

        $upload->refresh();
        expect($upload->status->value)->toBe('complete')
            ->and($blob)->toBeInstanceOf(DockerBlob::class)
            ->and($blob->digest)->toBe('sha256:'.hash('sha256', $content));
    });

    it('verifies digest on upload completion', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $content = 'Content to verify';
        $expectedDigest = 'sha256:'.hash('sha256', $content);

        $this->blobService->appendChunk($upload, $content, 0, strlen($content) - 1);
        $blob = $this->blobService->completeChunkedUpload($upload, $expectedDigest);

        expect($blob->digest)->toBe($expectedDigest);
    });

    it('fails upload when digest mismatch', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $content = 'Mismatched content';
        $wrongDigest = 'sha256:'.str_repeat('a', 64);

        $this->blobService->appendChunk($upload, $content, 0, strlen($content) - 1);

        expect(fn () => $this->blobService->completeChunkedUpload($upload, $wrongDigest))
            ->toThrow(RuntimeException::class, 'Provided digest does not match');
    });

    it('cancels upload and cleans up temp file', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $tempPath = $upload->temp_path;

        $this->blobService->cancelUpload($upload);

        $upload->refresh();
        expect($upload->status->value)->toBe('failed')
            ->and(file_exists($tempPath))->toBeFalse();
    });
});

// =============================================================================
// BLOB DELETION TESTS
// =============================================================================

describe('BlobStorageService Delete', function () {
    it('deletes orphaned blob', function () {
        $content = 'Deletable content';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $storagePath = $blob->storage_path;

        $this->blobService->deleteBlob($blob);

        expect(DockerBlob::find($blob->id))->toBeNull()
            ->and(Storage::disk('local')->exists($storagePath))->toBeFalse();
    });

    it('prevents deletion of referenced blob', function () {
        $content = 'Referenced content';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $repository = DockerRepository::factory()->create();
        $this->blobService->linkBlobToRepository($blob, $repository);

        expect(fn () => $this->blobService->deleteBlob($blob))
            ->toThrow(RuntimeException::class, 'Cannot delete blob with active references');
    });
});

// =============================================================================
// STATISTICS TESTS
// =============================================================================

describe('BlobStorageService Statistics', function () {
    it('gets orphaned blobs', function () {
        $content1 = 'Orphan content 1';
        $content2 = 'Referenced content';
        $digest1 = 'sha256:'.hash('sha256', $content1);
        $digest2 = 'sha256:'.hash('sha256', $content2);

        $orphanBlob = $this->blobService->storeBlob($digest1, $content1);
        $referencedBlob = $this->blobService->storeBlob($digest2, $content2);

        $repository = DockerRepository::factory()->create();
        $this->blobService->linkBlobToRepository($referencedBlob, $repository);

        $orphanedBlobs = $this->blobService->getOrphanedBlobs();

        expect($orphanedBlobs)->toHaveCount(1)
            ->and($orphanedBlobs->first()->id)->toBe($orphanBlob->id);
    });

    it('gets total storage size', function () {
        $content1 = str_repeat('a', 1000);
        $content2 = str_repeat('b', 2000);
        $digest1 = 'sha256:'.hash('sha256', $content1);
        $digest2 = 'sha256:'.hash('sha256', $content2);

        $this->blobService->storeBlob($digest1, $content1);
        $this->blobService->storeBlob($digest2, $content2);

        $totalSize = $this->blobService->getTotalStorageSize();

        expect($totalSize)->toBe(3000);
    });
});
