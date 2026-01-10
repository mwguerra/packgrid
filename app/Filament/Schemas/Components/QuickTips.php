<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class QuickTips extends Component
{
    protected string $view = 'filament.schemas.components.quick-tips';

    protected string|Closure|null $icon = 'heroicon-o-light-bulb';

    protected string|Closure|null $title = 'Quick Tips';

    protected string|Closure|null $description = null;

    protected array|Closure $items = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function icon(string|Closure $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->evaluate($this->icon);
    }

    public function title(string|Closure $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->evaluate($this->title);
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
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
}
