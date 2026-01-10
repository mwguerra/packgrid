<?php

namespace App\Http\Controllers;

use App\Services\PackageMetadataStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageMetadataController extends Controller
{
    public function index(PackageMetadataStore $store): JsonResponse
    {
        return response()->json($store->readPackagesIndex());
    }

    public function show(Request $request, PackageMetadataStore $store, string $vendor, string $package): JsonResponse
    {
        $metadata = $store->readPackage($vendor, $package);

        if ($metadata === null) {
            abort(404);
        }

        return response()->json($metadata);
    }
}
