<?php

use App\Enums\DockerUploadStatus;
use App\Models\DockerBlob;
use App\Models\DockerRepository;
use App\Models\DockerUpload;
use App\Services\Docker\BlobStorageService;
use App\Services\Docker\GarbageCollectionService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->gcService = app(GarbageCollectionService::class);
    $this->blobService = app(BlobStorageService::class);
});

// =============================================================================
// ORPHANED BLOB DETECTION TESTS
// =============================================================================

describe('GarbageCollectionService Orphan Detection', function () {
    it('finds blobs with no repository associations', function () {
        $content = 'Orphan blob content';
        $digest = 'sha256:'.hash('sha256', $content);
        $orphanBlob = $this->blobService->storeBlob($digest, $content);

        $content2 = 'Referenced blob content';
        $digest2 = 'sha256:'.hash('sha256', $content2);
        $referencedBlob = $this->blobService->storeBlob($digest2, $content2);
        $repository = DockerRepository::factory()->create();
        $this->blobService->linkBlobToRepository($referencedBlob, $repository);

        $orphanedBlobs = $this->gcService->findOrphanedBlobs();

        expect($orphanedBlobs)->toHaveCount(1)
            ->and($orphanedBlobs->first()->id)->toBe($orphanBlob->id);
    });

    it('finds blobs with zero reference count', function () {
        DockerBlob::factory()->create(['reference_count' => 0]);
        // Blob with reference_count=1 and a repository association is not orphaned
        $repository = DockerRepository::factory()->create();
        $referencedBlob = DockerBlob::factory()->create(['reference_count' => 1]);
        $referencedBlob->repositories()->attach($repository);
        DockerBlob::factory()->create(['reference_count' => 0]);

        $orphanedBlobs = $this->gcService->findOrphanedBlobs();

        expect($orphanedBlobs)->toHaveCount(2);
    });
});

// =============================================================================
// STALE UPLOAD DETECTION TESTS
// =============================================================================

describe('GarbageCollectionService Stale Uploads', function () {
    it('finds expired uploads', function () {
        $repository = DockerRepository::factory()->create();

        DockerUpload::factory()->forRepository($repository)->expired()->create();
        DockerUpload::factory()->forRepository($repository)->create(); // Not expired

        $staleUploads = $this->gcService->findStaleUploads();

        expect($staleUploads)->toHaveCount(1);
    });

    it('finds uploads older than stale threshold', function () {
        config(['packgrid.docker.gc_stale_upload_hours' => 24]);
        $repository = DockerRepository::factory()->create();

        // Create old upload
        $oldUpload = DockerUpload::factory()->forRepository($repository)->create([
            'status' => DockerUploadStatus::Uploading,
            'expires_at' => now()->addHours(24), // Not expired yet
        ]);
        $oldUpload->forceFill(['created_at' => now()->subHours(25)])->save();

        // Create recent upload
        DockerUpload::factory()->forRepository($repository)->create([
            'status' => DockerUploadStatus::Uploading,
            'expires_at' => now()->addHours(24),
        ]);

        $staleUploads = $this->gcService->findStaleUploads();

        expect($staleUploads)->toHaveCount(1)
            ->and($staleUploads->first()->id)->toBe($oldUpload->id);
    });

    it('ignores completed uploads', function () {
        $repository = DockerRepository::factory()->create();

        DockerUpload::factory()->forRepository($repository)->complete()->expired()->create();

        $staleUploads = $this->gcService->findStaleUploads();

        expect($staleUploads)->toHaveCount(0);
    });

    it('ignores failed uploads', function () {
        $repository = DockerRepository::factory()->create();

        DockerUpload::factory()->forRepository($repository)->failed()->expired()->create();

        $staleUploads = $this->gcService->findStaleUploads();

        expect($staleUploads)->toHaveCount(0);
    });
});

// =============================================================================
// GARBAGE COLLECTION EXECUTION TESTS
// =============================================================================

