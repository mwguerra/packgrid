<?php

namespace App\Http\Controllers;

use App\Services\NpmMetadataStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NpmMetadataController extends Controller
{
    /**
     * Get package metadata for a non-scoped package.
     */
    public function show(Request $request, NpmMetadataStore $store, string $package): JsonResponse
    {
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
    public function showScoped(Request $request, NpmMetadataStore $store, string $scope, string $package): JsonResponse
    {
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
    public function showScopedEncoded(Request $request, NpmMetadataStore $store, string $scopedPackage): JsonResponse
    {
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
}
