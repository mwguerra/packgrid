<?php

namespace App\Console\Commands;

use App\Services\Docker\GarbageCollectionService;
use Illuminate\Console\Command;
use Throwable;

class DockerGarbageCollectionCommand extends Command
{
    protected $signature = 'packgrid:docker-gc
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--stats : Show current garbage collection statistics only}
                            {--recalculate : Recalculate blob reference counts before collecting}';

    protected $description = 'Garbage collect unreferenced Docker blobs and stale uploads';

    public function handle(GarbageCollectionService $gcService): int
    {
        if (! config('packgrid.docker.gc_enabled', true)) {
            $this->warn('Docker garbage collection is disabled in configuration.');
            $this->info('Set PACKGRID_DOCKER_GC_ENABLED=true to enable.');

            return Command::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $statsOnly = $this->option('stats');
        $recalculate = $this->option('recalculate');

        if ($statsOnly) {
            return $this->showStatistics($gcService);
        }

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made.');
            $this->newLine();
        }

        try {
            // Optionally recalculate reference counts first
            if ($recalculate) {
                $this->info('Recalculating blob reference counts...');
                $updated = $gcService->recalculateBlobReferences();
                $this->info("  Updated {$updated} blob reference counts.");
                $this->newLine();
            }

            // Run garbage collection
            $this->info('Starting Docker garbage collection...');
            $this->newLine();

            $results = $gcService->collectGarbage($dryRun);

            // Report orphaned blobs
            $this->reportOrphanedBlobs($results['orphaned_blobs'], $dryRun);

            // Report stale uploads
            $this->reportStaleUploads($results['stale_uploads'], $dryRun);

            // Summary
            $this->newLine();
            $action = $dryRun ? 'Would delete' : 'Deleted';
            $this->info('Summary:');
            $this->line("  {$action} {$results['orphaned_blobs']['count']} orphaned blobs ({$this->formatSize($results['orphaned_blobs']['size'])})");
            $this->line("  {$action} {$results['stale_uploads']['count']} stale uploads");

            if ($dryRun && ($results['orphaned_blobs']['count'] > 0 || $results['stale_uploads']['count'] > 0)) {
                $this->newLine();
                $this->info('Run without --dry-run to actually delete these items.');
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Garbage collection failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function showStatistics(GarbageCollectionService $gcService): int
    {
        $this->info('Docker Registry Statistics');
        $this->newLine();

        $stats = $gcService->getStatistics();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Blobs', $stats['total_blobs']],
                ['Total Size', $stats['total_size_formatted']],
                ['Orphaned Blobs', $stats['orphaned_blobs']],
                ['Orphaned Size', $stats['orphaned_size_formatted']],
                ['Stale Uploads', $stats['stale_uploads']],
                ['Potential Savings', $stats['potential_savings_formatted']],
            ]
        );

        if ($stats['orphaned_blobs'] > 0 || $stats['stale_uploads'] > 0) {
            $this->newLine();
            $this->info('Run `php artisan packgrid:docker-gc` to clean up.');
        }

        return Command::SUCCESS;
    }

    protected function reportOrphanedBlobs(array $orphanedBlobs, bool $dryRun): void
    {
        $action = $dryRun ? 'Would delete' : 'Deleting';

        $this->line("Orphaned Blobs ({$orphanedBlobs['count']}):");

        if ($orphanedBlobs['count'] === 0) {
            $this->info('  No orphaned blobs found.');

            return;
        }

        foreach ($orphanedBlobs['items'] as $blob) {
            $this->line("  {$action}: {$blob['digest']} ({$this->formatSize($blob['size'])})");
        }
    }

    protected function reportStaleUploads(array $staleUploads, bool $dryRun): void
    {
        $action = $dryRun ? 'Would cancel' : 'Cancelling';

        $this->newLine();
        $this->line("Stale Uploads ({$staleUploads['count']}):");

        if ($staleUploads['count'] === 0) {
            $this->info('  No stale uploads found.');

            return;
        }

        foreach ($staleUploads['items'] as $upload) {
            $this->line("  {$action}: {$upload['id']} (repo: {$upload['repository']}, created: {$upload['created_at']})");
        }
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
