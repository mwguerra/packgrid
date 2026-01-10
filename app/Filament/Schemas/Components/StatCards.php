<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class StatCards extends Component
{
    protected string $view = 'filament.schemas.components.stat-cards';

    protected array|Closure $cards = [];

    protected int|Closure $gridColumns = 3;

    public static function make(): static
    {
        return app(static::class);
    }

    public function cards(array|Closure $cards): static
    {
        $this->cards = $cards;

        return $this;
    }

    public function getCards(): array
    {
        $cards = $this->evaluate($this->cards);

        return array_map(function ($card) {
            if ($card instanceof StatCard) {
                return $card->toArray();
            }

            return $card;
        }, $cards);
    }

    public function gridColumns(int|Closure $gridColumns): static
    {
        $this->gridColumns = $gridColumns;

        return $this;
    }

    public function getGridColumns(): int
    {
        return $this->evaluate($this->gridColumns);
    }
}
