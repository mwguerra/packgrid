<?php

namespace App\Filament\Widgets;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Models\DockerBlob;
use App\Models\DockerRepository;
use App\Models\Repository;
use App\Models\Token;
use App\Support\PackgridSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PackgridStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = [];

        // Show packages and repositories stats only if composer or npm is enabled
        if (PackgridSettings::repositoriesEnabled()) {
            // Packages stat
            $totalPackages = Repository::sum('package_count');

            // Repositories stat
            $repoCount = Repository::count();
            $repoHealthy = Repository::whereNull('last_error')->count();
            $repoFailed = max($repoCount - $repoHealthy, 0);

            $stats[] = Stat::make(__('widget.stats.packages'), $totalPackages)
                ->description(__('widget.stats.packages_desc'))
                ->color('primary')
                ->icon('heroicon-o-cube');

            $stats[] = Stat::make(__('widget.stats.repositories'), $repoCount)
                ->description(__('widget.stats.repositories_desc', ['healthy' => $repoHealthy, 'failed' => $repoFailed]))
                ->color($repoFailed > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-archive-box');
        }

        // Show Docker stats only if Docker is enabled
        if (PackgridSettings::dockerEnabled()) {
            $dockerRepoCount = DockerRepository::count();
            $dockerTagCount = DockerRepository::sum('tag_count');
            $dockerStorageSize = DockerBlob::sum('size');
            $dockerStorageFormatted = $this->formatSize($dockerStorageSize);

            $stats[] = Stat::make(__('widget.stats.docker_repos'), $dockerRepoCount)
                ->description(__('widget.stats.docker_repos_desc', ['tags' => $dockerTagCount, 'storage' => $dockerStorageFormatted]))
                ->color('info')
                ->icon('heroicon-o-cube-transparent');
        }

        // Tokens stat - always shown
        $tokenCount = Token::count();
        $tokenActive = Token::where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $stats[] = Stat::make(__('widget.stats.tokens'), $tokenCount)
            ->description(__('widget.stats.tokens_desc', ['active' => $tokenActive]))
            ->color($tokenActive > 0 ? 'success' : 'warning')
            ->icon('heroicon-o-ticket');

        // Credentials stat - always shown
        $credCount = Credential::count();
        $credHealthy = Credential::where('status', CredentialStatus::Ok)->count();
        $credFailed = max($credCount - $credHealthy, 0);

        $stats[] = Stat::make(__('widget.stats.credentials'), $credCount)
            ->description(__('widget.stats.credentials_desc', ['healthy' => $credHealthy, 'failed' => $credFailed]))
            ->color($credFailed > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-key');

        return $stats;
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
