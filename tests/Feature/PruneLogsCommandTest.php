<?php

use App\Models\DownloadLog;
use App\Models\Repository;
use App\Models\SyncLog;

function ageRow($model, int $days): void
{
    $model->created_at = now()->subDays($days);
    $model->save();
}

it('prunes download and sync logs older than the configured retention', function () {
    config()->set('packgrid.retention.download_logs_days', 30);
    config()->set('packgrid.retention.sync_logs_days', 30);

    $repo = Repository::factory()->create();

    $oldDownload = DownloadLog::factory()->forRepository($repo)->create();
    ageRow($oldDownload, 60);
    $recentDownload = DownloadLog::factory()->forRepository($repo)->create();
    ageRow($recentDownload, 5);

    $oldSync = SyncLog::factory()->create(['repository_id' => $repo->id]);
    ageRow($oldSync, 60);
    $recentSync = SyncLog::factory()->create(['repository_id' => $repo->id]);
    ageRow($recentSync, 5);

    $this->artisan('packgrid:prune-logs')->assertSuccessful();

    expect(DownloadLog::find($oldDownload->id))->toBeNull();
    expect(DownloadLog::find($recentDownload->id))->not->toBeNull();
    expect(SyncLog::find($oldSync->id))->toBeNull();
    expect(SyncLog::find($recentSync->id))->not->toBeNull();
});

it('does not delete anything in dry-run mode', function () {
    config()->set('packgrid.retention.download_logs_days', 30);

    $repo = Repository::factory()->create();
    $old = DownloadLog::factory()->forRepository($repo)->create();
    ageRow($old, 90);

    $this->artisan('packgrid:prune-logs --dry-run')->assertSuccessful();

    expect(DownloadLog::find($old->id))->not->toBeNull();
});

it('respects the --days override', function () {
    $repo = Repository::factory()->create();
    $log = DownloadLog::factory()->forRepository($repo)->create();
    ageRow($log, 10);

    // Default retention (90 days) would keep it; an override of 7 prunes it.
    $this->artisan('packgrid:prune-logs --days=7')->assertSuccessful();

    expect(DownloadLog::find($log->id))->toBeNull();
});

it('keeps logs indefinitely when retention is zero', function () {
    config()->set('packgrid.retention.download_logs_days', 0);
    config()->set('packgrid.retention.sync_logs_days', 0);

    $repo = Repository::factory()->create();
    $old = DownloadLog::factory()->forRepository($repo)->create();
    ageRow($old, 365 * 5);

    $this->artisan('packgrid:prune-logs')->assertSuccessful();

    expect(DownloadLog::find($old->id))->not->toBeNull();
});
