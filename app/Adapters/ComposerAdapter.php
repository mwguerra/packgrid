<?php

namespace App\Adapters;

use App\Contracts\FormatAdapterInterface;
use App\Enums\PackageFormat;
use App\Models\Credential;
use App\Models\Repository;
use App\Services\GitHubClient;
use Illuminate\Support\Str;
use RuntimeException;

class ComposerAdapter implements FormatAdapterInterface
{
    public function __construct(private readonly GitHubClient $client) {}

    public function getFormat(): PackageFormat
    {
        return PackageFormat::Composer;
    }

    public function buildMetadata(Repository $repository, array $refs): array
    {
        $packages = [];
        $fullName = $repository->repo_full_name;
        $credential = $repository->credential;

        foreach ($refs as $ref) {
            try {
                $manifest = $this->getManifest($fullName, $ref['ref'], $credential);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response->status() === 404) {
                    throw new RuntimeException(
                        "No composer.json found in '{$fullName}' at ref '{$ref['ref']}'. ".
                        'This repository is not a valid Composer package.'
                    );
                }
                throw $e;
            }

            $packageName = $this->getPackageName($manifest, $fullName);
            $version = $this->normalizeVersion($ref['ref'], $ref['type']);

            $versionData = $this->buildVersionData(
                $manifest,
                $packageName,
                $version,
                $repository->url,
                $ref['ref'],
                $ref['sha']
            );

            $packages[$packageName][$version] = $versionData;
        }

        return $packages;
    }

    public function getManifest(string $fullName, string $ref, ?Credential $credential): array
    {
        $payload = $this->client->getFileContent($fullName, 'composer.json', $ref, $credential);

        if (! isset($payload['content'])) {
            throw new RuntimeException('composer.json not found for '.$fullName.' at '.$ref);
        }

        $contents = base64_decode($payload['content']);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('composer.json could not be decoded for '.$fullName.' at '.$ref);
        }

        return $decoded;
    }

    public function getPackageName(array $manifest, string $fallbackName): string
    {
        if (isset($manifest['name']) && is_string($manifest['name'])) {
            return $manifest['name'];
        }

        $fallbackName = Str::of($fallbackName)->lower();
        $parts = explode('/', $fallbackName);

        if (count($parts) === 2) {
            return $parts[0].'/'.$parts[1];
        }

        return Str::slug($fallbackName);
    }

    public function normalizeVersion(string $ref, string $type): string
    {
        return $type === 'branch' ? 'dev-'.$ref : $ref;
    }

    public function buildDistUrl(string $fullName, string $ref): string
    {
        return rtrim(config('app.url'), '/').'/dist/'.$fullName.'/'.$ref.'.zip';
    }

    public function buildVersionData(
        array $manifest,
        string $packageName,
        string $version,
        string $repoUrl,
        string $ref,
        string $sha
    ): array {
        return array_merge($manifest, [
            'name' => $packageName,
            'version' => $version,
            'source' => [
                'type' => 'git',
                'url' => $repoUrl,
                'reference' => $sha,
            ],
            'dist' => [
                'type' => 'zip',
                'url' => $this->buildDistUrl(
                    $this->extractFullName($repoUrl),
                    $ref
                ),
                'reference' => $sha,
            ],
        ]);
    }

    private function extractFullName(string $repoUrl): string
    {
        // Extract owner/repo from URL like https://github.com/owner/repo
        $path = parse_url($repoUrl, PHP_URL_PATH);
        $path = trim($path ?? '', '/');
        $path = preg_replace('/\.git$/', '', $path);

        return $path ?: '';
    }
}
