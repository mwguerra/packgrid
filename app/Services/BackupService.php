<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BackupService
{
    private const BACKUP_VERSION = 1;

    /** Magic prefix identifying the modern authenticated-encryption format. */
    private const MAGIC = 'PGBK';

    /** Encryption format version (2 = Argon2id + XChaCha20-Poly1305 AEAD). */
    private const FORMAT_AEAD = 2;

    private const SKIPPED_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'sessions',
    ];

    /**
     * Encrypt with authenticated encryption: a key derived from the password via
     * Argon2id (memory-hard) and XChaCha20-Poly1305 AEAD, which guarantees both
     * confidentiality and integrity (tamper detection).
     */
    public function encrypt(string $data, string $password): string
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $header = self::MAGIC.chr(self::FORMAT_AEAD);

        // The header is bound as associated data so it cannot be altered.
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, $header, $nonce, $key);

        sodium_memzero($key);

        return $header.$salt.$nonce.$ciphertext;
    }

    /**
     * Decrypt a backup. Routes to the modern AEAD reader for new files and falls
     * back to the legacy AES-256-CBC reader so older backups remain restorable.
     */
    public function decrypt(string $encrypted, string $password): string
    {
        if (str_starts_with($encrypted, self::MAGIC)) {
            return $this->decryptAead($encrypted, $password);
        }

        return $this->decryptLegacyCbc($encrypted, $password);
    }

    private function decryptAead(string $encrypted, string $password): string
    {
        $headerLen = strlen(self::MAGIC) + 1;
        $saltLen = SODIUM_CRYPTO_PWHASH_SALTBYTES;
        $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $minLen = $headerLen + $saltLen + $nonceLen + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

        if (strlen($encrypted) < $minLen) {
            throw new RuntimeException('Invalid backup file.');
        }

        if (ord($encrypted[strlen(self::MAGIC)]) !== self::FORMAT_AEAD) {
            throw new RuntimeException('Unsupported backup format version.');
        }

        $header = substr($encrypted, 0, $headerLen);
        $salt = substr($encrypted, $headerLen, $saltLen);
        $nonce = substr($encrypted, $headerLen + $saltLen, $nonceLen);
        $ciphertext = substr($encrypted, $headerLen + $saltLen + $nonceLen);

        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $data = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $header, $nonce, $key);

        sodium_memzero($key);

        if ($data === false) {
            throw new RuntimeException('Decryption failed. Wrong password or corrupted file.');
        }

        return $data;
    }

    /**
     * Legacy reader for backups produced before the AEAD upgrade
     * (PBKDF2-SHA256/100k + AES-256-CBC, layout: salt|iv|ciphertext).
     */
    private function decryptLegacyCbc(string $encrypted, string $password): string
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

        $encrypted = $this->encrypt($json, $password);

        Setting::first()?->update(['last_backup_at' => now()]);

        return $encrypted;
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

                // DELETE (DML) rather than TRUNCATE (DDL) so the whole restore stays
                // inside one transaction and rolls back atomically on failure — TRUNCATE
                // implicitly commits on MySQL, which would leave a half-restored database.
                DB::table($table)->delete();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            $this->enableForeignKeyChecks($driver);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->enableForeignKeyChecks($driver);

            throw new RuntimeException('Restore failed: '.$e->getMessage());
        }

        Setting::first()?->update(['last_restore_at' => now()]);
    }

    /**
     * A summary of what a backup will contain, for display on the backup page.
     *
     * @return array{driver: string, table_count: int, record_count: int, tables: array<string, int>, encryption: string}
     */
    public function getBackupSummary(): array
    {
        $driver = DB::getDriverName();
        $tables = $this->getUserTables($driver);

        $perTable = [];
        $records = 0;

        foreach ($tables as $table) {
            $count = (int) DB::table($table)->count();
            $perTable[$table] = $count;
            $records += $count;
        }

        return [
            'driver' => $driver,
            'table_count' => count($tables),
            'record_count' => $records,
            'tables' => $perTable,
            'encryption' => 'Argon2id + XChaCha20-Poly1305',
        ];
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
        return Schema::hasTable($table);
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
