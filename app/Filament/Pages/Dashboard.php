<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttentionRequired;
use App\Filament\Widgets\OnboardingChecklist;
use App\Filament\Widgets\PackgridStats;
use App\Filament\Widgets\RecentDockerActivity;
use App\Filament\Widgets\SecurityAccess;
use App\Filament\Widgets\StorageCapacity;
use App\Filament\Widgets\SyncActivity;
use App\Filament\Widgets\SystemHealth;
use App\Filament\Widgets\UsageTrend;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Order widgets by urgency (inverted pyramid). Widgets whose canView()
     * returns false (e.g. onboarding once complete, Docker widgets when Docker
     * is disabled, the attention panel when nothing is wrong) are skipped.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            // Get-started guidance (only while setup is incomplete).
            OnboardingChecklist::class,
            // What needs action right now.
            AttentionRequired::class,
            // Operational health.
            SystemHealth::class,
            // At-a-glance overview.
            PackgridStats::class,
            // Security posture.
            SecurityAccess::class,
            // Usage trends.
            UsageTrend::class,
            // Storage & capacity (Docker only).
            StorageCapacity::class,
            // Recent activity feeds.
            SyncActivity::class,
            RecentDockerActivity::class,
        ];
    }
}
