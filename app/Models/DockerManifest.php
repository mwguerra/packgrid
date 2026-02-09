<?php

namespace App\Models;

use App\Enums\DockerMediaType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DockerManifest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'docker_repository_id',
        'digest',
        'media_type',
        'content',
        'size',
        'layer_digests',
        'config_digest',
        'architecture',
        'os',
    ];

    protected $casts = [
        'media_type' => DockerMediaType::class,
        'size' => 'integer',
        'layer_digests' => 'array',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(DockerRepository::class, 'docker_repository_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(DockerTag::class);
    }

    public function getDecodedContentAttribute(): array
    {
        return json_decode($this->content, true) ?? [];
    }

    public function getLayerCountAttribute(): int
    {
        return count($this->layer_digests ?? []);
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

    public function isMultiArch(): bool
    {
        return in_array($this->media_type, [
            DockerMediaType::ManifestList,
            DockerMediaType::OciIndex,
        ]);
    }

    public function getPlatformAttribute(): ?string
    {
        if ($this->architecture && $this->os) {
            return "{$this->os}/{$this->architecture}";
        }

        return null;
    }
}
