<?php

namespace App\Services\Docker;

use App\Models\DockerBlob;
use App\Models\DockerManifest;
use App\Models\DockerUpload;
use Illuminate\Support\Collection;

class GarbageCollectionService
{
    public function __construct(
        private readonly BlobStorageService $blobStorageService,
        private readonly ManifestService $manifestService
    ) {}

    public function collectGarbage(bool $dryRun = false): array
    {
        $results = [
            'untagged_manifests' => [
                'count' => 0,
                'items' => [],
            ],
            'orphaned_blobs' => [
                'count' => 0,
                'size' => 0,
                'items' => [],
            ],
            'stale_uploads' => [
                'count' => 0,
                'items' => [],
            ],
        ];

        // Prune untagged manifests first - this unlinks their no-longer-reachable
        // blobs, which is what makes them detectable by findOrphanedBlobs below.
        $untaggedManifests = $this->findUntaggedManifests();
        foreach ($untaggedManifests as $manifest) {
            $results['untagged_manifests']['count']++;
            $results['untagged_manifests']['items'][] = [
                'digest' => $manifest->digest,
                'repository' => $manifest->repository?->name ?? 'unknown',
                'size' => $manifest->size,
                'created_at' => $manifest->created_at->toDateTimeString(),
            ];

            if (! $dryRun && $manifest->repository) {
                $this->manifestService->cleanupIfOrphaned($manifest, $manifest->repository);
            }
        }

        // Clean orphaned blobs (now includes blobs unlinked above)
        $orphanedBlobs = $this->findOrphanedBlobs();
        foreach ($orphanedBlobs as $blob) {
            $results['orphaned_blobs']['count']++;
            $results['orphaned_blobs']['size'] += $blob->size;
            $results['orphaned_blobs']['items'][] = [
                'digest' => $blob->digest,
                'size' => $blob->size,
                'created_at' => $blob->created_at->toDateTimeString(),
            ];

            if (! $dryRun) {
                $this->blobStorageService->deleteBlob($blob);
            }
        }

        // Clean stale uploads
        $staleUploads = $this->findStaleUploads();
        foreach ($staleUploads as $upload) {
            $results['stale_uploads']['count']++;
            $results['stale_uploads']['items'][] = [
                'id' => $upload->id,
                'repository' => $upload->repository->name ?? 'unknown',
                'created_at' => $upload->created_at->toDateTimeString(),
                'expires_at' => $upload->expires_at?->toDateTimeString(),
            ];

            if (! $dryRun) {
                $this->blobStorageService->cancelUpload($upload);
            }
        }

        return $results;
    }

    public function findUntaggedManifests(): Collection
    {
        return DockerManifest::doesntHave('tags')->with('repository')->get();
    }

    public function findOrphanedBlobs(): Collection
    {
        // Find blobs with no repository associations
        return DockerBlob::whereDoesntHave('repositories')
            ->orWhere('reference_count', '<=', 0)
            ->get();
    }

    public function findStaleUploads(): Collection
    {
        $staleHours = config('packgrid.docker.gc_stale_upload_hours', 24);

        return DockerUpload::whereIn('status', ['pending', 'uploading'])
            ->where(function ($query) use ($staleHours) {
                $query->where('expires_at', '<', now())
                    ->orWhere('created_at', '<', now()->subHours($staleHours));
            })
            ->get();
    }

    public function recalculateBlobReferences(): int
    {
        $updated = 0;

        DockerBlob::chunk(100, function ($blobs) use (&$updated) {
            foreach ($blobs as $blob) {
                $actualCount = $blob->repositories()->count();
                if ($blob->reference_count !== $actualCount) {
                    $blob->forceFill(['reference_count' => $actualCount])->save();
                    $updated++;
                }
            }
        });

        return $updated;
    }

    public function getStatistics(): array
    {
        $totalBlobs = DockerBlob::count();
        $totalSize = DockerBlob::sum('size');
        $orphanedBlobs = $this->findOrphanedBlobs()->count();
        $orphanedSize = $this->findOrphanedBlobs()->sum('size');
        $staleUploads = $this->findStaleUploads()->count();
        $untaggedManifests = $this->findUntaggedManifests();
        $untaggedSize = $this->estimateReclaimableFromUntaggedManifests($untaggedManifests);

        return [
            'total_blobs' => $totalBlobs,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatSize($totalSize),
            'orphaned_blobs' => $orphanedBlobs,
            'orphaned_size' => $orphanedSize,
            'orphaned_size_formatted' => $this->formatSize($orphanedSize),
            'untagged_manifests' => $untaggedManifests->count(),
            'untagged_reclaimable_size' => $untaggedSize,
            'untagged_reclaimable_size_formatted' => $this->formatSize($untaggedSize),
            'stale_uploads' => $staleUploads,
            'potential_savings' => $orphanedSize + $untaggedSize,
            'potential_savings_formatted' => $this->formatSize($orphanedSize + $untaggedSize),
        ];
    }

    /**
     * Estimate space reclaimable by pruning untagged manifests, by summing the
     * sizes of blobs that are referenced ONLY by untagged manifests in their
     * repository (i.e. not also reachable from tagged manifests).
     */
    protected function estimateReclaimableFromUntaggedManifests(Collection $untaggedManifests): int
    {
        $reclaimable = [];

        $byRepo = $untaggedManifests->groupBy('docker_repository_id');

        foreach ($byRepo as $manifests) {
            $repository = $manifests->first()->repository;
            if (! $repository) {
                continue;
            }

            $reachable = $this->manifestService->collectReachableBlobDigests($repository);

            foreach ($manifests as $manifest) {
                foreach ($this->manifestService->collectManifestBlobDigests($manifest) as $digest) {
                    if (in_array($digest, $reachable, true)) {
                        continue;
                    }
                    $reclaimable[$digest] = true;
                }
            }
        }

        if ($reclaimable === []) {
            return 0;
        }

        return (int) DockerBlob::whereIn('digest', array_keys($reclaimable))->sum('size');
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
