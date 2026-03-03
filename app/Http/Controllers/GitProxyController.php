<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GitProxyController extends Controller
{
    public function infoRefs(Request $request, string $owner, string $repo): StreamedResponse
    {
        $service = $request->query('service');
        if ($service !== 'git-upload-pack') {
            abort(403, 'Only git-upload-pack is supported (read-only proxy).');
        }

        $repository = $this->findRepository($owner, $repo);
        $this->authorizeClone($request, $repository);

        $credential = $repository->credential;
        $gitUrl = "https://github.com/{$owner}/{$repo}.git/info/refs?service=git-upload-pack";

        $response = $this->githubRequest($credential)
            ->get($gitUrl)
            ->throw();

        return response()->stream(function () use ($response) {
            echo $response->body();
        }, 200, [
            'Content-Type' => 'application/x-git-upload-pack-advertisement',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function uploadPack(Request $request, string $owner, string $repo): StreamedResponse
    {
        $repository = $this->findRepository($owner, $repo);
        $this->authorizeClone($request, $repository);

        $credential = $repository->credential;
        $gitUrl = "https://github.com/{$owner}/{$repo}.git/git-upload-pack";

        $response = $this->githubRequest($credential)
            ->withBody($request->getContent(), 'application/x-git-upload-pack-request')
            ->post($gitUrl)
            ->throw();

        $token = $request->attributes->get('packgrid_token');
        DownloadLog::logDownload($repository, 'clone', PackageFormat::Git, $token);
        $repository->increment('clone_count');

        return response()->stream(function () use ($response) {
            echo $response->body();
        }, 200, [
            'Content-Type' => 'application/x-git-upload-pack-result',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function findRepository(string $owner, string $repo): Repository
    {
        $fullName = $owner.'/'.$repo;

        $repository = Repository::query()
            ->where('repo_full_name', $fullName)
            ->where('enabled', true)
            ->where('clone_enabled', true)
            ->first();

        if (! $repository) {
            abort(404, 'Repository not found, not enabled, or clone not enabled.');
        }

        return $repository;
    }

    private function authorizeClone(Request $request, Repository $repository): void
    {
        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedToCloneRepository($repository)) {
            abort(403, __('token.error.clone_access_denied'));
        }
    }

    private function githubRequest(?\App\Models\Credential $credential): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::withHeaders([
            'User-Agent' => 'Packgrid',
        ]);

        if ($credential?->token) {
            $scheme = str_starts_with($credential->token, 'github_pat_') ? 'Bearer' : 'token';
            $request = $request->withHeaders([
                'Authorization' => $scheme.' '.$credential->token,
            ]);
        }

        return $request;
    }
}
