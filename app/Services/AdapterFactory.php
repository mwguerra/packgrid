<?php

namespace App\Services;

use App\Adapters\ComposerAdapter;
use App\Adapters\NpmAdapter;
use App\Contracts\FormatAdapterInterface;
use App\Enums\PackageFormat;
use InvalidArgumentException;

class AdapterFactory
{
    public function __construct(
        private readonly GitHubClient $client
    ) {}

    public function make(PackageFormat $format): FormatAdapterInterface
    {
        return match ($format) {
            PackageFormat::Composer => new ComposerAdapter($this->client),
            PackageFormat::Npm => new NpmAdapter($this->client),
        };
    }

    public function makeFromString(string $format): FormatAdapterInterface
    {
        $packageFormat = PackageFormat::tryFrom($format);

        if ($packageFormat === null) {
            throw new InvalidArgumentException("Unknown package format: {$format}");
        }

        return $this->make($packageFormat);
    }
}
