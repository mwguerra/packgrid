<?php

namespace App\Models;

use App\Enums\DockerMediaType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DockerBlob extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'digest',
        'size',
        'media_type',
        'storage_path',
        'reference_count',
    ];

    protected $casts = [
        'size' => 'integer',
        'media_type' => DockerMediaType::class,
        'reference_count' => 'integer',
    ];

    public function repositories(): BelongsToMany
    {
        return $this->belongsToMany(DockerRepository::class, 'docker_blob_repository')
            ->withTimestamps();
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    public function getShortDigestAttribute(): string
    {
        $parts = explode(':', $this->digest);

        return $parts[0].':'.substr($parts[1] ?? '', 0, 12);
    }

    public function incrementReferenceCount(): void
    {
        $this->increment('reference_count');
    }

    public function decrementReferenceCount(): void
    {
        $this->decrement('reference_count');
    }

    public function isOrphaned(): bool
    {
        return $this->reference_count <= 0;
    }
}
