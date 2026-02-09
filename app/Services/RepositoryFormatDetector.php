<?php

namespace App\Services;

use App\Enums\PackageFormat;
use App\Models\Credential;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class RepositoryFormatDetector
{
    public function __construct(private readonly GitHubClient $client) {}

    /**
     * Detect the package format by checking for manifest files.
     *
     * @throws RuntimeException if no valid manifest is found
     */
    public function detect(string $fullName, ?Credential $credential = null): PackageFormat
    {
        $defaultBranch = $this->getDefaultBranch($fullName, $credential);

        // Try Composer first (more common in this ecosystem)
        if ($this->hasFile($fullName, 'composer.json', $defaultBranch, $credential)) {
            return PackageFormat::Composer;
        }

        // Try NPM
        if ($this->hasFile($fullName, 'package.json', $defaultBranch, $credential)) {
            return PackageFormat::Npm;
        }

        throw new RuntimeException(
            __('repository.validation.no_manifest', ['repo' => $fullName])
        );
    }

    private function getDefaultBranch(string $fullName, ?Credential $credential): string
    {
        $repo = $this->client->getRepository($fullName, $credential);

        return $repo['default_branch'] ?? 'main';
    }

    private function hasFile(string $fullName, string $path, string $ref, ?Credential $credential): bool
    {
        try {
            $this->client->getFileContent($fullName, $path, $ref, $credential);

            return true;
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return false;
            }
            throw $e;
        }
    }
}
