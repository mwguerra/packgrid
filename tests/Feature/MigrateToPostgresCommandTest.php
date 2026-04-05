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
    $host = env('TEST_PG_HOST', '127.0.0.1');
    $port = env('TEST_PG_PORT', '5432');
    $database = env('TEST_PG_DATABASE', 'packgrid_test_migration');
    $username = env('TEST_PG_USERNAME', 'laravel');
    $password = env('TEST_PG_PASSWORD', 'secret');

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

    try {
        DB::connection('pgsql_target')->getPdo();
    } catch (\Throwable) {
        $this->markTestSkipped('PostgreSQL is not available for testing.');
    }

    // Drop all tables so each test starts with a truly empty postgres database
    DB::connection('pgsql_target')->statement('DROP SCHEMA public CASCADE');
    DB::connection('pgsql_target')->statement('CREATE SCHEMA public');
    DB::purge('pgsql_target');
});

function runMigrationCommand($test, array $extra = []): \Illuminate\Testing\PendingCommand
{
    return $test->artisan('packgrid:migrate-to-postgres', array_merge([
        'database' => config('database.connections.pgsql_target.database'),
        'username' => config('database.connections.pgsql_target.username'),
        '--host' => config('database.connections.pgsql_target.host'),
        '--port' => config('database.connections.pgsql_target.port'),
    ], $extra))
        ->expectsQuestion('PostgreSQL password:', config('database.connections.pgsql_target.password', 'secret'));
}

test('migrates all table row counts correctly', function () {
    $this->seed();

    $tables = [
        'users', 'settings', 'credentials', 'tokens', 'repositories',
        'docker_repositories', 'docker_blobs', 'docker_manifests',
        'docker_tags', 'docker_uploads', 'docker_activities',
        'sync_logs', 'download_logs', 'repository_token',
        'clone_repository_token', 'docker_repository_token',
        'docker_blob_repository',
    ];

    // Capture SQLite counts BEFORE running command
    $expectedCounts = [];
    foreach ($tables as $table) {
        $expectedCounts[$table] = DB::table($table)->count();
    }

    runMigrationCommand($this)->assertSuccessful();

    foreach ($tables as $table) {
        $pgCount = DB::connection('pgsql_target')->table($table)->count();
        expect($pgCount)->toBe($expectedCounts[$table], "Row count mismatch for table: {$table}");
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

    runMigrationCommand($this)->assertSuccessful();

    $pgCredential = DB::connection('pgsql_target')->table('credentials')->where('id', $credential->id)->first();
    $pgRepo = DB::connection('pgsql_target')->table('repositories')->where('id', $repo->id)->first();

    expect($pgCredential)->not->toBeNull();
    expect($pgCredential->id)->toBe($credential->id);
    expect($pgRepo)->not->toBeNull();
    expect($pgRepo->id)->toBe($repo->id);
    expect($pgRepo->credential_id)->toBe($credential->id);
});

test('converts booleans correctly', function () {
    // Remove default settings row inserted by migration
    Setting::query()->delete();
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

    runMigrationCommand($this)->assertSuccessful();

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

    runMigrationCommand($this)->assertSuccessful();

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

    $sqliteRaw = DB::table('credentials')->where('id', $credential->id)->value('token');

    runMigrationCommand($this)->assertSuccessful();

    $pgRaw = DB::connection('pgsql_target')->table('credentials')->where('id', $credential->id)->value('token');

    expect($pgRaw)->toBe($sqliteRaw);
    expect(Crypt::decryptString($pgRaw))->toBe('my-secret-github-token');
});

test('resets auto-increment sequences correctly', function () {
    User::factory()->count(3)->create();
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
        'git_enabled' => false,
    ]);

    runMigrationCommand($this)->assertSuccessful();

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

    runMigrationCommand($this)->assertSuccessful();

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

    runMigrationCommand($this)->assertSuccessful();

    $pgUser = DB::connection('pgsql_target')->table('users')->where('id', $user->id)->first();

    expect($pgUser->created_at)->toBe('2025-06-15 14:30:00');
    expect($pgUser->updated_at)->toBe('2025-12-25 08:00:00');
});

test('handles existing data by asking to wipe or abort', function () {
    User::factory()->create();

    runMigrationCommand($this)->assertSuccessful();

    runMigrationCommand($this, ['--verify' => true])
        ->expectsConfirmation('Wipe all existing data and start fresh?', 'yes')
        ->assertSuccessful();
});

test('aborts when user declines wipe on existing data', function () {
    User::factory()->create();

    runMigrationCommand($this)->assertSuccessful();

    runMigrationCommand($this)
        ->expectsConfirmation('Wipe all existing data and start fresh?', 'no')
        ->assertSuccessful();
});
