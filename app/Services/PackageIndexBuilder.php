<?php

namespace App\Services;

use App\Models\Repository;

class PackageIndexBuilder
{
    public function __construct(private readonly PackageMetadataStore $store) {}

    public function rebuild(): array
    {
        $packages = [];

        Repository::query()
            ->where('enabled', true)
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

        $indexPayload = ['packages' => $packages];
        $this->store->writePackagesIndex($indexPayload);

        foreach ($packages as $packageName => $versions) {
            [$vendor, $package] = explode('/', $packageName, 2) + [null, null];

            if (! $vendor || ! $package) {
                continue;
            }

            $this->store->writePackage($vendor, $package, [
                'packages' => [
                    $packageName => $versions,
                ],
            ]);
        }

        return $packages;
    }
}
