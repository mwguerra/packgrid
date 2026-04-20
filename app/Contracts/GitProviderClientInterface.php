<?php

namespace App\Contracts;

use App\DTOs\FileContentDto;
use App\DTOs\RefDto;
use App\DTOs\RepositoryInfoDto;
use Illuminate\Http\Client\Response;

interface GitProviderClientInterface
{
    /**
     * Test that the stored credential is valid.
     * Returns raw provider user data (used for display in health checks).
     */
    public function testConnection(): array;

    /**
     * Fetch repository metadata.
     */
    public function getRepositoryInfo(string $fullName): RepositoryInfoDto;

    /**
     * List all tags. Returns RefDto[] with type='tag'.
     */
    public function listTags(string $fullName): array;

    /**
     * List all branches. Returns RefDto[] with type='branch'.
     */
    public function listBranches(string $fullName): array;

    /**
     * Fetch a file from the repository at a specific ref.
     * Content is decoded — not base64.
     */
    public function getFileContent(string $fullName, string $path, string $ref): FileContentDto;

    /**
     * Stream a ZIP archive of the repository at a specific ref.
     */
    public function downloadZip(string $fullName, string $ref): Response;

    /**
     * Stream a TAR.GZ archive of the repository at a specific ref.
     */
    public function downloadTar(string $fullName, string $ref): Response;

    /**
     * Return [username, password] for HTTP Basic Auth when proxying Git clone.
     * Returns null for unauthenticated (public) access.
     */
    public function getHttpGitCredentials(): ?array;
}
