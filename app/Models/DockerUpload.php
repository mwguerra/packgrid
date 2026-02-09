<?php

namespace App\Models;

use App\Enums\DockerUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DockerUpload extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'docker_repository_id',
        'status',
        'temp_path',
        'uploaded_bytes',
        'expected_size',
        'expected_digest',
        'expires_at',
    ];

    protected $casts = [
        'status' => DockerUploadStatus::class,
        'uploaded_bytes' => 'integer',
        'expected_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(DockerRepository::class, 'docker_repository_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status->isActive() && ! $this->isExpired();
    }

    public function markAsUploading(): void
    {
        $this->forceFill(['status' => DockerUploadStatus::Uploading])->save();
    }

    public function markAsComplete(): void
    {
        $this->forceFill(['status' => DockerUploadStatus::Complete])->save();
    }

    public function markAsFailed(): void
    {
        $this->forceFill(['status' => DockerUploadStatus::Failed])->save();
    }

    public function getProgressPercentage(): ?float
    {
        if (! $this->expected_size || $this->expected_size === 0) {
            return null;
        }

        return round(($this->uploaded_bytes / $this->expected_size) * 100, 2);
    }
}
