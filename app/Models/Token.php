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
        'token',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
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

    public function recordUsage(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
