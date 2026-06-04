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
use Illuminate\Support\Facades\Route;

class PackgridStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getHeading(): ?string
    {
        return __('widget.stats.heading');
    }

    protected function getStats(): array
    {
        $stats = [];

        // Show packages and repositories stats only if composer or npm is enabled
        if (PackgridSettings::repositoriesEnabled()) {
            $totalPackages = (int) Repository::sum('package_count');

            $repoCount = Repository::count();
            $repoFailed = Repository::whereNotNull('last_error')->count();
            $repoStale = Repository::query()->stale()->count();
            $repoHealthy = max($repoCount - $repoFailed - $repoStale, 0);

            $repoDescription = __('widget.stats.repositories_desc', ['healthy' => $repoHealthy, 'failed' => $repoFailed]);
            if ($repoStale > 0) {
                $repoDescription .= ' · '.__('widget.stats.repositories_stale', ['stale' => $repoStale]);
            }

            $stats[] = Stat::make(__('widget.stats.packages'), $totalPackages)
                ->description(__('widget.stats.packages_desc'))
                ->color('primary')
                ->icon('heroicon-o-cube')
                ->url($this->resourceUrl('repositories'));

            $stats[] = Stat::make(__('widget.stats.repositories'), $repoCount)
                ->description($repoDescription)
                ->color($repoFailed > 0 ? 'danger' : ($repoStale > 0 ? 'warning' : 'success'))
                ->icon('heroicon-o-archive-box')
                ->url($this->resourceUrl('repositories'));
        }

        // Git clone stat - only if the Git proxy feature is enabled
        if (PackgridSettings::gitEnabled()) {
            $cloneRepos = Repository::where('clone_enabled', true)->count();
            $cloneCount = (int) Repository::sum('clone_count');

            $stats[] = Stat::make(__('widget.stats.git_clones'), $cloneCount)
                ->description(__('widget.stats.git_clones_desc', ['repos' => $cloneRepos]))
                ->color('info')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->url($this->resourceUrl('repositories'));
        }

        // Show Docker stats only if Docker is enabled
        if (PackgridSettings::dockerEnabled()) {
            $dockerRepoCount = DockerRepository::count();
            $dockerTagCount = (int) DockerRepository::sum('tag_count');
            $dockerStorageFormatted = $this->formatSize((int) DockerBlob::sum('size'));

            $stats[] = Stat::make(__('widget.stats.docker_repos'), $dockerRepoCount)
                ->description(__('widget.stats.docker_repos_desc', ['tags' => $dockerTagCount, 'storage' => $dockerStorageFormatted]))
                ->color('info')
                ->icon('heroicon-o-cube-transparent')
                ->url($this->resourceUrl('docker-repositories'));
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
            ->icon('heroicon-o-ticket')
            ->url($this->resourceUrl('tokens'));

        // Credentials stat - always shown
        $credCount = Credential::count();
        $credHealthy = Credential::where('status', CredentialStatus::Ok)->count();
        $credFailed = max($credCount - $credHealthy, 0);

        $stats[] = Stat::make(__('widget.stats.credentials'), $credCount)
            ->description(__('widget.stats.credentials_desc', ['healthy' => $credHealthy, 'failed' => $credFailed]))
            ->color($credFailed > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-key')
            ->url($this->resourceUrl('credentials'));

        return $stats;
    }

    /**
     * Build a drill-down URL to a resource list, guarding against missing routes.
     */
    protected function resourceUrl(string $resource): ?string
    {
        $name = "filament.admin.resources.{$resource}.index";

        return Route::has($name) ? route($name) : null;
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
