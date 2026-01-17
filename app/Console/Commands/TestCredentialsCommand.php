<?php

namespace App\Console\Commands;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Services\CredentialHealthService;
use Illuminate\Console\Command;

class TestCredentialsCommand extends Command
{
    protected $signature = 'packgrid:test-credentials';

    protected $description = 'Test all GitHub credentials to verify they are still valid';

    public function handle(CredentialHealthService $healthService): int
    {
        $credentials = Credential::all();

        if ($credentials->isEmpty()) {
            $this->info('No credentials to test.');

            return Command::SUCCESS;
        }

        $this->info("Testing {$credentials->count()} credentials...");
        $this->newLine();

        $valid = 0;
        $invalid = 0;

        foreach ($credentials as $credential) {
            $this->line("  Testing: {$credential->name}");

            $result = $healthService->test($credential);

            if ($result->status === CredentialStatus::Ok) {
                $this->info('    ✓ Valid');
                $valid++;
            } else {
                $this->error("    ✗ Invalid: {$result->last_error}");
                $invalid++;
            }
        }

        $this->newLine();
        $this->info("Test complete: {$valid} valid, {$invalid} invalid.");

        return $invalid > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
