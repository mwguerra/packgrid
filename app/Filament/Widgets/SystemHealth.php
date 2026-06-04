<?php

namespace App\Filament\Widgets;

use App\Models\Credential;
use App\Models\DockerBlob;
use App\Models\DockerUpload;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Support\PackgridSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class SystemHealth extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    /** Backups older than this many days are flagged. */
    protected const BACKUP_STALE_DAYS = 7;

    /**
     * If no scheduled job has run within this many hours, the scheduler
     * (cron running `schedule:run`) is very likely down. The longest-cadence
     * job is the daily credential check, so 25h = 24h + 1h grace.
     */
    protected const SCHEDULER_STALE_HOURS = 25;

    protected function getHeading(): ?string
    {
        return __('widget.health.heading');
    }

    protected function getStats(): array
    {
        return array_values(array_filter([
            $this->schedulerStat(),
            $this->backupStat(),
            PackgridSettings::dockerEnabled() ? $this->storageStat() : null,
        ]));
    }

    protected function schedulerStat(): Stat
    {
        $lastRun = $this->latestScheduledRun();

        if ($lastRun === null) {
            return Stat::make(__('widget.health.scheduler'), __('widget.health.scheduler_idle'))
                ->description(__('widget.health.scheduler_idle_desc'))
                ->color('gray')
                ->icon('heroicon-o-clock');
        }

        $stalled = $lastRun->lt(now()->subHours(self::SCHEDULER_STALE_HOURS));

        return Stat::make(
            __('widget.health.scheduler'),
            $stalled ? __('widget.health.scheduler_stalled') : __('widget.health.scheduler_active'),
        )
            ->description(__('widget.health.last_run', ['time' => $lastRun->diffForHumans()]))
            ->color($stalled ? 'danger' : 'success')
            ->icon($stalled ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-bolt');
    }

    protected function backupStat(): Stat
    {
        $raw = Setting::query()->value('last_backup_at');
        $lastBackup = $raw ? Carbon::parse($raw) : null;

        $url = Route::has('filament.admin.pages.backup-restore')
            ? route('filament.admin.pages.backup-restore')
            : null;

        if ($lastBackup === null) {
            return Stat::make(__('widget.health.backup'), __('widget.health.backup_never'))
                ->description(__('widget.health.backup_never_desc'))
                ->color('danger')
                ->icon('heroicon-o-shield-exclamation')
                ->url($url);
        }

        $stale = $lastBackup->lt(now()->subDays(self::BACKUP_STALE_DAYS));

        return Stat::make(__('widget.health.backup'), $lastBackup->diffForHumans())
            ->description($stale ? __('widget.health.backup_stale_desc') : __('widget.health.backup_ok_desc'))
            ->color($stale ? 'warning' : 'success')
            ->icon('heroicon-o-shield-check')
            ->url($url);
    }

    protected function storageStat(): Stat
    {
        $totalSize = (int) DockerBlob::sum('size');
        $gcEnabled = (bool) config('packgrid.docker.gc_enabled', true);
        $staleUploads = $this->staleUploadCount();

        $color = 'info';
        $description = __('widget.health.storage_ok_desc');

        if (! $gcEnabled) {
            $color = 'warning';
            $description = __('widget.health.storage_gc_off_desc');
        } elseif ($staleUploads > 0) {
            $color = 'warning';
            $description = __('widget.health.storage_stale_uploads_desc', ['count' => $staleUploads]);
        }

        return Stat::make(__('widget.health.storage'), $this->formatSize($totalSize))
            ->description($description)
            ->color($color)
            ->icon('heroicon-o-circle-stack')
            ->url(Route::has('filament.admin.resources.docker-repositories.index')
                ? route('filament.admin.resources.docker-repositories.index')
                : null);
    }

    /**
     * The most recent timestamp of any scheduled job (sync or credential check),
     * used as a proxy for whether the cron scheduler is alive.
     */
    protected function latestScheduledRun(): ?Carbon
    {
        $timestamps = array_filter([
            SyncLog::max('started_at'),
            Credential::max('last_checked_at'),
        ]);

        if ($timestamps === []) {
            return null;
        }

        return collect($timestamps)
            ->map(fn ($value): Carbon => Carbon::parse($value))
            ->reduce(fn (?Carbon $carry, Carbon $date): Carbon => $carry === null || $date->gt($carry) ? $date : $carry);
    }

    protected function staleUploadCount(): int
    {
        $staleHours = (int) config('packgrid.docker.gc_stale_upload_hours', 24);

        return DockerUpload::whereIn('status', ['pending', 'uploading'])
            ->where(function ($query) use ($staleHours) {
                $query->where('expires_at', '<', now())
                    ->orWhere('created_at', '<', now()->subHours($staleHours));
            })
            ->count();
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
