<?php

namespace App\Http\Controllers\Docker;

use App\Http\Controllers\Controller;
use App\Models\DockerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    /**
     * List all tags for a repository.
     * GET /v2/{name}/tags/list
     */
    public function __invoke(Request $request, string $name): JsonResponse
    {
        $repository = DockerRepository::where('name', $name)->where('enabled', true)->first();

        if (! $repository) {
            return $this->errorResponse('NAME_UNKNOWN', "repository name not known to registry: {$name}", 404);
        }

        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedForDockerRepository($repository)) {
            return $this->errorResponse('DENIED', 'token is not authorized for this repository', 403);
        }

        $n = (int) $request->query('n', 100);
        $last = $request->query('last');

        $query = $repository->tags()->orderBy('name');

        if ($last) {
            $query->where('name', '>', $last);
        }

        $tags = $query->limit($n)->pluck('name')->toArray();

        $headers = [
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ];

        // Add Link header for pagination if there are more results
        if (count($tags) === $n) {
            $lastTag = end($tags);
            $linkUrl = url("/v2/{$name}/tags/list?n={$n}&last=".urlencode($lastTag));
            $headers['Link'] = "<{$linkUrl}>; rel=\"next\"";
        }

        return response()->json([
            'name' => $name,
            'tags' => $tags,
        ], 200, $headers);
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
