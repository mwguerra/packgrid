<?php

namespace App\Http\Controllers\Docker;

use App\Http\Controllers\Controller;
use App\Models\DockerActivity;
use App\Models\DockerRepository;
use App\Services\Docker\BlobStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlobController extends Controller
{
    public function __construct(
        private readonly BlobStorageService $blobStorageService
    ) {}

    /**
     * Download a blob.
     * GET /v2/{name}/blobs/{digest}
     */
    public function show(Request $request, string $name, string $digest): StreamedResponse|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedForDockerRepository($repository)) {
            return $this->errorResponse('DENIED', 'token is not authorized for this repository', 403);
        }

        $blob = $this->blobStorageService->getBlob($digest);

        if (! $blob) {
            return $this->errorResponse('BLOB_UNKNOWN', "blob unknown to registry: {$digest}", 404);
        }

        // Verify blob is linked to this repository
        if (! $repository->blobs()->where('docker_blob_id', $blob->id)->exists()) {
            return $this->errorResponse('BLOB_UNKNOWN', "blob unknown to registry: {$digest}", 404);
        }

        // Log pull activity
        DockerActivity::logPull($repository, null, $digest, $blob->size);

        return $this->blobStorageService->streamBlob($blob);
    }

    /**
     * Check if a blob exists (HEAD request).
     * HEAD /v2/{name}/blobs/{digest}
     */
    public function head(Request $request, string $name, string $digest): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return response('', 404, [
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedForDockerRepository($repository)) {
            return $this->errorResponse('DENIED', 'token is not authorized for this repository', 403);
        }

        $blob = $this->blobStorageService->getBlob($digest);

        if (! $blob) {
            return response('', 404, [
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        // Verify blob is linked to this repository
        if (! $repository->blobs()->where('docker_blob_id', $blob->id)->exists()) {
            return response('', 404, [
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        return response('', 200, [
            'Content-Type' => $blob->media_type?->value ?? 'application/octet-stream',
            'Content-Length' => $blob->size,
            'Docker-Content-Digest' => $blob->digest,
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    /**
     * Delete a blob.
     * DELETE /v2/{name}/blobs/{digest}
     */
    public function destroy(Request $request, string $name, string $digest): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedForDockerRepository($repository)) {
            return $this->errorResponse('DENIED', 'token is not authorized for this repository', 403);
        }

        $blob = $this->blobStorageService->getBlob($digest);

        if (! $blob) {
            return $this->errorResponse('BLOB_UNKNOWN', "blob unknown to registry: {$digest}", 404);
        }

        // Only unlink from this repository, don't delete the actual blob
        // (other repositories might reference it)
        $this->blobStorageService->unlinkBlobFromRepository($blob, $repository);

        // Log activity
        DockerActivity::logDelete($repository, null, $digest);

        return response('', 202, [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    private function getRepository(string $name): ?DockerRepository
    {
        return DockerRepository::where('name', $name)->where('enabled', true)->first();
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
