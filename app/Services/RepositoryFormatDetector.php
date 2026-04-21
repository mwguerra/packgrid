<?php

namespace App\Services;

use App\Contracts\GitProviderClientInterface;
use App\Enums\PackageFormat;
use App\Models\Credential;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class RepositoryFormatDetector
{
    public function __construct(private readonly GitProviderClientFactory $clientFactory) {}

    /**
     * Detect the package format by checking for manifest files.
     *
     * @throws RuntimeException if no valid manifest is found
     */
    public function detect(string $fullName, ?Credential $credential = null): PackageFormat
    {
        $client = $this->clientFactory->forCredential($credential);
        $info = $client->getRepositoryInfo($fullName);

        if ($this->hasFile($client, $fullName, 'composer.json', $info->defaultBranch)) {
            return PackageFormat::Composer;
        }

        if ($this->hasFile($client, $fullName, 'package.json', $info->defaultBranch)) {
            return PackageFormat::Npm;
        }

        throw new RuntimeException(
            __('repository.validation.no_manifest', ['repo' => $fullName])
        );
    }

    private function hasFile(
        GitProviderClientInterface $client,
        string $fullName,
        string $path,
        string $ref
    ): bool {
        try {
            $client->getFileContent($fullName, $path, $ref);

            return true;
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return false;
            }
            throw $e;
        }
    }
}
