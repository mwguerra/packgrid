<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class OrderedSchema extends Component
{
    protected string $view = 'filament.schemas.components.ordered-schema';

    protected int|Closure|null $number = null;

    protected string|Closure|null $customView = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function number(int|Closure $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->evaluate($this->number);
    }

    public function customView(string|Closure $view): static
    {
        $this->customView = $view;

        return $this;
    }

    public function getCustomView(): ?string
    {
        return $this->evaluate($this->customView);
    }
}
