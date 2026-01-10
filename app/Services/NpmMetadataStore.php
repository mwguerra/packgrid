<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class NpmMetadataStore
{
    /**
     * Read package metadata for NPM package.
     * Handles both scoped (@scope/package) and non-scoped packages.
     */
    public function readPackage(string $packageName): ?array
    {
        $path = $this->packagePath($packageName);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }

    /**
     * Write package metadata for NPM package.
     */
    public function writePackage(string $packageName, array $payload): void
    {
        $directory = $this->packageDirectory($packageName);
        $this->ensureDirectory($directory);

        Storage::disk('local')->put(
            $this->packagePath($packageName),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Write repository metadata (cached sync data).
     */
    public function writeRepositoryMetadata(string $repositoryId, array $payload): void
    {
        $this->ensureDirectory($this->basePath().'/npm-repositories');

        Storage::disk('local')->put(
            $this->repositoryMetadataPath($repositoryId),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Read repository metadata.
     */
    public function readRepositoryMetadata(string $repositoryId): ?array
    {
        $path = $this->repositoryMetadataPath($repositoryId);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }

    /**
     * Get the path for a package's metadata file.
     * Handles scoped packages by replacing @ with _at_ and / with _slash_.
     */
    public function packagePath(string $packageName): string
    {
        $safeName = $this->sanitizePackageName($packageName);

        return $this->basePath().'/npm/'.$safeName.'.json';
    }

    /**
     * Get the directory for a package's metadata.
     */
    public function packageDirectory(string $packageName): string
    {
        $safeName = $this->sanitizePackageName($packageName);

        // For scoped packages, we need to handle the directory structure
        if (str_contains($safeName, '_slash_')) {
            $parts = explode('_slash_', $safeName);

            return $this->basePath().'/npm/'.$parts[0];
        }

        return $this->basePath().'/npm';
    }

    /**
     * Sanitize package name for filesystem storage.
     *
     * @scope/package becomes _at_scope_slash_package
     */
    public function sanitizePackageName(string $packageName): string
    {
        $name = str_replace('@', '_at_', $packageName);
        $name = str_replace('/', '_slash_', $name);

        return $name;
    }

    /**
     * Restore original package name from sanitized version.
     */
    public function unsanitizePackageName(string $sanitized): string
    {
        $name = str_replace('_at_', '@', $sanitized);
        $name = str_replace('_slash_', '/', $name);

        return $name;
    }

    public function basePath(): string
    {
        return trim(config('packgrid.storage_path'), '/');
    }

    public function repositoryMetadataPath(string $repositoryId): string
    {
        return $this->basePath().'/npm-repositories/'.$repositoryId.'.json';
    }

    private function ensureDirectory(string $path): void
    {
        Storage::disk('local')->makeDirectory(trim($path, '/'));
    }
}
