<?php

namespace App\Enums;

enum DockerActivityType: string
{
    case Push = 'push';
    case Pull = 'pull';
    case Delete = 'delete';
    case Mount = 'mount';

    public function label(): string
    {
        return match ($this) {
            self::Push => 'Push',
            self::Pull => 'Pull',
            self::Delete => 'Delete',
            self::Mount => 'Mount',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Push => 'heroicon-o-arrow-up-tray',
            self::Pull => 'heroicon-o-arrow-down-tray',
            self::Delete => 'heroicon-o-trash',
            self::Mount => 'heroicon-o-link',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Push => 'success',
            self::Pull => 'info',
            self::Delete => 'danger',
            self::Mount => 'warning',
        };
    }
}
