<?php

namespace App\Adapters;

use App\Contracts\FormatAdapterInterface;
use App\Contracts\GitProviderClientInterface;
use App\DTOs\RefDto;
use App\Enums\PackageFormat;
use App\Models\Repository;
use App\Support\PackgridSettings;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ComposerAdapter implements FormatAdapterInterface
{
    public function __construct(private readonly GitProviderClientInterface $client) {}

    public function getFormat(): PackageFormat
    {
        return PackageFormat::Composer;
    }

    public function buildMetadata(Repository $repository, array $refs): array
    {
        $packages = [];
        $fullName = $repository->repo_full_name;

        foreach ($refs as $ref) {
            /** @var RefDto $ref */
            try {
                $manifest = $this->getManifest($fullName, $ref->name);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response->status() === 404) {
                    throw new RuntimeException(
                        "No composer.json found in '{$fullName}' at ref '{$ref->name}'. ".
                        'This repository is not a valid Composer package.'
                    );
                }
                throw $e;
            }

            $packageName = $this->getPackageName($manifest, $fullName);
            $version = $this->normalizeVersion($ref->name, $ref->type);

            if (! $this->isValidVersion($version)) {
                Log::warning("Skipping invalid Composer version '{$version}' from ref '{$ref->name}' in '{$fullName}'");

                continue;
            }

            $packages[$packageName][$version] = $this->buildVersionData(
                $manifest, $packageName, $version, $repository->url, $ref->name, $ref->sha
            );
        }

        return $packages;
    }

    public function getManifest(string $fullName, string $ref): array
    {
        $dto = $this->client->getFileContent($fullName, 'composer.json', $ref);
        $decoded = json_decode($dto->content, true);

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

    public function isValidVersion(string $version): bool
    {
        try {
            (new VersionParser)->normalize($version);

            return true;
        } catch (\UnexpectedValueException) {
            return false;
        }
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
        $fullName = $this->extractFullName($repoUrl);

        return array_merge($manifest, [
            'name' => $packageName,
            'version' => $version,
            'source' => [
                'type' => 'git',
                'url' => PackgridSettings::gitEnabled()
                    ? rtrim(config('app.url'), '/').'/git/'.$fullName.'.git'
                    : $repoUrl,
                'reference' => $sha,
            ],
            'dist' => [
                'type' => 'zip',
                'url' => $this->buildDistUrl($fullName, $ref),
                'reference' => $sha,
            ],
        ]);
    }

    private function extractFullName(string $repoUrl): string
    {
        $path = parse_url($repoUrl, PHP_URL_PATH);
        $path = trim($path ?? '', '/');

        return preg_replace('/\.git$/', '', $path) ?: '';
    }
}
