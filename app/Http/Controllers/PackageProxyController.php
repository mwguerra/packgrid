<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Services\GitHubClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageProxyController extends Controller
{
    public function __construct(private readonly GitHubClient $client) {}

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

        $response = $this->client->downloadZipball($fullName, $ref, $repository->credential);

        $filename = $owner.'-'.$repo.'-'.$ref.'.zip';

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
