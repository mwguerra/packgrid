<?php

namespace App\Services\Docker;

use App\Models\DockerActivity;
use App\Models\DockerBlob;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;

class RegistryStatisticsService
{
    public function getOverviewStatistics(): array
    {
        return [
            'repositories' => [
                'total' => DockerRepository::count(),
                'enabled' => DockerRepository::where('enabled', true)->count(),
                'disabled' => DockerRepository::where('enabled', false)->count(),
            ],
            'images' => [
                'total_manifests' => DockerManifest::count(),
                'total_tags' => DockerTag::count(),
            ],
            'storage' => [
                'total_blobs' => DockerBlob::count(),
                'total_size' => DockerBlob::sum('size'),
                'total_size_formatted' => $this->formatSize(DockerBlob::sum('size')),
            ],
            'activity' => [
                'total_pulls' => DockerRepository::sum('pull_count'),
                'total_pushes' => DockerRepository::sum('push_count'),
                'recent_activities' => DockerActivity::orderBy('created_at', 'desc')->limit(10)->get(),
            ],
        ];
    }

    public function getRepositoryStatistics(DockerRepository $repository): array
    {
        return [
            'manifests' => $repository->manifests()->count(),
            'tags' => $repository->tags()->count(),
            'blobs' => $repository->blobs()->count(),
            'total_size' => $repository->total_size,
            'total_size_formatted' => $repository->formatted_size,
            'pull_count' => $repository->pull_count,
            'push_count' => $repository->push_count,
            'last_push_at' => $repository->last_push_at,
            'last_pull_at' => $repository->last_pull_at,
            'recent_activities' => $repository->activities()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
        ];
    }

    public function updateAllRepositoryStatistics(): int
    {
        $updated = 0;

        DockerRepository::chunk(50, function ($repositories) use (&$updated) {
            foreach ($repositories as $repository) {
                $repository->updateStatistics();
                $updated++;
            }
        });

        return $updated;
    }

    public function getTopRepositoriesBySize(int $limit = 10): array
    {
        return DockerRepository::orderBy('total_size', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($repo) => [
                'id' => $repo->id,
                'name' => $repo->name,
                'total_size' => $repo->total_size,
                'total_size_formatted' => $repo->formatted_size,
                'tag_count' => $repo->tag_count,
            ])
            ->toArray();
    }

    public function getTopRepositoriesByPulls(int $limit = 10): array
    {
        return DockerRepository::orderBy('pull_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($repo) => [
                'id' => $repo->id,
                'name' => $repo->name,
                'pull_count' => $repo->pull_count,
                'push_count' => $repo->push_count,
            ])
            ->toArray();
    }

    public function getActivitySummary(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $activities = DockerActivity::where('created_at', '>=', $startDate)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        return [
            'pushes' => $activities['push'] ?? 0,
            'pulls' => $activities['pull'] ?? 0,
            'deletes' => $activities['delete'] ?? 0,
            'mounts' => $activities['mount'] ?? 0,
            'period_days' => $days,
        ];
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
