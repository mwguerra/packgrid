<?php

namespace App\Services\Docker;

use App\Enums\DockerMediaType;
use App\Models\DockerActivity;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ManifestService
{
    public function __construct(
        private readonly DigestService $digestService,
        private readonly BlobStorageService $blobStorageService
    ) {}

    public function storeManifest(
        DockerRepository $repository,
        string $reference,
        string $content,
        string $mediaType
    ): DockerManifest {
        // Validate content is valid JSON
        $data = json_decode($content, true);
        if ($data === null) {
            throw new RuntimeException('Invalid manifest: not valid JSON.');
        }

        // Calculate digest
        $digest = $this->digestService->calculate($content);

        // Parse manifest for metadata
        $parsed = $this->parseManifest($data, $mediaType);

        return DB::transaction(function () use ($repository, $reference, $content, $mediaType, $digest, $parsed) {
            // Check if manifest already exists
            $manifest = DockerManifest::where('digest', $digest)->first();

            if (! $manifest) {
                $manifest = DockerManifest::create([
                    'docker_repository_id' => $repository->id,
                    'digest' => $digest,
                    'media_type' => $mediaType,
                    'content' => $content,
                    'size' => strlen($content),
                    'layer_digests' => $parsed['layer_digests'],
                    'config_digest' => $parsed['config_digest'],
                    'architecture' => $parsed['architecture'],
                    'os' => $parsed['os'],
                ]);
            }

            // Handle tag if reference is a tag (not a digest)
            if (! $this->isDigest($reference)) {
                $this->updateOrCreateTag($repository, $reference, $manifest);
            }

            // Update repository statistics
            $repository->incrementPushCount();
            $repository->updateStatistics();

            // Log activity
            DockerActivity::logPush($repository, $this->isDigest($reference) ? null : $reference, $digest, strlen($content));

            return $manifest;
        });
    }

    public function getManifest(DockerRepository $repository, string $reference): ?DockerManifest
    {
        if ($this->isDigest($reference)) {
            return $repository->manifests()->where('digest', $reference)->first();
        }

        // Reference is a tag
        $tag = $repository->tags()->where('name', $reference)->first();

        return $tag?->manifest;
    }

    public function getManifestByDigest(string $digest): ?DockerManifest
    {
        return DockerManifest::where('digest', $digest)->first();
    }

    public function deleteManifest(DockerRepository $repository, string $reference): bool
    {
        return DB::transaction(function () use ($repository, $reference) {
            $manifest = $this->getManifest($repository, $reference);

            if (! $manifest) {
                return false;
            }

            // Delete all tags pointing to this manifest
            $manifest->tags()->delete();

            // Unlink associated blobs
            $this->unlinkManifestBlobs($repository, $manifest);

            // Log activity
            DockerActivity::logDelete($repository, null, $manifest->digest);

            // Delete manifest
            $manifest->delete();

            // Update repository statistics
            $repository->updateStatistics();

            return true;
        });
    }

    public function deleteTag(DockerRepository $repository, string $tagName): bool
    {
        return DB::transaction(function () use ($repository, $tagName) {
            $tag = $repository->tags()->where('name', $tagName)->first();

            if (! $tag) {
                return false;
            }

            $manifest = $tag->manifest;

            // Log activity
            DockerActivity::logDelete($repository, $tagName, $manifest->digest);

            // Delete the tag
            $tag->delete();

            // If manifest has no more tags and is unreferenced, delete it
            if ($manifest->tags()->count() === 0) {
                $this->unlinkManifestBlobs($repository, $manifest);
                $manifest->delete();
            }

            // Update repository statistics
            $repository->updateStatistics();

            return true;
        });
    }

    public function listTags(DockerRepository $repository, ?int $limit = null, ?string $last = null): array
    {
        $query = $repository->tags()->orderBy('name');

        if ($last !== null) {
            $query->where('name', '>', $last);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->pluck('name')->toArray();
    }

    public function tagExists(DockerRepository $repository, string $tagName): bool
    {
        return $repository->tags()->where('name', $tagName)->exists();
    }

    public function manifestExists(DockerRepository $repository, string $reference): bool
    {
        if ($this->isDigest($reference)) {
            return $repository->manifests()->where('digest', $reference)->exists();
        }

        return $this->tagExists($repository, $reference);
    }

    protected function updateOrCreateTag(DockerRepository $repository, string $name, DockerManifest $manifest): DockerTag
    {
        return DockerTag::updateOrCreate(
            [
                'docker_repository_id' => $repository->id,
                'name' => $name,
            ],
            [
                'docker_manifest_id' => $manifest->id,
            ]
        );
    }

    protected function parseManifest(array $data, string $mediaType): array
    {
        $result = [
            'layer_digests' => [],
            'config_digest' => null,
            'architecture' => null,
            'os' => null,
        ];

        // Handle Docker Manifest V2 and OCI Manifest
        if (in_array($mediaType, [
            DockerMediaType::ManifestV2->value,
            DockerMediaType::OciManifest->value,
        ])) {
            // Extract layer digests
            if (isset($data['layers']) && is_array($data['layers'])) {
                $result['layer_digests'] = array_column($data['layers'], 'digest');
            }

            // Extract config digest
            if (isset($data['config']['digest'])) {
                $result['config_digest'] = $data['config']['digest'];
            }
        }

        // Handle Manifest List / OCI Index
        if (in_array($mediaType, [
            DockerMediaType::ManifestList->value,
            DockerMediaType::OciIndex->value,
        ])) {
            // These are multi-arch manifests, extract manifests array
            if (isset($data['manifests']) && is_array($data['manifests'])) {
                $result['layer_digests'] = array_column($data['manifests'], 'digest');

                // Get platform info from first manifest
                if (isset($data['manifests'][0]['platform'])) {
                    $platform = $data['manifests'][0]['platform'];
                    $result['architecture'] = $platform['architecture'] ?? null;
                    $result['os'] = $platform['os'] ?? null;
                }
            }
        } else {
            // Single-arch manifest - try to get platform from config blob
            // Architecture and OS might be in the config blob, but we'd need to fetch it
            // For now, we leave them null and they can be populated later
        }

        return $result;
    }

    protected function unlinkManifestBlobs(DockerRepository $repository, DockerManifest $manifest): void
    {
        // Unlink config blob
        if ($manifest->config_digest) {
            $blob = $this->blobStorageService->getBlob($manifest->config_digest);
            if ($blob) {
                $this->blobStorageService->unlinkBlobFromRepository($blob, $repository);
            }
        }

        // Unlink layer blobs
        foreach ($manifest->layer_digests ?? [] as $layerDigest) {
            $blob = $this->blobStorageService->getBlob($layerDigest);
            if ($blob) {
                $this->blobStorageService->unlinkBlobFromRepository($blob, $repository);
            }
        }
    }

    protected function isDigest(string $reference): bool
    {
        return str_contains($reference, ':') && $this->digestService->validate($reference);
    }
}
