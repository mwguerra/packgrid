<?php

namespace App\Enums;

enum DockerUploadStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Complete = 'complete';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Uploading => 'Uploading',
            self::Complete => 'Complete',
            self::Failed => 'Failed',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Pending, self::Uploading => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Complete, self::Failed => true,
            default => false,
        };
    }
}