describe('GarbageCollectionService Execute', function () {
    it('collects orphaned blobs', function () {
        $content = 'Orphan to collect';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $storagePath = $blob->storage_path;

        $results = $this->gcService->collectGarbage();

        expect($results['orphaned_blobs']['count'])->toBe(1)
            ->and($results['orphaned_blobs']['size'])->toBe(strlen($content))
            ->and(DockerBlob::find($blob->id))->toBeNull()
            ->and(Storage::disk('local')->exists($storagePath))->toBeFalse();
    });

    it('collects stale uploads', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $tempPath = $upload->temp_path;

        // Make upload expired
        $upload->forceFill(['expires_at' => now()->subHour()])->save();

        $results = $this->gcService->collectGarbage();

        $upload->refresh();
        expect($results['stale_uploads']['count'])->toBe(1)
            ->and($upload->status)->toBe(DockerUploadStatus::Failed)
            ->and(file_exists($tempPath))->toBeFalse();
    });

    it('dry run does not delete orphaned blobs', function () {
        $content = 'Orphan to keep in dry run';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $blobId = $blob->id;

        $results = $this->gcService->collectGarbage(dryRun: true);

        expect($results['orphaned_blobs']['count'])->toBe(1)
            ->and(DockerBlob::find($blobId))->not->toBeNull();
    });

    it('dry run does not delete stale uploads', function () {
        $repository = DockerRepository::factory()->create();
        $upload = $this->blobService->initChunkedUpload($repository);
        $uploadId = $upload->id;

        // Make upload expired
        $upload->forceFill(['expires_at' => now()->subHour()])->save();

        $results = $this->gcService->collectGarbage(dryRun: true);

        $upload->refresh();
        expect($results['stale_uploads']['count'])->toBe(1)
            ->and($upload->status)->not->toBe(DockerUploadStatus::Failed);
    });

    it('returns detailed results', function () {
        $content = 'Orphan with details';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);

        $results = $this->gcService->collectGarbage();

        expect($results['orphaned_blobs']['items'])->toHaveCount(1)
            ->and($results['orphaned_blobs']['items'][0]['digest'])->toBe($digest)
            ->and($results['orphaned_blobs']['items'][0]['size'])->toBe(strlen($content));
    });

    it('preserves referenced blobs', function () {
        $content = 'Referenced blob';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $repository = DockerRepository::factory()->create();
        $this->blobService->linkBlobToRepository($blob, $repository);

        $results = $this->gcService->collectGarbage();

        expect($results['orphaned_blobs']['count'])->toBe(0)
            ->and(DockerBlob::find($blob->id))->not->toBeNull();
    });
});

// =============================================================================
// REFERENCE COUNT RECALCULATION TESTS
// =============================================================================

describe('GarbageCollectionService Recalculate References', function () {
    it('recalculates blob reference counts', function () {
        $content = 'Blob with wrong count';
        $digest = 'sha256:'.hash('sha256', $content);
        $blob = $this->blobService->storeBlob($digest, $content);
        $repository = DockerRepository::factory()->create();

        $this->blobService->linkBlobToRepository($blob, $repository);

        // Manually corrupt the reference count
        $blob->forceFill(['reference_count' => 999])->save();

        $updated = $this->gcService->recalculateBlobReferences();

        $blob->refresh();
        expect($updated)->toBe(1)
            ->and($blob->reference_count)->toBe(1);
    });

    it('returns count of updated blobs', function () {
        // Create blobs with incorrect reference counts
        $blob1 = DockerBlob::factory()->create(['reference_count' => 5]);
        $blob2 = DockerBlob::factory()->create(['reference_count' => 10]);
        $blob3 = DockerBlob::factory()->create(['reference_count' => 0]); // Already correct

        $updated = $this->gcService->recalculateBlobReferences();

        expect($updated)->toBe(2);
    });
});

// =============================================================================
// STATISTICS TESTS
// =============================================================================

describe('GarbageCollectionService Statistics', function () {
    it('returns complete statistics', function () {
        // Create some blobs
        $content1 = str_repeat('a', 1000);
        $content2 = str_repeat('b', 2000);
        $digest1 = 'sha256:'.hash('sha256', $content1);
        $digest2 = 'sha256:'.hash('sha256', $content2);

        $orphanBlob = $this->blobService->storeBlob($digest1, $content1);
        $referencedBlob = $this->blobService->storeBlob($digest2, $content2);

        $repository = DockerRepository::factory()->create();
        $this->blobService->linkBlobToRepository($referencedBlob, $repository);

        // Create stale upload
        $upload = $this->blobService->initChunkedUpload($repository);
        $upload->forceFill(['expires_at' => now()->subHour()])->save();

        $stats = $this->gcService->getStatistics();

        expect($stats['total_blobs'])->toBe(2)
            ->and($stats['total_size'])->toBe(3000)
            ->and($stats['orphaned_blobs'])->toBe(1)
            ->and($stats['orphaned_size'])->toBe(1000)
            ->and($stats['stale_uploads'])->toBe(1)
            ->and($stats['potential_savings'])->toBe(1000)
            ->and($stats['total_size_formatted'])->toBe('2.93 KB')
            ->and($stats['orphaned_size_formatted'])->toBe('1000 B');
    });
});
