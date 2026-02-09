<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PackageMetadataStore
{
    public function readPackagesIndex(): array
    {
        $path = $this->packagesIndexPath();

        if (! Storage::disk('local')->exists($path)) {
            return ['packages' => []];
        }

        return json_decode(Storage::disk('local')->get($path), true) ?? ['packages' => []];
    }

    public function readPackage(string $vendor, string $package): ?array
    {
        $path = $this->packagePath($vendor, $package);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }

    public function writePackagesIndex(array $payload): void
    {
        $this->ensureDirectory($this->basePath());

        Storage::disk('local')->put($this->packagesIndexPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function writePackage(string $vendor, string $package, array $payload): void
    {
        $this->ensureDirectory($this->basePath().'/'.trim(config('packgrid.packages_prefix'), '/').'/'.$vendor);

        Storage::disk('local')->put($this->packagePath($vendor, $package), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function writeRepositoryMetadata(string $repositoryId, array $payload): void
    {
        $this->ensureDirectory($this->basePath().'/repositories');

        Storage::disk('local')->put($this->repositoryMetadataPath($repositoryId), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function readRepositoryMetadata(string $repositoryId): ?array
    {
        $path = $this->repositoryMetadataPath($repositoryId);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }

    public function packagesIndexPath(): string
    {
        return $this->basePath().'/'.config('packgrid.packages_index');
    }

    public function packagePath(string $vendor, string $package): string
    {
        return $this->basePath().'/'.trim(config('packgrid.packages_prefix'), '/').'/'.$vendor.'/'.$package.'.json';
    }

    public function basePath(): string
    {
        return trim(config('packgrid.storage_path'), '/');
    }

    public function repositoryMetadataPath(string $repositoryId): string
    {
        return $this->basePath().'/repositories/'.$repositoryId.'.json';
    }

    private function ensureDirectory(string $path): void
    {
        Storage::disk('local')->makeDirectory(trim($path, '/'));
    }
}
