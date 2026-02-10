<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'composer_enabled',
        'npm_enabled',
        'docker_enabled',
        'last_backup_at',
        'last_restore_at',
    ];

    protected $casts = [
        'composer_enabled' => 'boolean',
        'npm_enabled' => 'boolean',
        'docker_enabled' => 'boolean',
        'last_backup_at' => 'datetime',
        'last_restore_at' => 'datetime',
    ];
}
