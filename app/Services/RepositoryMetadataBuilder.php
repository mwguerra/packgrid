<?php

namespace App\Services;

use App\Enums\PackageFormat;
use App\Models\Repository;
use RuntimeException;

class RepositoryMetadataBuilder
{
    public function __construct(
        private readonly GitHubClient $client,
        private readonly AdapterFactory $adapterFactory
    ) {}

    public function build(Repository $repository): array
    {
        $fullName = $repository->repo_full_name;
        $credential = $repository->credential;
        $tags = $this->client->listTags($fullName, $credential);
        $branches = $this->client->listBranches($fullName, $credential);

        $tagMap = collect($tags)
            ->filter(fn ($tag) => isset($tag['name'], $tag['commit']['sha']))
            ->mapWithKeys(fn ($tag) => [$tag['name'] => $tag['commit']['sha']])
            ->all();

        $branchMap = collect($branches)
            ->filter(fn ($branch) => isset($branch['name'], $branch['commit']['sha']))
            ->mapWithKeys(fn ($branch) => [$branch['name'] => $branch['commit']['sha']])
            ->all();

        $refs = $this->resolveRefs($repository->ref_filter, $tagMap, $branchMap);

        // Get the appropriate adapter based on repository format
        $format = $repository->format ?? PackageFormat::Composer;
        $adapter = $this->adapterFactory->make($format);

        return $adapter->buildMetadata($repository, $refs);
    }

    private function resolveRefs(?string $filter, array $tagMap, array $branchMap): array
    {
        $refs = [];

        if ($filter) {
            $requested = array_filter(preg_split('/[\s,]+/', $filter) ?: []);
            foreach ($requested as $ref) {
                if (isset($tagMap[$ref])) {
                    $refs[] = ['ref' => $ref, 'sha' => $tagMap[$ref], 'type' => 'tag'];
                } elseif (isset($branchMap[$ref])) {
                    $refs[] = ['ref' => $ref, 'sha' => $branchMap[$ref], 'type' => 'branch'];
                }
            }

            if ($refs === []) {
                throw new RuntimeException('No matching tags or branches for filter.');
            }

            return $refs;
        }

        foreach ($tagMap as $name => $sha) {
            $refs[] = ['ref' => $name, 'sha' => $sha, 'type' => 'tag'];
        }

        foreach ($branchMap as $name => $sha) {
            $refs[] = ['ref' => $name, 'sha' => $sha, 'type' => 'branch'];
        }

        if ($refs === []) {
            throw new RuntimeException('No tags or branches found for repository.');
        }

        return $refs;
    }
}
