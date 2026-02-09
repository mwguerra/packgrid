<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DockerTag extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'docker_repository_id',
        'docker_manifest_id',
        'name',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(DockerRepository::class, 'docker_repository_id');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(DockerManifest::class, 'docker_manifest_id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->repository->name.':'.$this->name;
    }
}
