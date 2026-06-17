<?php

namespace App\Http\Controllers;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\GitHubClient;
use App\Services\RepositoryAutosyncService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NpmProxyController extends Controller
{
    public function __construct(private readonly GitHubClient $client) {}

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

        app(RepositoryAutosyncService::class)->maybeSync($repository);

        $response = $this->client->downloadTarball($fullName, $ref, $repository->credential);

        DownloadLog::logDownload($repository, $ref, PackageFormat::Npm, $token);

        $filename = $owner.'-'.$repo.'-'.$ref.'.tgz';

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, $filename, [
            'Content-Type' => 'application/gzip',
        ]);
    }
}
