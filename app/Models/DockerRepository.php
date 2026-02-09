<?php

namespace App\Models;

use App\Enums\RepositoryVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DockerRepository extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'visibility',
        'description',
        'enabled',
        'total_size',
        'pull_count',
        'push_count',
        'tag_count',
        'manifest_count',
        'last_push_at',
        'last_pull_at',
    ];

    protected $casts = [
        'visibility' => RepositoryVisibility::class,
        'enabled' => 'boolean',
        'total_size' => 'integer',
        'pull_count' => 'integer',
        'push_count' => 'integer',
        'tag_count' => 'integer',
        'manifest_count' => 'integer',
        'last_push_at' => 'datetime',
        'last_pull_at' => 'datetime',
    ];

    public function manifests(): HasMany
    {
        return $this->hasMany(DockerManifest::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(DockerTag::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(DockerUpload::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DockerActivity::class);
    }

    public function blobs(): BelongsToMany
    {
        return $this->belongsToMany(DockerBlob::class, 'docker_blob_repository')
            ->withTimestamps();
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->total_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    public function incrementPullCount(): void
    {
        $this->increment('pull_count');
        $this->forceFill(['last_pull_at' => now()])->save();
    }

    public function incrementPushCount(): void
    {
        $this->increment('push_count');
        $this->forceFill(['last_push_at' => now()])->save();
    }

    public function updateStatistics(): void
    {
        $this->forceFill([
            'tag_count' => $this->tags()->count(),
            'manifest_count' => $this->manifests()->count(),
            'total_size' => $this->calculateTotalSize(),
        ])->save();
    }

    protected function calculateTotalSize(): int
    {
        return $this->blobs()->sum('size');
    }
}
