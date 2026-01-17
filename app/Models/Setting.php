<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'composer_enabled',
        'npm_enabled',
        'docker_enabled',
    ];

    protected $casts = [
        'composer_enabled' => 'boolean',
        'npm_enabled' => 'boolean',
        'docker_enabled' => 'boolean',
    ];
}
