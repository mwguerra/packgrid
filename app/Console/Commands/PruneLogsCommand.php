<?php

namespace App\Console\Commands;

use App\Models\DownloadLog;
use App\Models\SyncLog;
use Illuminate\Console\Command;

class PruneLogsCommand extends Command
{
    protected $signature = 'packgrid:prune-logs
                            {--days= : Override the retention period (in days) for both logs}
                            {--dry-run : Report how many rows would be pruned without deleting}';

    protected $description = 'Delete download and sync log rows older than the configured retention period';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $override = $this->option('days');

        $downloadDays = $override !== null ? (int) $override : (int) config('packgrid.retention.download_logs_days', 90);
        $syncDays = $override !== null ? (int) $override : (int) config('packgrid.retention.sync_logs_days', 90);

        if ($dryRun) {
            $this->info('Running in dry-run mode - no rows will be deleted.');
            $this->newLine();
        }

        $downloadDeleted = $this->prune(DownloadLog::class, 'download logs', $downloadDays, $dryRun);
        $syncDeleted = $this->prune(SyncLog::class, 'sync logs', $syncDays, $dryRun);

        $this->newLine();
        $this->info(sprintf(
            '%s %d download log(s) and %d sync log(s).',
            $dryRun ? 'Would prune' : 'Pruned',
            $downloadDeleted,
            $syncDeleted,
        ));

        return Command::SUCCESS;
    }

    /**
     * Prune (or count) rows of the given log model older than $days days.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function prune(string $model, string $label, int $days, bool $dryRun): int
    {
        // A retention of 0 (or less) keeps the log indefinitely.
        if ($days <= 0) {
            $this->line(sprintf('Skipping %s (retention disabled).', $label));

            return 0;
        }

        $cutoff = now()->subDays($days);
        $query = $model::query()->where('created_at', '<', $cutoff);

        if ($dryRun) {
            $count = $query->count();
            $this->line(sprintf('%s: %d row(s) older than %s.', $label, $count, $cutoff->toDateString()));

            return $count;
        }

        $deleted = $query->delete();
        $this->line(sprintf('%s: deleted %d row(s) older than %s.', $label, $deleted, $cutoff->toDateString()));

        return $deleted;
    }
}
