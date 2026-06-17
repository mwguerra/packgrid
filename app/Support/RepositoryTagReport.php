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
        $store = $repository->format === PackageFormat::Npm ? $this->npmStore : $this->composerStore;
        $metadata = $store->readRepositoryMetadata($repository->id) ?? [];

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

        $rows = [];
        foreach ($metadata as $packageName => $versions) {
            foreach (array_keys($versions) as $version) {
                $rows[] = [
                    'package' => $packageName,
                    'version' => $version,
                    'downloads' => (int) ($canonical[$this->canonical((string) $version)] ?? 0),
                ];
            }
        }

        return $this->sortRows($rows);
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
