<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackgridSetupCommand extends Command
{
    protected $signature = 'packgrid:setup
                            {--local : Use local development settings (.test domain, keeps APP_DEBUG and APP_ENV unchanged)}
                            {--url= : Override the auto-detected APP_URL (e.g., --url=https://packages.example.com)}
                            {--env= : Override the APP_ENV value (e.g., --env=staging)}
                            {--debug= : Override the APP_DEBUG value (--debug=true or --debug=false)}
                            {--dry-run : Preview all changes without saving to .env file}';

    protected $description = 'Set up PackGrid environment configuration with smart defaults';

    protected $help = <<<'HELP'
<info>Description:</info>
  Interactive environment setup for PackGrid. Configures APP_URL, APP_ENV,
  APP_DEBUG, and generates APP_KEY if needed.

<info>URL Auto-Detection:</info>
  The APP_URL is automatically detected based on the project folder name:

  <comment>Production mode (default):</comment>
    Folder: packgrid      → https://packgrid.com
    Folder: packgrid.io   → https://packgrid.io
    Folder: my-packages   → https://my-packages.com

  <comment>Local mode (--local):</comment>
    Folder: packgrid      → https://packgrid.test
    Folder: packgrid.test → https://packgrid.test
    Folder: my-app.local  → https://my-app.test

<info>Environment Modes:</info>
  <comment>Production (default):</comment>
    APP_ENV=production, APP_DEBUG=false
    URL uses .com suffix (or detected domain)

  <comment>Local (--local):</comment>
    APP_ENV and APP_DEBUG remain unchanged
    URL uses .test suffix

<info>Key Preservation:</info>
  If .env already exists, APP_KEY is never modified.
  New key is only generated when creating .env for the first time.

<info>Examples:</info>
  <comment># Production setup with auto-detected URL</comment>
  php artisan packgrid:setup

  <comment># Local development setup</comment>
  php artisan packgrid:setup --local

  <comment># Preview changes without saving</comment>
  php artisan packgrid:setup --dry-run

  <comment># Custom URL for production</comment>
  php artisan packgrid:setup --url=https://packages.mycompany.com

  <comment># Staging environment</comment>
  php artisan packgrid:setup --url=https://staging.packgrid.com --env=staging --debug=true

  <comment># Via Composer script</comment>
  composer env:setup
  composer env:setup -- --local
  composer env:setup -- --dry-run

<info>What This Command Does:</info>
  1. Checks for .env.example (required)
  2. Creates .env from .env.example if it doesn't exist
  3. Calculates new values based on options and folder name
  4. Shows a comparison table of changes
  5. Asks for confirmation before saving
  6. Generates APP_KEY if not set

HELP;

    protected string $envPath;

    protected string $envExamplePath;

    public function handle(): int
    {
        $this->envPath = base_path('.env');
        $this->envExamplePath = base_path('.env.example');

        info('PackGrid Environment Setup');
        $this->newLine();

        // Check if .env.example exists
        if (! File::exists($this->envExamplePath)) {
            $this->error('Missing .env.example file. Cannot proceed.');

            return Command::FAILURE;
        }

        $envExists = File::exists($this->envPath);
        $currentEnv = $envExists ? $this->parseEnvFile($this->envPath) : [];
        $exampleEnv = $this->parseEnvFile($this->envExamplePath);

        // Start with example values, overlay with current values if env exists
        $baseEnv = $envExists ? array_merge($exampleEnv, $currentEnv) : $exampleEnv;

        if ($envExists) {
            note('Found existing .env file - will preserve APP_KEY');
        } else {
            note('No .env file found - will create from .env.example');
        }

        // Calculate new values
        $newEnv = $this->calculateNewValues($baseEnv, $envExists);

        // Build comparison table
        $changes = $this->buildChangesTable($baseEnv, $newEnv);

        if (empty($changes)) {
            info('No changes needed. Environment is already configured.');

            return Command::SUCCESS;
        }

        // Display changes table
        $this->newLine();
        note('The following changes will be made:');
        table(
            headers: ['Setting', 'Current Value', 'New Value'],
            rows: $changes
        );

        // Dry run - just show the final env
        if ($this->option('dry-run')) {
            $this->newLine();
            warning('Dry run mode - no changes will be saved');
            $this->newLine();
            note('Final .env content:');
            $this->line($this->buildEnvContent($newEnv));

            return Command::SUCCESS;
        }

        // Ask for confirmation
        $this->newLine();
        if (! confirm('Apply these changes?', default: true)) {
            warning('Setup cancelled.');

            return Command::SUCCESS;
        }

        // Write the .env file
        File::put($this->envPath, $this->buildEnvContent($newEnv));
        info('.env file saved successfully!');

        // Generate key if needed
        if (empty($newEnv['APP_KEY']) || $newEnv['APP_KEY'] === '') {
            $this->newLine();
            note('Generating application key...');
            $this->call('key:generate');
        }

        $this->newLine();
        info('PackGrid setup complete!');

        if ($this->option('local')) {
            note("Local development mode - access at: {$newEnv['APP_URL']}");
        } else {
            note("Production mode - access at: {$newEnv['APP_URL']}");
        }

        // Create storage link for Docker blob access
        $this->newLine();
        note('Creating storage symbolic link...');
        $this->callSilently('storage:link');
        info('Storage link created successfully!');

        $this->newLine();
        note('Scheduled Tasks (defined in routes/console.php):');
        $this->line('  - Repository sync: Every 4 hours (0 */4 * * *)');
        $this->line('  - Credential testing: Daily at 6 AM (0 6 * * *)');
        $this->line('  - Docker garbage collection: Weekly on Sundays at 3 AM');
        $this->newLine();
        note('To activate the scheduler on production, add this cron entry:');
        $this->line('  * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');
        $this->newLine();
        note('Verify the schedule with:');
        $this->line('  php artisan schedule:list');
        $this->newLine();
        note('Docker Registry Configuration:');
        $this->line('  Storage disk: '.config('packgrid.docker.disk', 'local'));
        $this->line('  Manual garbage collection: php artisan packgrid:docker-gc');
        $this->line('  Preview cleanup: php artisan packgrid:docker-gc --dry-run');

        return Command::SUCCESS;
    }

    protected function calculateNewValues(array $baseEnv, bool $envExists): array
    {
        $newEnv = $baseEnv;
        $isLocal = $this->option('local');
        $folderName = basename(base_path());

        // APP_URL
        if ($this->option('url')) {
            $newEnv['APP_URL'] = $this->option('url');
        } else {
            $newEnv['APP_URL'] = $this->detectUrl($folderName, $isLocal);
        }

        // APP_ENV
        if ($this->option('env')) {
            $newEnv['APP_ENV'] = $this->option('env');
        } elseif (! $isLocal) {
            $newEnv['APP_ENV'] = 'production';
        }

        // APP_DEBUG
        if ($this->option('debug') !== null) {
            $newEnv['APP_DEBUG'] = $this->option('debug') === 'true' ? 'true' : 'false';
        } elseif (! $isLocal) {
            $newEnv['APP_DEBUG'] = 'false';
        }

        // APP_KEY - preserve if env exists and has a key
        if ($envExists && ! empty($baseEnv['APP_KEY'])) {
            $newEnv['APP_KEY'] = $baseEnv['APP_KEY'];
        }

        return $newEnv;
    }

    protected function detectUrl(string $folderName, bool $isLocal): string
    {
        if ($isLocal) {
            // Local mode: always use .test domain
            $baseName = preg_replace('/\.(test|local|dev)$/', '', $folderName);

            return "https://{$baseName}.test";
        }

        // Production mode
        if (str_contains($folderName, '.')) {
            // Folder already looks like a domain
            return "https://{$folderName}";
        }

        // Add .com
        return "https://{$folderName}.com";
    }

    protected function parseEnvFile(string $path): array
    {
        $content = File::get($path);
        $lines = explode("\n", $content);
        $env = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove surrounding quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $env[$key] = $value;
            }
        }

        return $env;
    }

    protected function buildEnvContent(array $env): string
    {
        $lines = [];

        foreach ($env as $key => $value) {
            // Quote values with spaces or special characters
            if (preg_match('/[\s#]/', $value) || $value === '') {
                $value = "\"{$value}\"";
            }

            $lines[] = "{$key}={$value}";
        }

        return implode("\n", $lines)."\n";
    }

    protected function buildChangesTable(array $current, array $new): array
    {
        $changes = [];
        $keysToCheck = ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_KEY'];

        foreach ($keysToCheck as $key) {
            $currentValue = $current[$key] ?? '(not set)';
            $newValue = $new[$key] ?? '(not set)';

            // Mask APP_KEY for display
            if ($key === 'APP_KEY') {
                $currentValue = $this->maskKey($currentValue);
                $newValue = $this->maskKey($newValue);
            }

            if ($currentValue !== $newValue) {
                $changes[] = [$key, $currentValue, $newValue];
            }
        }

        return $changes;
    }

    protected function maskKey(string $key): string
    {
        if ($key === '' || $key === '(not set)') {
            return '(will be generated)';
        }

        if (strlen($key) > 20) {
            return substr($key, 0, 15).'...'.substr($key, -5);
        }

        return $key;
    }
}
