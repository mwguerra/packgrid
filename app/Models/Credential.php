<?php

namespace App\Models;

use App\Enums\CredentialStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credential extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'provider',
        'token',
        'username',
        'status',
        'last_checked_at',
        'last_error',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'status' => CredentialStatus::class,
        'last_checked_at' => 'datetime',
    ];

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }
}
