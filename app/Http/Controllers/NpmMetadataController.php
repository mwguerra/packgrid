<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Services\NpmMetadataStore;
use App\Services\RepositoryAutosyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NpmMetadataController extends Controller
{
    /**
     * Get package metadata for a non-scoped package.
     */
    public function show(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $package): JsonResponse
    {
        $this->refreshNpm($autosync);

        $metadata = $store->readPackage($package);

        if ($metadata === null) {
            return response()->json([
                'error' => 'Not found',
            ], 404);
        }

        return response()->json($metadata);
    }

    /**
     * Get package metadata for a scoped package (@scope/package).
     */
    public function showScoped(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $scope, string $package): JsonResponse
    {
        $this->refreshNpm($autosync);

        $fullPackageName = '@'.$scope.'/'.$package;
        $metadata = $store->readPackage($fullPackageName);

        if ($metadata === null) {
            return response()->json([
                'error' => 'Not found',
            ], 404);
        }

        return response()->json($metadata);
    }

    /**
     * Get package metadata for a scoped package with URL-encoded name.
     * npm sends @scope%2fpackage (URL-encoded slash) as a single path segment.
     */
    public function showScopedEncoded(Request $request, NpmMetadataStore $store, RepositoryAutosyncService $autosync, string $scopedPackage): JsonResponse
    {
        $this->refreshNpm($autosync);

        // URL decode the package name (e.g., @mwguerra%2fnpm-public-test -> @mwguerra/npm-public-test)
        $fullPackageName = urldecode($scopedPackage);

        // Validate it looks like a scoped package
        if (! str_starts_with($fullPackageName, '@') || ! str_contains($fullPackageName, '/')) {
            return response()->json([
                'error' => 'Invalid scoped package name',
            ], 400);
        }

        $metadata = $store->readPackage($fullPackageName);

        if ($metadata === null) {
            return response()->json([
                'error' => 'Not found',
            ], 404);
        }

        return response()->json($metadata);
    }

    private function refreshNpm(RepositoryAutosyncService $autosync): void
    {
        $autosync->refreshIndex(PackageFormat::Npm);
    }
}
