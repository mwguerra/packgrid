<?php

namespace App\Console\Commands;

use App\Models\Repository;
use App\Services\RepositorySyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncRepositoriesCommand extends Command
{
    protected $signature = 'packgrid:sync-repositories
                            {--force : Sync all repositories, including disabled ones}';

    protected $description = 'Sync all enabled repositories from GitHub';

    public function handle(RepositorySyncService $syncService): int
    {
        $query = Repository::query();

        if (! $this->option('force')) {
            $query->where('enabled', true);
        }

        $repositories = $query->get();

        if ($repositories->isEmpty()) {
            $this->info('No repositories to sync.');

            return Command::SUCCESS;
        }

        $this->info("Syncing {$repositories->count()} repositories...");
        $this->newLine();

        $success = 0;
        $failed = 0;

        foreach ($repositories as $repository) {
            $this->line("  Syncing: {$repository->name}");

            try {
                $syncService->sync($repository);
                $this->info('    ✓ Synced successfully');
                $success++;
            } catch (Throwable $e) {
                $this->error("    ✗ Failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Sync complete: {$success} succeeded, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
