<?php

namespace App\Services;

use App\Adapters\ComposerAdapter;
use App\Adapters\NpmAdapter;
use App\Contracts\FormatAdapterInterface;
use App\Enums\PackageFormat;
use App\Models\Credential;
use InvalidArgumentException;

class AdapterFactory
{
    public function __construct(private readonly GitProviderClientFactory $clientFactory) {}

    public function make(PackageFormat $format, ?Credential $credential = null): FormatAdapterInterface
    {
        $client = $this->clientFactory->forCredential($credential);

        return match ($format) {
            PackageFormat::Composer => new ComposerAdapter($client),
            PackageFormat::Npm => new NpmAdapter($client),
        };
    }

    public function makeFromString(string $format, ?Credential $credential = null): FormatAdapterInterface
    {
        $packageFormat = PackageFormat::tryFrom($format);

        if ($packageFormat === null) {
            throw new InvalidArgumentException("Unknown package format: {$format}");
        }

        return $this->make($packageFormat, $credential);
    }
}
