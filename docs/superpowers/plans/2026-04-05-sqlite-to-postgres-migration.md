# SQLite to PostgreSQL Migration Command — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `packgrid:migrate-to-postgres` artisan command that copies all application data from SQLite to PostgreSQL, preserving IDs, UUIDs, encrypted values, and timestamps.

**Architecture:** Single artisan command class using Laravel's DB facade with two connections (default SQLite + runtime-configured `pgsql_target`). Reads in chunks, applies type conversions (booleans, JSON), writes via `DB::connection('pgsql_target')->table()->insert()`. Built-in `--verify` flag for post-migration row-count validation.

**Tech Stack:** Laravel 11, DB facade, Laravel Prompts, Pest 3

**Spec:** `docs/superpowers/specs/2026-04-05-sqlite-to-postgres-migration-design.md`

---

### Task 1: Create the Command Skeleton

**Files:**
- Create: `app/Console/Commands/MigrateToPostgresCommand.php`

- [ ] **Step 1: Generate the command via artisan**

```bash
cd /home/guerra/projects/packgrid && php artisan make:command MigrateToPostgresCommand
```

- [ ] **Step 2: Implement the command signature, properties, and input gathering**

Replace the generated file content with the full command skeleton. This includes:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class MigrateToPostgresCommand extends Command
{
    protected $signature = 'packgrid:migrate-to-postgres
                            {database? : PostgreSQL database name}
                            {username? : PostgreSQL username}
                            {--host=127.0.0.1 : PostgreSQL host}
                            {--port=5432 : PostgreSQL port}
                            {--verify : Verify data integrity after migration}';

    protected $description = 'Migrate all application data from SQLite to PostgreSQL';

    /**
     * Tables to migrate, in FK-dependency order.
     * Chunk size per table (default 500, smaller for large-content tables).
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

    /**
     * Boolean columns per table that need 0/1 → true/false conversion.
     */
    private const BOOLEAN_COLUMNS = [
        'repositories' => ['enabled', 'clone_enabled'],
        'docker_repositories' => ['enabled'],
        'tokens' => ['enabled'],
        'settings' => ['composer_enabled', 'npm_enabled', 'docker_enabled', 'git_enabled'],
    ];

    /**
     * JSON columns per table — no conversion needed, SQLite stores valid JSON
     * text that PostgreSQL json/jsonb columns accept directly. Listed here
     * for documentation purposes only.
     */

    /**
     * Tables with integer auto-increment PKs that need sequence resets.
     */
    private const SEQUENCE_TABLES = ['users', 'settings'];

    public function handle(): int
    {
        // Production warning
        if (app()->environment('production')) {
            warning('You are running this command in PRODUCTION.');
            warning('This will copy data from your SQLite database to a PostgreSQL database.');
            if (! confirm('Are you sure you want to continue?', default: false)) {
                info('Migration cancelled.');
                return self::SUCCESS;
            }
        }

        // Gather connection details
        $database = $this->argument('database') ?? text('PostgreSQL database name:', required: true);
        $username = $this->argument('username') ?? text('PostgreSQL username:', required: true);
        $pw = password('PostgreSQL password:', required: true);
        $host = $this->option('host');
        $port = $this->option('port');

        // Configure runtime connection
        $this->configureTargetConnection($database, $username, $pw, $host, $port);

        // Test connection
        if (! $this->testConnection()) {
            return self::FAILURE;
        }

        // Run migrations on target
        info('Running migrations on PostgreSQL...');
        Artisan::call('migrate', [
            '--database' => 'pgsql_target',
            '--force' => true,
        ]);
        info('Migrations complete.');

        // Check for existing data
        if (! $this->handleExistingData()) {
            return self::SUCCESS;
        }

        // Copy data
        $this->newLine();
        info('Starting data migration...');
        $this->newLine();

        try {
            DB::connection('pgsql_target')->beginTransaction();
            DB::connection('pgsql_target')->statement("SET session_replication_role = 'replica'");

            foreach (self::TABLES as $table => $chunkSize) {
                $this->migrateTable($table, $chunkSize);
            }

            $this->resetSequences();

            DB::connection('pgsql_target')->statement("SET session_replication_role = 'default'");
            DB::connection('pgsql_target')->commit();
        } catch (\Throwable $e) {
            DB::connection('pgsql_target')->statement("SET session_replication_role = 'default'");
            DB::connection('pgsql_target')->rollBack();
            error("Migration failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->newLine();
        info('Data migration complete!');

        // Verify if requested
        if ($this->option('verify')) {
            return $this->verify();
        }

        return self::SUCCESS;
    }

    private function configureTargetConnection(string $database, string $username, string $password, string $host, string $port): void
    {
        Config::set('database.connections.pgsql_target', [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
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
                // Table may not exist yet — that's fine
            }
        }

        if (! $hasData) {
            return true;
        }

        warning('The PostgreSQL database already contains data.');
        $wipe = confirm('Wipe all existing data and start fresh?', default: false);

        if (! $wipe) {
            info('Migration aborted. No changes were made.');
            return false;
        }

        info('Wiping PostgreSQL database...');
        Artisan::call('migrate:fresh', [
            '--database' => 'pgsql_target',
            '--force' => true,
        ]);
        info('Database wiped and migrations re-run.');
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

        DB::table($table)->orderBy(
            DB::raw('rowid')
        )->chunk($chunkSize, function ($rows) use ($table, $booleanCols, &$migrated) {
            $batch = [];

            foreach ($rows as $row) {
                $record = (array) $row;

                foreach ($booleanCols as $col) {
                    if (array_key_exists($col, $record)) {
                        $record[$col] = (bool) $record[$col];
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
```

- [ ] **Step 3: Verify the command is registered**

```bash
cd /home/guerra/projects/packgrid && php artisan list packgrid
```

Expected: `packgrid:migrate-to-postgres` appears in the list.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/MigrateToPostgresCommand.php
git commit -m "feat: add packgrid:migrate-to-postgres command for SQLite to PostgreSQL migration"
```

---

### Task 2: Write the Pest Test

**Files:**
- Create: `tests/Feature/MigrateToPostgresCommandTest.php`

This test uses docker-local PostgreSQL. It seeds SQLite, runs the command, and verifies the data was migrated correctly. The test is skipped if PostgreSQL is not available.

- [ ] **Step 1: Check docker-local postgres availability**

```bash
cd /home/guerra/projects/packgrid && docker-local list 2>/dev/null | grep -i postgres
```

If postgres is available, note the host/port/credentials. If not, check standard docker-local postgres defaults (typically `127.0.0.1:5432` with user `postgres` and password `postgres`, or check via `docker-local info postgres`).

- [ ] **Step 2: Create the PostgreSQL test database**

The test needs a dedicated database. Create it:

```bash
# Adjust credentials based on docker-local output from Step 1
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE packgrid_test_migration;" 2>/dev/null || true
```

- [ ] **Step 3: Write the test file**

Create `tests/Feature/MigrateToPostgresCommandTest.php`:

```php
<?php

use App\Enums\CredentialStatus;
use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Models\Credential;
use App\Models\DockerRepository;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Token;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Configure target postgres connection for tests
    $host = env('TEST_PG_HOST', '127.0.0.1');
    $port = env('TEST_PG_PORT', '5432');
    $database = env('TEST_PG_DATABASE', 'packgrid_test_migration');
    $username = env('TEST_PG_USERNAME', 'postgres');
    $password = env('TEST_PG_PASSWORD', 'postgres');

    Config::set('database.connections.pgsql_target', [
        'driver' => 'pgsql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ]);

    // Test if postgres is available, skip if not
    try {
        DB::connection('pgsql_target')->getPdo();
    } catch (\Throwable) {
        $this->markTestSkipped('PostgreSQL is not available for testing.');
    }

    // Wipe postgres before each test
    $this->artisan('migrate:fresh', [
        '--database' => 'pgsql_target',
        '--force' => true,
    ]);
});

test('migrates all table row counts correctly', function () {
    // Seed SQLite with the full seeder
    $this->seed();

    $tables = [
        'users', 'settings', 'credentials', 'tokens', 'repositories',
        'docker_repositories', 'docker_blobs', 'docker_manifests',
        'docker_tags', 'docker_uploads', 'docker_activities',
        'sync_logs', 'download_logs', 'repository_token',
        'clone_repository_token', 'docker_repository_token',
        'docker_blob_repository',
    ];

    // Run the command — supply connection details as arguments, password via mock
    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
        '--verify' => true,
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    // Double-check row counts ourselves
    foreach ($tables as $table) {
        $sqliteCount = DB::table($table)->count();
        $pgCount = DB::connection('pgsql_target')->table($table)->count();

        expect($pgCount)->toBe($sqliteCount, "Row count mismatch for table: {$table}");
    }
});

test('preserves UUIDs exactly', function () {
    $credential = Credential::factory()->create([
        'name' => 'UUID Test Credential',
        'status' => CredentialStatus::Ok,
    ]);

    $repo = Repository::factory()->create([
        'credential_id' => $credential->id,
        'format' => PackageFormat::Composer,
        'visibility' => RepositoryVisibility::PrivateRepo,
    ]);

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    $pgCredential = DB::connection('pgsql_target')->table('credentials')->where('id', $credential->id)->first();
    $pgRepo = DB::connection('pgsql_target')->table('repositories')->where('id', $repo->id)->first();

    expect($pgCredential)->not->toBeNull();
    expect($pgCredential->id)->toBe($credential->id);
    expect($pgRepo)->not->toBeNull();
    expect($pgRepo->id)->toBe($repo->id);
    expect($pgRepo->credential_id)->toBe($credential->id);
});

test('converts booleans correctly', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
        'git_enabled' => false,
    ]);

    Token::factory()->create(['enabled' => true]);
    Token::factory()->create(['enabled' => false]);

    Repository::factory()->create([
        'enabled' => true,
        'clone_enabled' => false,
        'format' => PackageFormat::Composer,
        'visibility' => RepositoryVisibility::PrivateRepo,
    ]);

    DockerRepository::factory()->create(['enabled' => true]);
    DockerRepository::factory()->create(['enabled' => false]);

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    // PostgreSQL returns actual booleans via PDO
    $pgSettings = DB::connection('pgsql_target')->table('settings')->first();
    expect($pgSettings->composer_enabled)->toBeTrue();
    expect($pgSettings->npm_enabled)->toBeFalse();
    expect($pgSettings->docker_enabled)->toBeTrue();
    expect($pgSettings->git_enabled)->toBeFalse();

    $enabledToken = DB::connection('pgsql_target')->table('tokens')->where('enabled', true)->first();
    $disabledToken = DB::connection('pgsql_target')->table('tokens')->where('enabled', false)->first();
    expect($enabledToken)->not->toBeNull();
    expect($disabledToken)->not->toBeNull();
});

test('preserves JSON columns', function () {
    $token = Token::factory()->create([
        'allowed_ips' => ['127.0.0.1', '10.0.0.0/8'],
        'allowed_domains' => ['*.example.com', 'deploy.internal.io'],
    ]);

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    $pgToken = DB::connection('pgsql_target')->table('tokens')->where('id', $token->id)->first();
    $ips = json_decode($pgToken->allowed_ips, true);
    $domains = json_decode($pgToken->allowed_domains, true);

    expect($ips)->toBe(['127.0.0.1', '10.0.0.0/8']);
    expect($domains)->toBe(['*.example.com', 'deploy.internal.io']);
});

test('encrypted credential token transfers and decrypts correctly', function () {
    $credential = Credential::factory()->create([
        'token' => 'my-secret-github-token',
    ]);

    // Read the raw encrypted value from SQLite
    $sqliteRaw = DB::table('credentials')->where('id', $credential->id)->value('token');

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    $pgRaw = DB::connection('pgsql_target')->table('credentials')->where('id', $credential->id)->value('token');

    // Raw encrypted values should be identical
    expect($pgRaw)->toBe($sqliteRaw);

    // Should decrypt to the original value
    expect(Crypt::decryptString($pgRaw))->toBe('my-secret-github-token');
});

test('resets auto-increment sequences correctly', function () {
    User::factory()->count(3)->create();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
        'git_enabled' => false,
    ]);

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    // Insert a new user on postgres — should not collide
    $maxUserId = DB::connection('pgsql_target')->table('users')->max('id');
    DB::connection('pgsql_target')->table('users')->insert([
        'name' => 'New PG User',
        'email' => 'newuser@test.com',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $newMaxId = DB::connection('pgsql_target')->table('users')->max('id');
    expect($newMaxId)->toBeGreaterThan($maxUserId);
});

test('migrates pivot table data', function () {
    $token = Token::factory()->create();
    $repo = Repository::factory()->create([
        'format' => PackageFormat::Composer,
        'visibility' => RepositoryVisibility::PrivateRepo,
    ]);
    $repo->tokens()->attach($token->id);

    $dockerRepo = DockerRepository::factory()->create();
    $dockerRepo->tokens()->attach($token->id);

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    $repoTokenCount = DB::connection('pgsql_target')->table('repository_token')->count();
    $dockerTokenCount = DB::connection('pgsql_target')->table('docker_repository_token')->count();

    expect($repoTokenCount)->toBe(1);
    expect($dockerTokenCount)->toBe(1);
});

test('preserves timestamps accurately', function () {
    $user = User::factory()->create([
        'created_at' => '2025-06-15 14:30:00',
        'updated_at' => '2025-12-25 08:00:00',
    ]);

    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    $pgUser = DB::connection('pgsql_target')->table('users')->where('id', $user->id)->first();

    expect($pgUser->created_at)->toBe('2025-06-15 14:30:00');
    expect($pgUser->updated_at)->toBe('2025-12-25 08:00:00');
});

test('handles existing data by asking to wipe or abort', function () {
    User::factory()->create();

    // Run first migration
    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    // Run again — should detect existing data and ask to wipe
    // Choose to wipe
    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
        '--verify' => true,
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->expectsConfirmation('Wipe all existing data and start fresh?', 'yes')
      ->assertSuccessful();
});

test('aborts when user declines wipe on existing data', function () {
    User::factory()->create();

    // Run first migration
    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->assertSuccessful();

    // Run again — decline the wipe
    $this->artisan('packgrid:migrate-to-postgres', [
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ])->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password'))
      ->expectsConfirmation('Wipe all existing data and start fresh?', 'no')
      ->assertSuccessful();
});
```

- [ ] **Step 4: Run the tests**

```bash
cd /home/guerra/projects/packgrid && php artisan test tests/Feature/MigrateToPostgresCommandTest.php
```

Expected: All tests pass (or skip if postgres is unavailable).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/MigrateToPostgresCommandTest.php
git commit -m "test: add Pest tests for packgrid:migrate-to-postgres command"
```

---

### Task 3: Run Full Seeded Migration Test Manually

This task validates the command end-to-end with the full database seeder, ensuring the command works in a realistic scenario.

- [ ] **Step 1: Ensure postgres database exists**

```bash
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -c "DROP DATABASE IF EXISTS packgrid_migration_manual;" 2>/dev/null
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE packgrid_migration_manual;" 2>/dev/null
```

- [ ] **Step 2: Seed the SQLite database**

```bash
cd /home/guerra/projects/packgrid && php artisan migrate:fresh --seed
```

- [ ] **Step 3: Run the migration command with --verify**

```bash
cd /home/guerra/projects/packgrid && php artisan packgrid:migrate-to-postgres packgrid_migration_manual postgres --host=127.0.0.1 --port=5432 --verify
```

Expected: All tables migrated with matching row counts, verification passes.

- [ ] **Step 4: Spot-check data in postgres**

```bash
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d packgrid_migration_manual -c "SELECT id, name, email FROM users;"
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d packgrid_migration_manual -c "SELECT id, name, enabled FROM tokens LIMIT 5;"
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d packgrid_migration_manual -c "SELECT count(*) FROM repository_token;"
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d packgrid_migration_manual -c "SELECT id, composer_enabled, npm_enabled, docker_enabled, git_enabled FROM settings;"
```

- [ ] **Step 5: Run the full test suite to ensure nothing is broken**

```bash
cd /home/guerra/projects/packgrid && php artisan test --parallel
```

Expected: All existing tests still pass.

- [ ] **Step 6: Commit the spec and plan docs together**

```bash
git add docs/superpowers/
git commit -m "docs: add SQLite to PostgreSQL migration spec and implementation plan"
```
