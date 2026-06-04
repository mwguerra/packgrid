<?php

namespace App\Filament\Widgets;

use App\Services\Docker\GarbageCollectionService;
use App\Support\PackgridSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Route;

class StorageCapacity extends StatsOverviewWidget
{
    // Garbage-collection statistics are moderately expensive, so poll gently.
    protected ?string $pollingInterval = '300s';

    public static function canView(): bool
    {
        return PackgridSettings::dockerEnabled();
    }

    protected function getHeading(): ?string
    {
        return __('widget.storage.heading');
    }

    protected function getStats(): array
    {
        $stats = app(GarbageCollectionService::class)->getStatistics();

        $dockerUrl = Route::has('filament.admin.resources.docker-repositories.index')
            ? route('filament.admin.resources.docker-repositories.index')
            : null;

        return [
            Stat::make(__('widget.storage.used'), $stats['total_size_formatted'])
                ->description(__('widget.storage.used_desc', ['blobs' => $stats['total_blobs']]))
                ->color('info')
                ->icon('heroicon-o-circle-stack')
                ->url($dockerUrl),

            Stat::make(__('widget.storage.reclaimable'), $stats['potential_savings_formatted'])
                ->description(__('widget.storage.reclaimable_desc', [
                    'orphaned' => $stats['orphaned_blobs'],
                    'untagged' => $stats['untagged_manifests'],
                ]))
                ->color(((int) $stats['potential_savings']) > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-trash')
                ->url($dockerUrl),

            Stat::make(__('widget.storage.stale_uploads'), $stats['stale_uploads'])
                ->description(__('widget.storage.stale_uploads_desc'))
                ->color(((int) $stats['stale_uploads']) > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-cloud-arrow-up')
                ->url($dockerUrl),
        ];
    }
}
