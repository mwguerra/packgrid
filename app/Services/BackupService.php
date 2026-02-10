<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackupService
{
    private const BACKUP_VERSION = 1;

    private const SKIPPED_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'sessions',
    ];

    public function encrypt(string $data, string $password): string
    {
        $salt = random_bytes(16);
        $iv = random_bytes(16);
        $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);

        $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return $salt . $iv . $ciphertext;
    }

    public function decrypt(string $encrypted, string $password): string
    {
        if (strlen($encrypted) < 33) {
            throw new RuntimeException('Invalid backup file.');
        }

        $salt = substr($encrypted, 0, 16);
        $iv = substr($encrypted, 16, 16);
        $ciphertext = substr($encrypted, 32);
        $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);

        $data = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($data === false) {
            throw new RuntimeException('Decryption failed. Wrong password or corrupted file.');
        }

        return $data;
    }

    public function createBackup(string $password): string
    {
        $driver = DB::getDriverName();
        $tables = $this->getUserTables($driver);

        $data = [
            'version' => self::BACKUP_VERSION,
            'created_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'driver' => $driver,
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $data['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->toArray();
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $this->encrypt($json, $password);
    }

    public function restoreBackup(string $encryptedContent, string $password): void
    {
        $json = $this->decrypt($encryptedContent, $password);

        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['version'], $data['tables'])) {
            throw new RuntimeException('Invalid backup format.');
        }

        $driver = DB::getDriverName();

        DB::beginTransaction();

        try {
            $this->disableForeignKeyChecks($driver);

            foreach ($data['tables'] as $table => $rows) {
                if (! $this->tableExists($table)) {
                    continue;
                }

                DB::table($table)->truncate();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            $this->enableForeignKeyChecks($driver);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->enableForeignKeyChecks($driver);

            throw new RuntimeException('Restore failed: ' . $e->getMessage());
        }

        Setting::first()?->update(['last_restore_at' => now()]);
    }

    private function getUserTables(string $driver): array
    {
        $tables = match ($driver) {
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->toArray(),
            'mysql' => collect(DB::select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0])
                ->toArray(),
            'pgsql' => collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"))
                ->pluck('tablename')
                ->toArray(),
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        return array_values(array_filter($tables, function (string $table) {
            if (in_array($table, self::SKIPPED_TABLES, true)) {
                return false;
            }

            if (str_starts_with($table, 'pulse_')) {
                return false;
            }

            return true;
        }));
    }

    private function tableExists(string $table): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable($table);
    }

    private function disableForeignKeyChecks(string $driver): void
    {
        match ($driver) {
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS = 0'),
            'pgsql' => DB::statement('SET session_replication_role = replica'),
            default => null,
        };
    }

    private function enableForeignKeyChecks(string $driver): void
    {
        match ($driver) {
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS = 1'),
            'pgsql' => DB::statement('SET session_replication_role = DEFAULT'),
            default => null,
        };
    }
}
