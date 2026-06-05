<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Token extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The plaintext token value, only populated in-memory right after the
     * token is generated/rotated. It is never persisted; the database stores
     * only the SHA-256 hash. Use this to display the value to the user once.
     */
    public ?string $plainTextToken = null;

    protected $fillable = [
        'name',
        'token',
        'allowed_ips',
        'allowed_domains',
        'enabled',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'allowed_domains' => 'array',
        'enabled' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * One-way hash used for storing and looking up tokens. SHA-256 is
     * appropriate here because the token is a 64-char high-entropy random
     * string (brute-forcing the hash is infeasible regardless of speed).
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Find a token by its plaintext value via the stored hash.
     */
    public static function findByPlainText(string $plain): ?self
    {
        return static::query()->where('token_hash', static::hashToken($plain))->first();
    }

    /**
     * Whether a token with the given plaintext value exists.
     */
    public static function plainTextExists(string $plain): bool
    {
        return static::query()->where('token_hash', static::hashToken($plain))->exists();
    }

    /**
     * Set the token from a plaintext value: store only its hash and keep the
     * plaintext in memory for one-time display.
     */
    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token_hash'] = static::hashToken($value);
        $this->plainTextToken = $value;
    }

    /**
     * The plaintext token is only available in-memory immediately after
     * creation or rotation; otherwise this is null (it is not stored).
     */
    public function getTokenAttribute(): ?string
    {
        return $this->plainTextToken;
    }

    /**
     * Generate a fresh token value, replacing the stored hash. Returns the new
     * plaintext value (shown once) for display to the user.
     */
    public function rotate(): string
    {
        $this->token = static::generateToken();
        $this->save();

        return $this->plainTextToken;
    }

    public function isValid(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isAllowedFromIp(?string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        if (! $ip) {
            return false;
        }

        return in_array($ip, $this->allowed_ips, true);
    }

    public function isAllowedFromDomain(?string $domain): bool
    {
        if (empty($this->allowed_domains)) {
            return true;
        }

        if (! $domain) {
            return false;
        }

        foreach ($this->allowed_domains as $allowed) {
            if ($domain === $allowed || Str::endsWith($domain, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public function repositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class);
    }

    public function isAllowedForRepository(Repository $repository): bool
    {
        if ($this->repositories()->count() === 0) {
            return true;
        }

        return $this->repositories()->where('repositories.id', $repository->id)->exists();
    }

    public function dockerRepositories(): BelongsToMany
    {
        return $this->belongsToMany(DockerRepository::class);
    }

    public function isAllowedForDockerRepository(DockerRepository $repository): bool
    {
        if ($this->dockerRepositories()->count() === 0) {
            return true;
        }

        return $this->dockerRepositories()->where('docker_repositories.id', $repository->id)->exists();
    }

    public function cloneRepositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class, 'clone_repository_token');
    }

    public function isAllowedToCloneRepository(Repository $repository): bool
    {
        if ($this->cloneRepositories()->count() === 0) {
            return true;
        }

        return $this->cloneRepositories()->where('repositories.id', $repository->id)->exists();
    }

    public function recordUsage(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
