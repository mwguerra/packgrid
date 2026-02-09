<?php

namespace App\Http\Controllers\Docker;

use App\Enums\RepositoryVisibility;
use App\Http\Controllers\Controller;
use App\Models\DockerActivity;
use App\Models\DockerRepository;
use App\Services\Docker\BlobStorageService;
use App\Services\Docker\DigestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UploadController extends Controller
{
    public function __construct(
        private readonly BlobStorageService $blobStorageService,
        private readonly DigestService $digestService
    ) {}

    /**
     * Start a blob upload or perform cross-repository mount.
     * POST /v2/{name}/blobs/uploads/
     */
    public function start(Request $request, string $name): Response|JsonResponse
    {
        $repository = $this->getOrCreateRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_INVALID', "invalid repository name: {$name}", 400);
        }

        if (! $repository->enabled) {
            return $this->errorResponse('DENIED', "repository is disabled: {$name}", 403);
        }

        // Check for cross-repository mount
        $mount = $request->query('mount');
        $from = $request->query('from');

        if ($mount && $from) {
            return $this->handleMount($repository, $mount, $from);
        }

        // Check for single-request (monolithic) upload
        $digest = $request->query('digest');
        if ($digest && $request->getContent()) {
            return $this->handleMonolithicUpload($repository, $request, $digest);
        }

        // Start chunked upload
        $upload = $this->blobStorageService->initChunkedUpload($repository);

        return response('', 202, [
            'Location' => url("/v2/{$name}/blobs/uploads/{$upload->id}"),
            'Docker-Upload-UUID' => $upload->id,
            'Range' => '0-0',
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    /**
     * Upload a chunk of data.
     * PATCH /v2/{name}/blobs/uploads/{uuid}
     */
    public function chunk(Request $request, string $name, string $uuid): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $upload = $this->blobStorageService->getUpload($uuid);

        if (! $upload || $upload->docker_repository_id !== $repository->id) {
            return $this->errorResponse('BLOB_UPLOAD_UNKNOWN', "blob upload unknown to registry: {$uuid}", 404);
        }

        if (! $upload->isActive()) {
            return $this->errorResponse('BLOB_UPLOAD_INVALID', "blob upload is no longer active: {$uuid}", 400);
        }

        // Handle Content-Range header
        $contentRange = $request->header('Content-Range');
        if ($contentRange) {
            // Format: bytes start-end/total
            if (preg_match('/^(\d+)-(\d+)$/', $contentRange, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                $content = $request->getContent();

                try {
                    $this->blobStorageService->appendChunk($upload, $content, $start, $end);
                } catch (\Throwable $e) {
                    return $this->errorResponse('BLOB_UPLOAD_INVALID', "chunk upload failed: {$e->getMessage()}", 400);
                }
            }
        } else {
            // No Content-Range, append to end
            $stream = fopen('php://input', 'r');
            try {
                $this->blobStorageService->appendChunkFromStream($upload, $stream);
            } finally {
                fclose($stream);
            }
        }

        $upload->refresh();

        return response('', 202, [
            'Location' => url("/v2/{$name}/blobs/uploads/{$upload->id}"),
            'Docker-Upload-UUID' => $upload->id,
            'Range' => '0-'.($upload->uploaded_bytes - 1),
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    /**
     * Complete a blob upload.
     * PUT /v2/{name}/blobs/uploads/{uuid}
     */
    public function complete(Request $request, string $name, string $uuid): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $upload = $this->blobStorageService->getUpload($uuid);

        if (! $upload || $upload->docker_repository_id !== $repository->id) {
            return $this->errorResponse('BLOB_UPLOAD_UNKNOWN', "blob upload unknown to registry: {$uuid}", 404);
        }

        if (! $upload->isActive()) {
            return $this->errorResponse('BLOB_UPLOAD_INVALID', "blob upload is no longer active: {$uuid}", 400);
        }

        // Handle final chunk if present
        $content = $request->getContent();
        if ($content) {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $content);
            rewind($stream);

            try {
                $this->blobStorageService->appendChunkFromStream($upload, $stream);
            } finally {
                fclose($stream);
            }
        }

        // Get digest from query string
        $digest = $request->query('digest');
        if (! $digest) {
            return $this->errorResponse('DIGEST_INVALID', 'digest is required to complete upload', 400);
        }

        if (! $this->digestService->validate($digest)) {
            return $this->errorResponse('DIGEST_INVALID', "invalid digest format: {$digest}", 400);
        }

        try {
            $blob = $this->blobStorageService->completeChunkedUpload($upload, $digest);

            return response('', 201, [
                'Location' => url("/v2/{$name}/blobs/{$blob->digest}"),
                'Docker-Content-Digest' => $blob->digest,
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('DIGEST_INVALID', "upload completion failed: {$e->getMessage()}", 400);
        }
    }

    /**
     * Cancel a blob upload.
     * DELETE /v2/{name}/blobs/uploads/{uuid}
     */
    public function cancel(Request $request, string $name, string $uuid): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $upload = $this->blobStorageService->getUpload($uuid);

        if (! $upload || $upload->docker_repository_id !== $repository->id) {
            return $this->errorResponse('BLOB_UPLOAD_UNKNOWN', "blob upload unknown to registry: {$uuid}", 404);
        }

        $this->blobStorageService->cancelUpload($upload);

        return response('', 204, [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    /**
     * Get upload status.
     * GET /v2/{name}/blobs/uploads/{uuid}
     */
    public function status(Request $request, string $name, string $uuid): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $upload = $this->blobStorageService->getUpload($uuid);

        if (! $upload || $upload->docker_repository_id !== $repository->id) {
            return $this->errorResponse('BLOB_UPLOAD_UNKNOWN', "blob upload unknown to registry: {$uuid}", 404);
        }

        return response('', 204, [
            'Location' => url("/v2/{$name}/blobs/uploads/{$upload->id}"),
            'Docker-Upload-UUID' => $upload->id,
            'Range' => '0-'.($upload->uploaded_bytes > 0 ? $upload->uploaded_bytes - 1 : 0),
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    private function handleMount(DockerRepository $repository, string $digest, string $fromName): Response|JsonResponse
    {
        $fromRepository = DockerRepository::where('name', $fromName)->where('enabled', true)->first();

        if (! $fromRepository) {
            // Fall back to starting a regular upload
            $upload = $this->blobStorageService->initChunkedUpload($repository);

            return response('', 202, [
                'Location' => url("/v2/{$repository->name}/blobs/uploads/{$upload->id}"),
                'Docker-Upload-UUID' => $upload->id,
                'Range' => '0-0',
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        $mounted = $this->blobStorageService->mountBlob($digest, $fromRepository, $repository);

        if ($mounted) {
            // Log mount activity
            DockerActivity::logMount($repository, $digest);

            return response('', 201, [
                'Location' => url("/v2/{$repository->name}/blobs/{$digest}"),
                'Docker-Content-Digest' => $digest,
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        // Mount failed, start regular upload
        $upload = $this->blobStorageService->initChunkedUpload($repository);

        return response('', 202, [
            'Location' => url("/v2/{$repository->name}/blobs/uploads/{$upload->id}"),
            'Docker-Upload-UUID' => $upload->id,
            'Range' => '0-0',
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    private function handleMonolithicUpload(DockerRepository $repository, Request $request, string $digest): Response|JsonResponse
    {
        if (! $this->digestService->validate($digest)) {
            return $this->errorResponse('DIGEST_INVALID', "invalid digest format: {$digest}", 400);
        }

        $content = $request->getContent();

        if (! $this->digestService->verify($content, $digest)) {
            return $this->errorResponse('DIGEST_INVALID', 'content digest does not match provided digest', 400);
        }

        $contentType = $request->header('Content-Type', 'application/octet-stream');

        try {
            $blob = $this->blobStorageService->storeBlob($digest, $content, $contentType);
            $this->blobStorageService->linkBlobToRepository($blob, $repository);

            return response('', 201, [
                'Location' => url("/v2/{$repository->name}/blobs/{$blob->digest}"),
                'Docker-Content-Digest' => $blob->digest,
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('BLOB_UPLOAD_INVALID', "upload failed: {$e->getMessage()}", 400);
        }
    }

    private function getRepository(string $name): ?DockerRepository
    {
        return DockerRepository::where('name', $name)->first();
    }

    private function getOrCreateRepository(string $name): ?DockerRepository
    {
        if (! $this->isValidRepositoryName($name)) {
            return null;
        }

        return DockerRepository::firstOrCreate(
            ['name' => $name],
            [
                'visibility' => RepositoryVisibility::PrivateRepo,
                'enabled' => true,
            ]
        );
    }

    private function isValidRepositoryName(string $name): bool
    {
        return preg_match('/^[a-z0-9]+([._\/-][a-z0-9]+)*$/', $name) === 1;
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'code' => $code,
                    'message' => $message,
                    'detail' => [],
                ],
            ],
        ], $status, [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }
}
