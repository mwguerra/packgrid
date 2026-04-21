<?php

namespace App\Services;

use App\DTOs\RefDto;
use App\Enums\PackageFormat;
use App\Models\Repository;
use RuntimeException;

class RepositoryMetadataBuilder
{
    public function __construct(
        private readonly GitProviderClientFactory $clientFactory,
        private readonly AdapterFactory $adapterFactory,
    ) {}

    public function build(Repository $repository): array
    {
        $client = $this->clientFactory->forCredential($repository->credential);
        $fullName = $repository->repo_full_name;

        $tags = $client->listTags($fullName);
        $branches = $client->listBranches($fullName);

        $refs = $this->resolveRefs($repository->ref_filter, $tags, $branches);

        $format = $repository->format ?? PackageFormat::Composer;
        $adapter = $this->adapterFactory->make($format, $repository->credential);

        return $adapter->buildMetadata($repository, $refs);
    }

    /**
     * @param  RefDto[]  $tags
     * @param  RefDto[]  $branches
     * @return RefDto[]
     */
    private function resolveRefs(?string $filter, array $tags, array $branches): array
    {
        $all = array_merge($tags, $branches);

        if ($filter) {
            $requested = array_filter(preg_split('/[\s,]+/', $filter) ?: []);
            $matched = array_values(
                array_filter($all, fn (RefDto $ref) => in_array($ref->name, $requested))
            );

            if ($matched === []) {
                throw new RuntimeException('No matching tags or branches for filter.');
            }

            return $matched;
        }

        if ($all === []) {
            throw new RuntimeException('No tags or branches found for repository.');
        }

        return $all;
    }
}
