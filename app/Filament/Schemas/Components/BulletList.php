<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class BulletList extends Component
{
    protected string $view = 'filament.schemas.components.bullet-list';

    protected array|Closure $items = [];

    protected string|Closure $bulletIcon = 'heroicon-s-arrow-right';

    protected string|Closure $bulletColor = 'gray';

    public static function make(array|Closure|null $items = null): static
    {
        $static = app(static::class);

        if ($items !== null) {
            $static->items($items);
        }

        return $static;
    }

    public function items(array|Closure $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function getItems(): array
    {
        return $this->evaluate($this->items);
    }

    public function bulletIcon(string|Closure $icon): static
    {
        $this->bulletIcon = $icon;

        return $this;
    }

    public function getBulletIcon(): string
    {
        return $this->evaluate($this->bulletIcon);
    }

    public function bulletColor(string|Closure $color): static
    {
        $this->bulletColor = $color;

        return $this;
    }

    public function getBulletColor(): string
    {
        return $this->evaluate($this->bulletColor);
    }
}
