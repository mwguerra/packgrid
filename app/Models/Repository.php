<?php

namespace App\Models;

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'repo_full_name',
        'url',
        'visibility',
        'format',
        'credential_id',
        'enabled',
        'package_count',
        'download_count',
        'clone_enabled',
        'clone_count',
        'last_sync_at',
        'last_error',
        'ref_filter',
    ];

    protected $casts = [
        'visibility' => RepositoryVisibility::class,
        'format' => PackageFormat::class,
        'enabled' => 'boolean',
        'package_count' => 'integer',
        'download_count' => 'integer',
        'clone_enabled' => 'boolean',
        'clone_count' => 'integer',
        'last_sync_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function tokens(): BelongsToMany
    {
        return $this->belongsToMany(Token::class);
    }

    public function needsSync(): bool
    {
        return $this->last_sync_at === null || $this->last_sync_at->lt(now()->subMinute());
    }

    /**
     * Hours after which an enabled, error-free repository is considered "stale"
     * on the dashboard. The scheduler syncs every 4 hours, so anything older
     * than this (or never synced) likely means a stuck or skipped sync.
     */
    public const DASHBOARD_STALE_HOURS = 5;

    /**
     * Enabled, error-free repositories that have never synced or are overdue.
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query
            ->where('enabled', true)
            ->whereNull('last_error')
            ->where(function (Builder $query): void {
                $query->whereNull('last_sync_at')
                    ->orWhere('last_sync_at', '<', now()->subHours(self::DASHBOARD_STALE_HOURS));
            });
    }
}
