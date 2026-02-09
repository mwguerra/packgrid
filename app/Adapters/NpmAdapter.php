<?php

namespace App\Adapters;

use App\Contracts\FormatAdapterInterface;
use App\Enums\PackageFormat;
use App\Models\Credential;
use App\Models\Repository;
use App\Services\GitHubClient;
use Illuminate\Support\Str;
use RuntimeException;

class NpmAdapter implements FormatAdapterInterface
{
    public function __construct(private readonly GitHubClient $client) {}

    public function getFormat(): PackageFormat
    {
        return PackageFormat::Npm;
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
                        "No package.json found in '{$fullName}' at ref '{$ref['ref']}'. ".
                        'This repository is not a valid NPM package.'
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
        $payload = $this->client->getFileContent($fullName, 'package.json', $ref, $credential);

        if (! isset($payload['content'])) {
            throw new RuntimeException('package.json not found for '.$fullName.' at '.$ref);
        }

        $contents = base64_decode($payload['content']);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('package.json could not be decoded for '.$fullName.' at '.$ref);
        }

        return $decoded;
    }

    public function getPackageName(array $manifest, string $fallbackName): string
    {
        if (isset($manifest['name']) && is_string($manifest['name'])) {
            return $manifest['name'];
        }

        // NPM package names are lowercase with optional @scope/
        $fallbackName = Str::of($fallbackName)->lower();
        $parts = explode('/', $fallbackName);

        if (count($parts) === 2) {
            // Convert owner/repo to @owner/repo format for scoped packages
            return '@'.$parts[0].'/'.$parts[1];
        }

        return Str::slug($fallbackName);
    }

    public function normalizeVersion(string $ref, string $type): string
    {
        if ($type === 'branch') {
            // NPM uses 0.0.0-branch-name format for branches
            $sanitized = preg_replace('/[^a-zA-Z0-9.-]/', '-', $ref);

            return '0.0.0-'.$sanitized;
        }

        // Remove 'v' prefix if present (v1.0.0 -> 1.0.0)
        return ltrim($ref, 'vV');
    }

    public function buildDistUrl(string $fullName, string $ref): string
    {
        $parts = explode('/', $fullName);
        $owner = $parts[0] ?? '';
        $repo = $parts[1] ?? '';

        return rtrim(config('app.url'), '/').'/npm/-/'.$owner.'/'.$repo.'/'.$ref.'.tgz';
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

        return [
            'name' => $packageName,
            'version' => $version,
            'description' => $manifest['description'] ?? '',
            'main' => $manifest['main'] ?? 'index.js',
            'scripts' => $manifest['scripts'] ?? [],
            'dependencies' => $manifest['dependencies'] ?? [],
            'devDependencies' => $manifest['devDependencies'] ?? [],
            'peerDependencies' => $manifest['peerDependencies'] ?? [],
            'repository' => [
                'type' => 'git',
                'url' => 'git+'.$repoUrl.'.git',
            ],
            'gitHead' => $sha,
            'dist' => [
                'shasum' => '', // Will be calculated when creating tarball
                'tarball' => $this->buildDistUrl($fullName, $ref),
            ],
            '_id' => $packageName.'@'.$version,
            '_nodeVersion' => '18.0.0',
            '_npmVersion' => '9.0.0',
            '_from' => '.',
            '_resolved' => $this->buildDistUrl($fullName, $ref),
        ];
    }

    /**
     * Build NPM registry-compatible package metadata.
     *
     * @param  array<string, array<string, mixed>>  $versions
     * @return array<string, mixed>
     */
    public function buildRegistryMetadata(string $packageName, array $versions): array
    {
        $sortedVersions = $this->sortVersions(array_keys($versions));
        $latestVersion = $sortedVersions[count($sortedVersions) - 1] ?? '';
        $latestData = $versions[$latestVersion] ?? [];

        $distTags = ['latest' => $latestVersion];

        // Add dev tag for branches
        foreach ($versions as $version => $data) {
            if (str_starts_with($version, '0.0.0-')) {
                $branchName = substr($version, 6);
                if (in_array($branchName, ['main', 'master', 'develop'])) {
                    $distTags[$branchName] = $version;
                }
            }
        }

        return [
            '_id' => $packageName,
            'name' => $packageName,
            'description' => $latestData['description'] ?? '',
            'dist-tags' => $distTags,
            'versions' => $versions,
            'time' => $this->buildTimeData($versions),
            'maintainers' => [],
            'repository' => $latestData['repository'] ?? null,
        ];
    }

    /**
     * Sort versions using semver ordering.
     *
     * @param  array<string>  $versions
     * @return array<string>
     */
    private function sortVersions(array $versions): array
    {
        usort($versions, function ($a, $b) {
            // Handle pre-release versions (0.0.0-*) - they come last
            $aIsPrerelease = str_starts_with($a, '0.0.0-');
            $bIsPrerelease = str_starts_with($b, '0.0.0-');

            if ($aIsPrerelease && ! $bIsPrerelease) {
                return -1;
            }
            if (! $aIsPrerelease && $bIsPrerelease) {
                return 1;
            }

            return version_compare($a, $b);
        });

        return $versions;
    }

    /**
     * Build time metadata for package versions.
     *
     * @param  array<string, array<string, mixed>>  $versions
     * @return array<string, string>
     */
    private function buildTimeData(array $versions): array
    {
        $now = now()->toIso8601String();
        $time = [
            'created' => $now,
            'modified' => $now,
        ];

        foreach (array_keys($versions) as $version) {
            $time[$version] = $now;
        }

        return $time;
    }

    private function extractFullName(string $repoUrl): string
    {
        $path = parse_url($repoUrl, PHP_URL_PATH);
        $path = trim($path ?? '', '/');
        $path = preg_replace('/\.git$/', '', $path);

        return $path ?: '';
    }
}
