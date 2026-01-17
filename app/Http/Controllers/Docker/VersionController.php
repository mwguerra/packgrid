<?php

namespace App\Http\Controllers\Docker;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    /**
     * Handle the Docker Registry API version check.
     * GET /v2/
     *
     * Returns 200 OK if the registry supports v2 API.
     * This endpoint does not require authentication per the OCI spec.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([], 200, [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }
}
