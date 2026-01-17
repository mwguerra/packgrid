<?php

namespace App\Http\Controllers\Docker;

use App\Enums\DockerMediaType;
use App\Enums\RepositoryVisibility;
use App\Http\Controllers\Controller;
use App\Models\DockerActivity;
use App\Models\DockerRepository;
use App\Services\Docker\ManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ManifestController extends Controller
{
    public function __construct(
        private readonly ManifestService $manifestService
    ) {}

    /**
     * Get a manifest by reference (tag or digest).
     * GET /v2/{name}/manifests/{reference}
     */
    public function show(Request $request, string $name, string $reference): JsonResponse|Response
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $manifest = $this->manifestService->getManifest($repository, $reference);

        if (! $manifest) {
            return $this->errorResponse('MANIFEST_UNKNOWN', "manifest unknown to registry: {$reference}", 404);
        }

        // Log pull activity
        DockerActivity::logPull($repository, $this->isTag($reference) ? $reference : null, $manifest->digest, $manifest->size);
        $repository->incrementPullCount();

        return response($manifest->content, 200, [
            'Content-Type' => $manifest->media_type->value,
            'Content-Length' => strlen($manifest->content),
            'Docker-Content-Digest' => $manifest->digest,
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    /**
     * Check if a manifest exists (HEAD request).
     * HEAD /v2/{name}/manifests/{reference}
     */
    public function head(Request $request, string $name, string $reference): Response
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return response('', 404, [
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        $manifest = $this->manifestService->getManifest($repository, $reference);

        if (! $manifest) {
            return response('', 404, [
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        }

        return response('', 200, [
            'Content-Type' => $manifest->media_type->value,
            'Content-Length' => strlen($manifest->content),
            'Docker-Content-Digest' => $manifest->digest,
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    /**
     * Upload a manifest.
     * PUT /v2/{name}/manifests/{reference}
     */
    public function store(Request $request, string $name, string $reference): JsonResponse|Response
    {
        $repository = $this->getOrCreateRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_INVALID', "invalid repository name: {$name}", 400);
        }

        if (! $repository->enabled) {
            return $this->errorResponse('DENIED', "repository is disabled: {$name}", 403);
        }

        $content = $request->getContent();
        $contentType = $request->header('Content-Type', DockerMediaType::ManifestV2->value);

        // Validate content type
        if (! in_array($contentType, DockerMediaType::acceptableManifestTypes())) {
            return $this->errorResponse('MANIFEST_INVALID', "manifest invalid: unsupported content type: {$contentType}", 400);
        }

        try {
            $manifest = $this->manifestService->storeManifest($repository, $reference, $content, $contentType);

            return response('', 201, [
                'Location' => url("/v2/{$name}/manifests/{$manifest->digest}"),
                'Docker-Content-Digest' => $manifest->digest,
                'Docker-Distribution-Api-Version' => 'registry/2.0',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('MANIFEST_INVALID', "manifest invalid: {$e->getMessage()}", 400);
        }
    }

    /**
     * Delete a manifest.
     * DELETE /v2/{name}/manifests/{reference}
     */
    public function destroy(Request $request, string $name, string $reference): Response|JsonResponse
    {
        $repository = $this->getRepository($name);

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $deleted = $this->manifestService->deleteManifest($repository, $reference);

        if (! $deleted) {
            return $this->errorResponse('MANIFEST_UNKNOWN', "manifest unknown to registry: {$reference}", 404);
        }

        return response('', 202, [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }

    private function getRepository(string $name): ?DockerRepository
    {
        return DockerRepository::where('name', $name)->first();
    }

    private function getOrCreateRepository(string $name): ?DockerRepository
    {
        // Validate repository name format
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
        // Repository name must match pattern: [a-z0-9]+([._-][a-z0-9]+)*(/[a-z0-9]+([._-][a-z0-9]+)*)*
        return preg_match('/^[a-z0-9]+([._\/-][a-z0-9]+)*$/', $name) === 1;
    }

    private function isTag(string $reference): bool
    {
        return ! str_contains($reference, ':');
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
