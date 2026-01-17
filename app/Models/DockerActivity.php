<?php

namespace App\Models;

use App\Enums\DockerActivityType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DockerActivity extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'docker_repository_id',
        'type',
        'tag',
        'digest',
        'size',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'type' => DockerActivityType::class,
        'size' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(DockerRepository::class, 'docker_repository_id');
    }

    public function getShortDigestAttribute(): ?string
    {
        if (! $this->digest) {
            return null;
        }

        $parts = explode(':', $this->digest);

        return $parts[0].':'.substr($parts[1] ?? '', 0, 12);
    }

    public function getFormattedSizeAttribute(): ?string
    {
        if (! $this->size) {
            return null;
        }

        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    public static function logPush(DockerRepository $repository, ?string $tag, ?string $digest, ?int $size): self
    {
        return self::create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Push,
            'tag' => $tag,
            'digest' => $digest,
            'size' => $size,
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logPull(DockerRepository $repository, ?string $tag, ?string $digest, ?int $size): self
    {
        return self::create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Pull,
            'tag' => $tag,
            'digest' => $digest,
            'size' => $size,
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logDelete(DockerRepository $repository, ?string $tag, ?string $digest): self
    {
        return self::create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Delete,
            'tag' => $tag,
            'digest' => $digest,
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logMount(DockerRepository $repository, string $digest): self
    {
        return self::create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Mount,
            'digest' => $digest,
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
