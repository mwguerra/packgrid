<?php

namespace App\Services\Docker;

use App\Enums\DockerUploadStatus;
use App\Models\DockerBlob;
use App\Models\DockerRepository;
use App\Models\DockerUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlobStorageService
{
    public function __construct(
        private readonly DigestService $digestService
    ) {}

    public function storeBlob(string $digest, string $content, ?string $mediaType = null): DockerBlob
    {
        // Validate digest matches content
        if (! $this->digestService->verify($content, $digest)) {
            throw new RuntimeException('Content digest does not match provided digest.');
        }

        // Check if blob already exists
        $existingBlob = DockerBlob::where('digest', $digest)->first();
        if ($existingBlob) {
            return $existingBlob;
        }

        // Store the blob
        $storagePath = $this->generateStoragePath($digest);
        $this->disk()->put($storagePath, $content);

        return DockerBlob::create([
            'digest' => $digest,
            'size' => strlen($content),
            'media_type' => $mediaType,
            'storage_path' => $storagePath,
            'reference_count' => 0,
        ]);
    }

    public function storeBlobFromFile(string $digest, string $tempPath, ?string $mediaType = null): DockerBlob
    {
        // Validate digest matches content
        if (! $this->digestService->verifyFile($tempPath, $digest)) {
            throw new RuntimeException('File digest does not match provided digest.');
        }

        // Check if blob already exists
        $existingBlob = DockerBlob::where('digest', $digest)->first();
        if ($existingBlob) {
            // Clean up temp file since blob already exists
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $existingBlob;
        }

        // Move file to permanent storage
        $storagePath = $this->generateStoragePath($digest);
        $stream = fopen($tempPath, 'r');

        if ($stream === false) {
            throw new RuntimeException("Failed to open temp file: {$tempPath}");
        }

        $this->disk()->writeStream($storagePath, $stream);
        fclose($stream);

        // Get file size before deleting
        $size = filesize($tempPath);

        // Clean up temp file
        unlink($tempPath);

        return DockerBlob::create([
            'digest' => $digest,
            'size' => $size,
            'media_type' => $mediaType,
            'storage_path' => $storagePath,
            'reference_count' => 0,
        ]);
    }

    public function blobExists(string $digest): bool
    {
        return DockerBlob::where('digest', $digest)->exists();
    }

    public function getBlob(string $digest): ?DockerBlob
    {
        return DockerBlob::where('digest', $digest)->first();
    }

    public function getBlobContent(DockerBlob $blob): string
    {
        $content = $this->disk()->get($blob->storage_path);

        if ($content === null) {
            throw new RuntimeException("Blob not found in storage: {$blob->digest}");
        }

        return $content;
    }

    public function streamBlob(DockerBlob $blob): StreamedResponse
    {
        return new StreamedResponse(function () use ($blob) {
            $stream = $this->disk()->readStream($blob->storage_path);

            if ($stream === null) {
                throw new RuntimeException("Blob not found in storage: {$blob->digest}");
            }

            while (! feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }

            fclose($stream);
        }, 200, [
            'Content-Type' => $blob->media_type?->value ?? 'application/octet-stream',
            'Content-Length' => $blob->size,
            'Docker-Content-Digest' => $blob->digest,
        ]);
    }

    public function linkBlobToRepository(DockerBlob $blob, DockerRepository $repository): void
    {
        if (! $repository->blobs()->where('docker_blob_id', $blob->id)->exists()) {
            $repository->blobs()->attach($blob->id);
            $blob->incrementReferenceCount();
        }
    }

    public function unlinkBlobFromRepository(DockerBlob $blob, DockerRepository $repository): void
    {
        if ($repository->blobs()->where('docker_blob_id', $blob->id)->exists()) {
            $repository->blobs()->detach($blob->id);
            $blob->decrementReferenceCount();
        }
    }

    public function mountBlob(string $digest, DockerRepository $fromRepository, DockerRepository $toRepository): bool
    {
        $blob = $this->getBlob($digest);

        if (! $blob) {
            return false;
        }

        // Verify blob is linked to source repository
        if (! $fromRepository->blobs()->where('docker_blob_id', $blob->id)->exists()) {
            return false;
        }

        // Link to target repository
        $this->linkBlobToRepository($blob, $toRepository);

        return true;
    }

    public function initChunkedUpload(DockerRepository $repository): DockerUpload
    {
        $uploadTimeout = config('packgrid.docker.upload_timeout', 86400);
        $tempPath = $this->generateTempPath();

        // Ensure temp directory exists
        $tempDir = dirname($tempPath);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Create empty temp file
        touch($tempPath);

        return DockerUpload::create([
            'docker_repository_id' => $repository->id,
            'status' => DockerUploadStatus::Pending,
            'temp_path' => $tempPath,
            'uploaded_bytes' => 0,
            'expires_at' => now()->addSeconds($uploadTimeout),
        ]);
    }

    public function appendChunk(DockerUpload $upload, string $content, int $start, int $end): void
    {
        if (! $upload->isActive()) {
            throw new RuntimeException('Upload is no longer active.');
        }

        if (! file_exists($upload->temp_path)) {
            throw new RuntimeException('Upload temp file not found.');
        }

        // Validate content range
        $contentLength = strlen($content);
        $expectedLength = $end - $start + 1;

        if ($contentLength !== $expectedLength) {
            throw new RuntimeException("Content length ({$contentLength}) does not match range ({$expectedLength}).");
        }

        // Write chunk at correct position
        $handle = fopen($upload->temp_path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Failed to open temp file for writing.');
        }

        fseek($handle, $start);
        fwrite($handle, $content);
        fclose($handle);

        // Update upload status
        $upload->forceFill([
            'status' => DockerUploadStatus::Uploading,
            'uploaded_bytes' => max($upload->uploaded_bytes, $end + 1),
        ])->save();
    }

    public function appendChunkFromStream(DockerUpload $upload, $stream): int
    {
        if (! $upload->isActive()) {
            throw new RuntimeException('Upload is no longer active.');
        }

        if (! file_exists($upload->temp_path)) {
            throw new RuntimeException('Upload temp file not found.');
        }

        $handle = fopen($upload->temp_path, 'a');
        if ($handle === false) {
            throw new RuntimeException('Failed to open temp file for writing.');
        }

        $bytesWritten = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk !== false) {
                $bytesWritten += fwrite($handle, $chunk);
            }
        }
        fclose($handle);

        $upload->forceFill([
            'status' => DockerUploadStatus::Uploading,
            'uploaded_bytes' => $upload->uploaded_bytes + $bytesWritten,
        ])->save();

        return $bytesWritten;
    }

    public function completeChunkedUpload(DockerUpload $upload, ?string $digest = null): DockerBlob
    {
        if (! $upload->isActive()) {
            throw new RuntimeException('Upload is no longer active.');
        }

        if (! file_exists($upload->temp_path)) {
            throw new RuntimeException('Upload temp file not found.');
        }

        // Calculate digest if not provided
        $calculatedDigest = $this->digestService->calculateFromFile($upload->temp_path);

        if ($digest !== null && ! hash_equals($calculatedDigest, $digest)) {
            $upload->markAsFailed();
            throw new RuntimeException('Provided digest does not match uploaded content.');
        }

        $finalDigest = $digest ?? $calculatedDigest;

        try {
            $blob = $this->storeBlobFromFile($finalDigest, $upload->temp_path);
            $this->linkBlobToRepository($blob, $upload->repository);
            $upload->markAsComplete();

            return $blob;
        } catch (\Throwable $e) {
            $upload->markAsFailed();
            throw $e;
        }
    }

    public function cancelUpload(DockerUpload $upload): void
    {
        if (file_exists($upload->temp_path)) {
            unlink($upload->temp_path);
        }

        $upload->markAsFailed();
    }

    public function getUpload(string $uuid): ?DockerUpload
    {
        return DockerUpload::find($uuid);
    }

    public function deleteBlob(DockerBlob $blob): void
    {
        // Only delete if no references
        if ($blob->reference_count > 0) {
            throw new RuntimeException('Cannot delete blob with active references.');
        }

        // Delete from storage
        $this->disk()->delete($blob->storage_path);

        // Delete record
        $blob->delete();
    }

    public function getOrphanedBlobs(): \Illuminate\Database\Eloquent\Collection
    {
        return DockerBlob::where('reference_count', '<=', 0)->get();
    }

    public function getStaleUploads(): \Illuminate\Database\Eloquent\Collection
    {
        return DockerUpload::where('status', DockerUploadStatus::Pending)
            ->orWhere('status', DockerUploadStatus::Uploading)
            ->where('expires_at', '<', now())
            ->get();
    }

    public function getTotalStorageSize(): int
    {
        return DockerBlob::sum('size');
    }

    protected function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('packgrid.docker.disk', 'local'));
    }

    protected function generateStoragePath(string $digest): string
    {
        $hash = $this->digestService->extractHash($digest);
        $basePath = config('packgrid.docker.storage_path', 'docker/blobs');

        // Use first two characters of hash for directory sharding
        $shard = substr($hash, 0, 2);

        return "{$basePath}/{$shard}/{$hash}";
    }

    protected function generateTempPath(): string
    {
        $tempDir = storage_path('app/docker-uploads');

        return "{$tempDir}/".Str::uuid()->toString();
    }
}
