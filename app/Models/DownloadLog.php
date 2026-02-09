<?php

namespace App\Models;

use App\Enums\PackageFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadLog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'repository_id',
        'token_id',
        'package_version',
        'format',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'format' => PackageFormat::class,
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }

    public static function logDownload(Repository $repository, string $version, PackageFormat $format, ?Token $token = null): self
    {
        $repository->increment('download_count');

        return self::create([
            'repository_id' => $repository->id,
            'token_id' => $token?->id,
            'package_version' => $version,
            'format' => $format,
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
