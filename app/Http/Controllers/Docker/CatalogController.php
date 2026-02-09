<?php

namespace App\Http\Controllers\Docker;

use App\Http\Controllers\Controller;
use App\Models\DockerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * List all repositories in the registry.
     * GET /v2/_catalog
     */
    public function __invoke(Request $request): JsonResponse
    {
        $n = (int) $request->query('n', 100);
        $last = $request->query('last');

        $query = DockerRepository::where('enabled', true)->orderBy('name');

        if ($last) {
            $query->where('name', '>', $last);
        }

        $repositories = $query->limit($n)->pluck('name')->toArray();

        $headers = [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ];

        // Add Link header for pagination if there are more results
        if (count($repositories) === $n) {
            $lastRepo = end($repositories);
            $linkUrl = url("/v2/_catalog?n={$n}&last=".urlencode($lastRepo));
            $headers['Link'] = "<{$linkUrl}>; rel=\"next\"";
        }

        return response()->json([
            'repositories' => $repositories,
        ], 200, $headers);
    }
}
