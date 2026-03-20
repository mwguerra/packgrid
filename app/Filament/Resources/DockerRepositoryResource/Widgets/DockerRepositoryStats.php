<?php

namespace App\Filament\Resources\DockerRepositoryResource\Widgets;

use App\Enums\DockerActivityType;
use App\Models\DockerActivity;
use App\Models\DockerBlob;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class DockerRepositoryStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            $this->getTotalStorageStat(),
            $this->getDownloadsLast7DaysStat(),
        ];
    }

    protected function getTotalStorageStat(): Stat
    {
        $totalSize = DockerBlob::sum('size');

        $dailySizes = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dailySizes[] = (int) DockerActivity::where('type', DockerActivityType::Push)
                ->whereDate('created_at', $date)
                ->sum('size');
        }

        return Stat::make(__('widget.docker_stats.total_storage'), $this->formatSize($totalSize))
            ->description(__('widget.docker_stats.total_storage_desc'))
            ->icon('heroicon-o-circle-stack')
            ->color('primary')
            ->chart($dailySizes);
    }

    protected function getDownloadsLast7DaysStat(): Stat
    {
        $dailyDownloads = [];
        $total = 0;

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = DockerActivity::where('type', DockerActivityType::Pull)
                ->whereDate('created_at', $date)
                ->count();
            $dailyDownloads[] = $count;
            $total += $count;
        }

        return Stat::make(__('widget.docker_stats.downloads_7d'), $total)
            ->description(__('widget.docker_stats.downloads_7d_desc'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info')
            ->chart($dailyDownloads);
    }

    protected function formatSize(int|float $bytes): string
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
