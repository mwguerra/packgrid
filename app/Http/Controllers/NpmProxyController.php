<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\GitProviderClientFactory;
use App\Services\RepositorySyncService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NpmProxyController extends Controller
{
    public function __construct(private readonly GitProviderClientFactory $clientFactory) {}

    public function download(Request $request, string $owner, string $repo, string $ref): StreamedResponse
    {
        $fullName = $owner.'/'.$repo;

        $repository = Repository::query()
            ->where('repo_full_name', $fullName)
            ->where('format', PackageFormat::Npm)
            ->where('enabled', true)
            ->first();

        if (! $repository) {
            abort(404, 'NPM package repository not found or not enabled.');
        }

        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedForRepository($repository)) {
            abort(403, __('token.error.repository_access_denied'));
        }

        // Remove .tgz extension if present
        $ref = preg_replace('/\.tgz$/', '', $ref);

        if ($repository->needsSync()) {
            try {
                app(RepositorySyncService::class)->sync($repository);
            } catch (\Throwable) {
                // Sync failure should not block the download
            }
        }

        $client = $this->clientFactory->forCredential($repository->credential);
        $response = $client->downloadTar($fullName, $ref);

        DownloadLog::logDownload($repository, $ref, PackageFormat::Npm, $token);

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, $owner.'-'.$repo.'-'.$ref.'.tgz', [
            'Content-Type' => 'application/gzip',
        ]);
    }
}
