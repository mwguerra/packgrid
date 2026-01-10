<?php

namespace App\Filament\Widgets;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Models\Repository;
use App\Models\Token;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PackgridStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Packages stat
        $totalPackages = Repository::sum('package_count');

        // Repositories stat
        $repoCount = Repository::count();
        $repoHealthy = Repository::whereNull('last_error')->count();
        $repoFailed = max($repoCount - $repoHealthy, 0);

        // Tokens stat
        $tokenCount = Token::count();
        $tokenActive = Token::where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        // Credentials stat
        $credCount = Credential::count();
        $credHealthy = Credential::where('status', CredentialStatus::Ok)->count();
        $credFailed = max($credCount - $credHealthy, 0);

        return [
            Stat::make(__('widget.stats.packages'), $totalPackages)
                ->description(__('widget.stats.packages_desc'))
                ->color('primary')
                ->icon('heroicon-o-cube'),

            Stat::make(__('widget.stats.repositories'), $repoCount)
                ->description(__('widget.stats.repositories_desc', ['healthy' => $repoHealthy, 'failed' => $repoFailed]))
                ->color($repoFailed > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-archive-box'),

            Stat::make(__('widget.stats.tokens'), $tokenCount)
                ->description(__('widget.stats.tokens_desc', ['active' => $tokenActive]))
                ->color($tokenActive > 0 ? 'success' : 'warning')
                ->icon('heroicon-o-ticket'),

            Stat::make(__('widget.stats.credentials'), $credCount)
                ->description(__('widget.stats.credentials_desc', ['healthy' => $credHealthy, 'failed' => $credFailed]))
                ->color($credFailed > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-key'),
        ];
    }
}
