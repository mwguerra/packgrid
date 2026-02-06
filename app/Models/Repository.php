<?php

namespace App\Models;

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
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
}
