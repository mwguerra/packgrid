<?php

namespace App\Support;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Services\NpmMetadataStore;
use App\Services\PackageMetadataStore;

class RepositoryTagReport
{
    public function __construct(
        private readonly PackageMetadataStore $composerStore,
        private readonly NpmMetadataStore $npmStore,
    ) {}

    /**
     * @return array<int, array{package: string, version: string, downloads: int}>
     */
    public function rows(Repository $repository): array
    {
        $downloads = $this->downloadCounts($repository);

        $rows = array_map(function (array $row) use ($downloads): array {
            $row['downloads'] = (int) ($downloads[$this->canonical($row['version'])] ?? 0);

            return $row;
        }, $this->metadataRows($repository));

        return $this->sortRows($rows);
    }

    /**
     * The available version/tag strings for a repository, newest tags first.
     *
     * @return array<int, string>
     */
    public function versions(Repository $repository): array
    {
        return array_map(
            fn (array $row): string => $row['version'],
            $this->sortRows($this->metadataRows($repository)),
        );
    }

    /**
     * @return array<int, array{package: string, version: string, downloads: int}>
     */
    private function metadataRows(Repository $repository): array
    {
        $store = $repository->format === PackageFormat::Npm ? $this->npmStore : $this->composerStore;
        $metadata = $store->readRepositoryMetadata($repository->id) ?? [];

        $rows = [];
        foreach ($metadata as $packageName => $versions) {
            foreach (array_keys($versions) as $version) {
                $rows[] = [
                    'package' => $packageName,
                    'version' => $version,
                    'downloads' => 0,
                ];
            }
        }

        return $rows;
    }

    /**
     * Per-version download totals, keyed by the canonical (prefix-stripped) version.
     *
     * @return array<string, int>
     */
    private function downloadCounts(Repository $repository): array
    {
        $downloads = DownloadLog::query()
            ->where('repository_id', $repository->id)
            ->selectRaw('package_version, COUNT(*) as total')
            ->groupBy('package_version')
            ->pluck('total', 'package_version');

        $canonical = [];
        foreach ($downloads as $version => $total) {
            $key = $this->canonical((string) $version);
            $canonical[$key] = ($canonical[$key] ?? 0) + (int) $total;
        }

        return $canonical;
    }

    private function isBranch(string $version): bool
    {
        return str_starts_with($version, 'dev-') || str_starts_with($version, '0.0.0-');
    }

    private function canonical(string $version): string
    {
        $version = preg_replace('/^dev-/', '', $version);
        $version = preg_replace('/^0\.0\.0-/', '', $version);

        return preg_replace('/^v(?=\d)/', '', $version);
    }

    /**
     * @param  array<int, array{package: string, version: string, downloads: int}>  $rows
     * @return array<int, array{package: string, version: string, downloads: int}>
     */
    private function sortRows(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $aBranch = $this->isBranch($a['version']);
            $bBranch = $this->isBranch($b['version']);

            if ($aBranch !== $bBranch) {
                return $aBranch <=> $bBranch; // tags (false) first
            }

            if ($aBranch) {
                return strcmp($a['version'], $b['version']);
            }

            return version_compare($this->canonical($b['version']), $this->canonical($a['version']));
        });

        return $rows;
    }
}
