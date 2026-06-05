<?php

use App\Models\Token;
use App\Services\PackageMetadataStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('stores only the hash of a token, never the plaintext', function () {
    $token = Token::factory()->create(['token' => 'super-secret-token-value-123456']);

    expect(Schema::hasColumn('tokens', 'token'))->toBeFalse();
    expect(Schema::hasColumn('tokens', 'token_hash'))->toBeTrue();

    $stored = DB::table('tokens')->where('id', $token->id)->value('token_hash');
    expect($stored)->toBe(hash('sha256', 'super-secret-token-value-123456'));
});

it('exposes the plaintext only in-memory right after creation', function () {
    $token = Token::factory()->create(['token' => 'one-time-visible-token-1234567']);

    expect($token->plainTextToken)->toBe('one-time-visible-token-1234567');
    expect($token->token)->toBe('one-time-visible-token-1234567');

    $fresh = Token::find($token->id);
    expect($fresh->plainTextToken)->toBeNull();
    expect($fresh->token)->toBeNull();
});

it('finds a token by its plaintext value through the hash', function () {
    $token = Token::factory()->create(['token' => 'lookup-me-by-hash-please-12345']);

    expect(Token::findByPlainText('lookup-me-by-hash-please-12345')?->id)->toBe($token->id);
    expect(Token::findByPlainText('wrong-value'))->toBeNull();
    expect(Token::plainTextExists('lookup-me-by-hash-please-12345'))->toBeTrue();
    expect(Token::plainTextExists('nope'))->toBeFalse();
});

it('authenticates composer requests using the plaintext token', function () {
    Storage::fake('local');
    Token::factory()->create(['token' => 'composer-auth-token-1234567890']);
    app(PackageMetadataStore::class)->writePackagesIndex(['packages' => []]);

    $this->withBasicAuth('composer', 'composer-auth-token-1234567890')
        ->get('/packages.json')
        ->assertOk();

    $this->withBasicAuth('composer', 'not-the-real-token')
        ->get('/packages.json')
        ->assertStatus(401);
});

it('rotates a token, invalidating the old value and accepting the new one', function () {
    Storage::fake('local');
    app(PackageMetadataStore::class)->writePackagesIndex(['packages' => []]);

    $token = Token::factory()->create(['token' => 'original-rotation-token-123456']);
    $originalHash = $token->fresh()->token_hash;

    $newValue = $token->rotate();

    expect($newValue)->not->toBe('original-rotation-token-123456');
    expect($token->fresh()->token_hash)->not->toBe($originalHash);

    $this->withBasicAuth('composer', 'original-rotation-token-123456')
        ->get('/packages.json')
        ->assertStatus(401);

    $this->withBasicAuth('composer', $newValue)
        ->get('/packages.json')
        ->assertOk();
});

it('backfills hashes and drops the plaintext column when migrating legacy data', function () {
    // Recreate the original (pre-hashing) tokens schema with a plaintext column.
    Schema::dropIfExists('tokens');
    Schema::create('tokens', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->json('allowed_ips')->nullable();
        $table->json('allowed_domains')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
        $table->index('token');
        $table->index('enabled');
    });

    $id = (string) Str::uuid();
    DB::table('tokens')->insert([
        'id' => $id,
        'name' => 'Legacy token',
        'token' => 'legacy-plaintext-token-1234567',
        'enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_06_05_035557_hash_existing_tokens.php');
    $migration->up();

    expect(Schema::hasColumn('tokens', 'token'))->toBeFalse();
    expect(Schema::hasColumn('tokens', 'token_hash'))->toBeTrue();
    expect(DB::table('tokens')->where('id', $id)->value('token_hash'))
        ->toBe(hash('sha256', 'legacy-plaintext-token-1234567'));
    expect(Token::findByPlainText('legacy-plaintext-token-1234567')?->id)->toBe($id);
});
