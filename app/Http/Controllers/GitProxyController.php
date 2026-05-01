<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\GitProviderClientFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GitProxyController extends Controller
{
    public function __construct(private readonly GitProviderClientFactory $clientFactory) {}

    public function infoRefs(Request $request, string $owner, string $repo): StreamedResponse
    {
        $service = $request->query('service');
        if ($service !== 'git-upload-pack') {
            abort(403, 'Only git-upload-pack is supported (read-only proxy).');
        }

        $repository = $this->findRepository($owner, $repo);
        $this->authorizeClone($request, $repository);

        $gitUrl = $this->upstreamBase($repository).'/info/refs?service=git-upload-pack';
        $response = $this->gitRequest($repository)->get($gitUrl);

        if ($response->failed()) {
            Log::error('Git proxy: upstream info/refs failed', [
                'repo' => "{$owner}/{$repo}",
                'status' => $response->status(),
            ]);
            abort($response->status(), 'Upstream request failed.');
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

        $gitUrl = $this->upstreamBase($repository).'/git-upload-pack';
        $response = $this->gitRequest($repository)
            ->withBody($request->getContent(), 'application/x-git-upload-pack-request')
            ->post($gitUrl);

        if ($response->failed()) {
            Log::error('Git proxy: upstream upload-pack failed', [
                'repo' => "{$owner}/{$repo}",
                'status' => $response->status(),
            ]);
            abort($response->status(), 'Upstream request failed.');
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

    private function upstreamBase(Repository $repository): string
    {
        return rtrim($repository->url, '/').'.git';
    }

    private function gitRequest(Repository $repository): PendingRequest
    {
        $request = Http::timeout(120)->withHeaders(['User-Agent' => 'Packgrid']);

        $credentials = $this->clientFactory
            ->forCredential($repository->credential)
            ->getHttpGitCredentials();

        if ($credentials) {
            [$username, $password] = $credentials;
            $request = $request->withBasicAuth($username, $password);
        }

        return $request;
    }

    private function findRepository(string $owner, string $repo): Repository
    {
        $repository = Repository::query()
            ->where('repo_full_name', $owner.'/'.$repo)
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
}
