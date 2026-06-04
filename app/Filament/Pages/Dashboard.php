<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttentionRequired;
use App\Filament\Widgets\PackgridStats;
use App\Filament\Widgets\SecurityAccess;
use App\Filament\Widgets\SyncActivity;
use App\Filament\Widgets\SystemHealth;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Order widgets by urgency (inverted pyramid): things needing action first,
     * then operational health, the at-a-glance overview, security posture, and
     * finally the recent-activity feed.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AttentionRequired::class,
            SystemHealth::class,
            PackgridStats::class,
            SecurityAccess::class,
            SyncActivity::class,
        ];
    }
}
