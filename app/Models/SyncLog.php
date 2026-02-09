<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'repository_id',
        'status',
        'started_at',
        'finished_at',
        'error',
    ];

    protected $casts = [
        'status' => SyncStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
