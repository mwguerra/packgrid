<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\GitProviderClientFactory;
use App\Services\RepositorySyncService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageProxyController extends Controller
{
    public function __construct(private readonly GitProviderClientFactory $clientFactory) {}

    public function download(Request $request, string $owner, string $repo, string $ref): StreamedResponse
    {
        $fullName = $owner.'/'.$repo;

        $repository = Repository::query()
            ->where('repo_full_name', $fullName)
            ->where('enabled', true)
            ->first();

        if (! $repository) {
            abort(404, 'Repository not found or not enabled.');
        }

        $token = $request->attributes->get('packgrid_token');
        if ($token && ! $token->isAllowedForRepository($repository)) {
            abort(403, __('token.error.repository_access_denied'));
        }

        if ($repository->needsSync()) {
            try {
                app(RepositorySyncService::class)->sync($repository);
            } catch (\Throwable) {
                // Sync failure should not block the download
            }
        }

        $client = $this->clientFactory->forCredential($repository->credential);
        $response = $client->downloadZip($fullName, $ref);

        DownloadLog::logDownload($repository, $ref, PackageFormat::Composer, $token);

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, $owner.'-'.$repo.'-'.$ref.'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
