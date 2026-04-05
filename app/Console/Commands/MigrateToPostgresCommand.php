<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class MigrateToPostgresCommand extends Command
{
    protected $signature = 'packgrid:migrate-to-postgres
                            {database? : PostgreSQL database name}
                            {username? : PostgreSQL username}
                            {--host=127.0.0.1 : PostgreSQL host}
                            {--port=5432 : PostgreSQL port}
                            {--password= : PostgreSQL password (prompted if not provided)}
                            {--verify : Verify data integrity after migration}';

    protected $description = 'Migrate all application data from SQLite to PostgreSQL';

    /**
     * Tables to migrate, in FK-dependency order.
     * Value is the chunk size (smaller for large-content tables).
     */
    private const TABLES = [
        'users' => 500,
        'settings' => 500,
        'credentials' => 500,
        'tokens' => 500,
        'repositories' => 500,
        'docker_repositories' => 500,
        'docker_blobs' => 500,
        'docker_manifests' => 100,
        'docker_tags' => 500,
        'docker_uploads' => 500,
        'docker_activities' => 500,
        'sync_logs' => 500,
        'download_logs' => 500,
        'repository_token' => 500,
        'clone_repository_token' => 500,
        'docker_repository_token' => 500,
        'docker_blob_repository' => 500,
    ];

    private const BOOLEAN_COLUMNS = [
        'repositories' => ['enabled', 'clone_enabled'],
        'docker_repositories' => ['enabled'],
        'tokens' => ['enabled'],
        'settings' => ['composer_enabled', 'npm_enabled', 'docker_enabled', 'git_enabled'],
    ];

    /**
     * Integer columns that SQLite may store as floats (e.g., 1.2 * 1024 * 1024 * 1024).
     * PostgreSQL bigint rejects decimal values, so these need (int) casting.
     */
    private const INTEGER_COLUMNS = [
        'docker_repositories' => ['total_size', 'pull_count', 'push_count', 'download_count', 'tag_count', 'manifest_count'],
        'docker_blobs' => ['size', 'reference_count'],
        'docker_manifests' => ['size'],
        'docker_uploads' => ['uploaded_bytes', 'expected_size'],
        'docker_activities' => ['size'],
        'repositories' => ['package_count', 'download_count', 'clone_count'],
    ];

    private const SEQUENCE_TABLES = ['users', 'settings'];

    public function handle(): int
    {
        if (app()->environment('production')) {
            warning('You are running this command in PRODUCTION.');
            warning('This will copy data from your SQLite database to a PostgreSQL database.');
            if (! confirm('Are you sure you want to continue?', default: false)) {
                info('Migration cancelled.');

                return self::SUCCESS;
            }
        }

        $database = $this->argument('database') ?? $this->ask('PostgreSQL database name:');
        $username = $this->argument('username') ?? $this->ask('PostgreSQL username:');
        $pw = $this->option('password') ?? $this->secret('PostgreSQL password:');
        $host = $this->option('host');
        $port = $this->option('port');

        $this->configureTargetConnection($database, $username, $pw, $host, $port);

        if (! $this->testConnection()) {
            return self::FAILURE;
        }

        if (! $this->handleExistingData()) {
            return self::SUCCESS;
        }

        $this->newLine();
        info('Starting data migration...');
        $this->newLine();

        try {
            DB::connection('pgsql_target')->beginTransaction();

            foreach (self::TABLES as $table => $chunkSize) {
                $this->migrateTable($table, $chunkSize);
            }

            $this->resetSequences();

            DB::connection('pgsql_target')->commit();
        } catch (\Throwable $e) {
            DB::connection('pgsql_target')->rollBack();
            error("Migration failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        info('Data migration complete!');

        if ($this->option('verify')) {
            return $this->verify();
        }

        return self::SUCCESS;
    }

    private function configureTargetConnection(string $database, string $username, ?string $password, string $host, string $port): void
    {
        Config::set('database.connections.pgsql_target', [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
    }

    private function testConnection(): bool
    {
        try {
            DB::connection('pgsql_target')->getPdo();
            info('PostgreSQL connection successful.');

            return true;
        } catch (\Throwable $e) {
            error("Cannot connect to PostgreSQL: {$e->getMessage()}");

            return false;
        }
    }

    private function handleExistingData(): bool
    {
        $hasData = false;

        foreach (array_keys(self::TABLES) as $table) {
            try {
                if (DB::connection('pgsql_target')->table($table)->exists()) {
                    $hasData = true;
                    break;
                }
            } catch (\Throwable) {
                // Table doesn't exist yet
            }
        }

        if ($hasData) {
            warning('The PostgreSQL database already contains data.');
            $wipe = confirm('Wipe all existing data and start fresh?', default: false);

            if (! $wipe) {
                info('Migration aborted. No changes were made.');

                return false;
            }
        }

        // Temporarily switch default connection so migration seed queries
        // (like DB::table('settings')->insert(...)) target postgres, not SQLite
        $originalDefault = config('database.default');
        Config::set('database.default', 'pgsql_target');

        info('Running migrations on PostgreSQL...');
        Artisan::call('migrate:fresh', [
            '--database' => 'pgsql_target',
            '--force' => true,
        ]);
        info('Migrations complete.');

        Config::set('database.default', $originalDefault);

        // Truncate all tables to remove any data inserted by migrations (e.g. default settings row)
        // Use a single TRUNCATE ... CASCADE statement to handle FK dependencies
        $tableList = implode(', ', array_map(fn ($t) => "\"{$t}\"", array_keys(self::TABLES)));
        DB::connection('pgsql_target')->statement("TRUNCATE {$tableList} CASCADE");

        return true;
    }

    private function migrateTable(string $table, int $chunkSize): void
    {
        $total = DB::table($table)->count();

        if ($total === 0) {
            $this->line("  {$table}: 0 rows (skipped)");

            return;
        }

        $migrated = 0;
        $booleanCols = self::BOOLEAN_COLUMNS[$table] ?? [];
        $integerCols = self::INTEGER_COLUMNS[$table] ?? [];

        DB::table($table)->orderBy(
            DB::raw('rowid')
        )->chunk($chunkSize, function ($rows) use ($table, $booleanCols, $integerCols, &$migrated) {
            $batch = [];

            foreach ($rows as $row) {
                $record = (array) $row;

                foreach ($booleanCols as $col) {
                    if (array_key_exists($col, $record)) {
                        $record[$col] = (bool) $record[$col];
                    }
                }

                foreach ($integerCols as $col) {
                    if (array_key_exists($col, $record) && $record[$col] !== null) {
                        $record[$col] = (int) $record[$col];
                    }
                }

                $batch[] = $record;
            }

            DB::connection('pgsql_target')->table($table)->insert($batch);
            $migrated += count($batch);
        });

        $this->line("  {$table}: {$migrated} rows");
    }

    private function resetSequences(): void
    {
        foreach (self::SEQUENCE_TABLES as $table) {
            $maxId = DB::connection('pgsql_target')->table($table)->max('id');

            if ($maxId !== null) {
                $nextId = $maxId + 1;
                DB::connection('pgsql_target')->statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), {$nextId}, false)"
                );
            }
        }
    }

    private function verify(): int
    {
        $this->newLine();
        info('Verifying data integrity...');
        $this->newLine();

        $allMatch = true;

        foreach (array_keys(self::TABLES) as $table) {
            $sqliteCount = DB::table($table)->count();
            $pgCount = DB::connection('pgsql_target')->table($table)->count();

            if ($sqliteCount === $pgCount) {
                $this->line("  ✓ {$table}: {$pgCount} rows");
            } else {
                $this->line("  ✗ {$table}: SQLite={$sqliteCount}, PostgreSQL={$pgCount}");
                $allMatch = false;
            }
        }

        $this->newLine();

        if ($allMatch) {
            info('Verification passed — all row counts match.');

            return self::SUCCESS;
        }

        error('Verification failed — row count mismatches detected.');

        return self::FAILURE;
    }
}
