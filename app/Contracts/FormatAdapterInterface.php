<?php

namespace App\Contracts;

use App\Enums\PackageFormat;
use App\Models\Repository;

interface FormatAdapterInterface
{
    /**
     * Get the package format this adapter handles.
     */
    public function getFormat(): PackageFormat;

    /**
     * Build package metadata from repository refs.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function buildMetadata(Repository $repository, array $refs): array;

    /**
     * Get the manifest file content from the repository at a specific ref.
     *
     * @return array<string, mixed>
     */
    public function getManifest(string $fullName, string $ref, ?\App\Models\Credential $credential): array;

    /**
     * Get the package name from manifest data.
     */
    public function getPackageName(array $manifest, string $fallbackName): string;

    /**
     * Normalize version string for this format.
     */
    public function normalizeVersion(string $ref, string $type): string;

    /**
     * Build the distribution URL for a package version.
     */
    public function buildDistUrl(string $fullName, string $ref): string;

    /**
     * Build version data structure for the package index.
     *
     * @return array<string, mixed>
     */
    public function buildVersionData(
        array $manifest,
        string $packageName,
        string $version,
        string $repoUrl,
        string $ref,
        string $sha
    ): array;
}
