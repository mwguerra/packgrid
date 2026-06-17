<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Services\PackageMetadataStore;
use App\Services\RepositoryAutosyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageMetadataController extends Controller
{
    public function index(PackageMetadataStore $store, RepositoryAutosyncService $autosync): JsonResponse
    {
        $autosync->refreshIndex(PackageFormat::Composer);

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
