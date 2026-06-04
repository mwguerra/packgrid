<?php

use App\Filament\Pages\BackupRestore;
use App\Models\Setting;
use App\Models\Token;
use App\Models\User;
use App\Services\BackupService;
use App\Support\PackgridSettings;
use Livewire\Livewire;

beforeEach(function () {
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();
});

test('backup service encrypts and decrypts data correctly', function () {
    $service = new BackupService;
    $original = 'Hello, this is secret data!';
    $password = 'test-password-123';

    $encrypted = $service->encrypt($original, $password);
    $decrypted = $service->decrypt($encrypted, $password);

    expect($decrypted)->toBe($original)
        ->and($encrypted)->not->toBe($original);
});

test('backup with wrong password fails to decrypt', function () {
    $service = new BackupService;
    $original = 'Secret data';
    $password = 'correct-password';
    $wrongPassword = 'wrong-password';

    $encrypted = $service->encrypt($original, $password);

    $service->decrypt($encrypted, $wrongPassword);
})->throws(RuntimeException::class);

test('backup creates valid encrypted file containing table data', function () {
    $service = new BackupService;
    $password = 'backup-test-password';

    User::factory()->create();
    Token::factory()->create();

    $encrypted = $service->createBackup($password);
    $json = $service->decrypt($encrypted, $password);
    $data = json_decode($json, true);

    expect($data)->toBeArray()
        ->and($data['version'])->toBe(1)
        ->and($data['tables'])->toBeArray()
        ->and($data['tables'])->toHaveKey('users')
        ->and($data['tables'])->toHaveKey('tokens')
        ->and($data['tables'])->toHaveKey('settings')
        ->and($data['created_at'])->toBeString()
        ->and($data['app_name'])->toBe(config('app.name'));
});

test('backup skips framework internal tables', function () {
    $service = new BackupService;
    $password = 'test-password-123';

    $encrypted = $service->createBackup($password);
    $json = $service->decrypt($encrypted, $password);
    $data = json_decode($json, true);

    expect($data['tables'])->not->toHaveKey('migrations')
        ->and($data['tables'])->not->toHaveKey('cache')
        ->and($data['tables'])->not->toHaveKey('cache_locks')
        ->and($data['tables'])->not->toHaveKey('jobs')
        ->and($data['tables'])->not->toHaveKey('failed_jobs')
        ->and($data['tables'])->not->toHaveKey('sessions');
});

test('restore from backup repopulates tables', function () {
    $service = new BackupService;
    $password = 'restore-test-password';

    // Create initial data
    $user = User::factory()->create(['name' => 'Original User']);
    Token::factory()->create(['name' => 'Original Token']);

    // Create backup
    $encrypted = $service->createBackup($password);

    // Modify data
    $user->update(['name' => 'Modified User']);
    Token::query()->delete();
    Token::factory()->count(3)->create();

    expect(Token::count())->toBe(3);

    // Restore
    $service->restoreBackup($encrypted, $password);

    // Verify original data is back
    expect(User::where('name', 'Original User')->exists())->toBeTrue()
        ->and(Token::where('name', 'Original Token')->exists())->toBeTrue()
        ->and(Token::count())->toBe(1);
});

test('restore with wrong password fails', function () {
    $service = new BackupService;

    $encrypted = $service->createBackup('correct-password');

    $service->restoreBackup($encrypted, 'wrong-password');
})->throws(RuntimeException::class);

test('restore sets last_restore_at on settings', function () {
    $service = new BackupService;
    $password = 'restore-timestamp-test';

    $encrypted = $service->createBackup($password);

    expect(Setting::first()->last_restore_at)->toBeNull();

    $service->restoreBackup($encrypted, $password);

    expect(Setting::first()->last_restore_at)->not->toBeNull();
});

test('backup restore page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/backup-restore')
        ->assertOk();
});

test('backup restore page requires authentication', function () {
    User::factory()->create();

    $this->get('/admin/backup-restore')
        ->assertRedirect('/admin/login');
});

test('backup restore page renders with livewire', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BackupRestore::class)
        ->assertOk();
});

test('backup restore page shows never when no backups exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BackupRestore::class)
        ->assertOk()
        ->assertSee(__('common.never'));
});

test('backup uses the modern authenticated encryption format', function () {
    $service = new BackupService;

    $encrypted = $service->encrypt('secret', 'pw-12345678');

    // The modern AEAD format is identified by the PGBK magic header.
    expect(str_starts_with($encrypted, 'PGBK'))->toBeTrue();
});

test('tampering with an encrypted backup is detected on decrypt', function () {
    $service = new BackupService;
    $encrypted = $service->encrypt('secret data', 'pw-12345678');

    // Flip a byte inside the ciphertext body (past the header+salt+nonce).
    $tampered = $encrypted;
    $pos = strlen($encrypted) - 1;
    $tampered[$pos] = $tampered[$pos] === 'A' ? 'B' : 'A';

    expect(fn () => $service->decrypt($tampered, 'pw-12345678'))
        ->toThrow(RuntimeException::class);
});

test('legacy AES-256-CBC backups can still be decrypted', function () {
    $service = new BackupService;
    $password = 'legacy-password';
    $plaintext = 'legacy backup payload';

    // Reproduce the old format: salt(16) | iv(16) | AES-256-CBC ciphertext (PBKDF2-SHA256/100k).
    $salt = random_bytes(16);
    $iv = random_bytes(16);
    $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $legacy = $salt.$iv.$ciphertext;

    expect($service->decrypt($legacy, $password))->toBe($plaintext);
});

test('getBackupSummary reports tables, records and the encryption method', function () {
    User::factory()->create();
    Token::factory()->count(2)->create();

    $summary = (new BackupService)->getBackupSummary();

    expect($summary['table_count'])->toBeGreaterThan(0)
        ->and($summary['record_count'])->toBeGreaterThanOrEqual(3)
        ->and($summary['encryption'])->toContain('XChaCha20-Poly1305')
        ->and($summary['tables'])->toHaveKey('users')
        ->and($summary['driver'])->toBeString();
});

test('createBackup records last_backup_at on the settings row', function () {
    expect(Setting::first()->last_backup_at)->toBeNull();

    (new BackupService)->createBackup('pw-12345678');

    expect(Setting::first()->last_backup_at)->not->toBeNull();
});

test('backup restore page shows the system state and encryption method', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BackupRestore::class)
        ->assertOk()
        ->assertSee('XChaCha20-Poly1305')
        // The Docker-blobs note moved from the sidebar into the Create Backup modal.
        ->assertDontSee(__('backup.state.docker_blobs_note'));
});

// Filament v5 lazy-renders modal content client-side, so the callout text is not
// in the server HTML; these assert the form builds/mounts in both Docker states
// (the actual security + blobs copy is verified end-to-end via Playwright).
test('create backup modal mounts with the security guidance form (docker enabled)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BackupRestore::class)
        ->mountAction('createBackup')
        ->assertActionMounted('createBackup');
});

test('create backup modal mounts when docker is disabled (no blobs warning)', function () {
    Setting::query()->update(['docker_enabled' => false]);
    PackgridSettings::clearCache();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BackupRestore::class)
        ->mountAction('createBackup')
        ->assertActionMounted('createBackup');
});
