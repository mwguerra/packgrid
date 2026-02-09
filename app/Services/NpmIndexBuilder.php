<?php

namespace App\Services;

use App\Adapters\NpmAdapter;
use App\Enums\PackageFormat;
use App\Models\Repository;

class NpmIndexBuilder
{
    public function __construct(
        private readonly NpmMetadataStore $store,
        private readonly NpmAdapter $adapter
    ) {}

    /**
     * Rebuild the NPM package index from all enabled NPM repositories.
     *
     * @return array<string, array<string, mixed>>
     */
    public function rebuild(): array
    {
        $packages = [];

        Repository::query()
            ->where('enabled', true)
            ->where('format', PackageFormat::Npm)
            ->each(function (Repository $repository) use (&$packages) {
                $metadata = $this->store->readRepositoryMetadata($repository->id);

                if (! is_array($metadata)) {
                    return;
                }

                foreach ($metadata as $packageName => $versions) {
                    if (! isset($packages[$packageName])) {
                        $packages[$packageName] = [];
                    }

                    $packages[$packageName] = array_replace($packages[$packageName], $versions);
                }
            });

        // Write individual package metadata in NPM registry format
        foreach ($packages as $packageName => $versions) {
            $registryMetadata = $this->adapter->buildRegistryMetadata($packageName, $versions);
            $this->store->writePackage($packageName, $registryMetadata);
        }

        return $packages;
    }
}
