<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $response = $this->githubRequest($credential)->get($gitUrl);

        if ($response->failed()) {
            Log::error('Git proxy: GitHub info/refs failed', [
                'repo' => "{$owner}/{$repo}",
                'status' => $response->status(),
                'body' => $response->body(),
                'has_credential' => $credential !== null,
            ]);
            abort($response->status(), 'Upstream GitHub request failed.');
        }

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
            ->post($gitUrl);

        if ($response->failed()) {
            Log::error('Git proxy: GitHub upload-pack failed', [
                'repo' => "{$owner}/{$repo}",
                'status' => $response->status(),
                'has_credential' => $credential !== null,
            ]);
            abort($response->status(), 'Upstream GitHub request failed.');
        }

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
        $request = Http::timeout(120)->withHeaders([
            'User-Agent' => 'Packgrid',
        ]);

        if ($credential?->token) {
            $request = $request->withBasicAuth('x-access-token', $credential->token);
        }

        return $request;
    }
}
