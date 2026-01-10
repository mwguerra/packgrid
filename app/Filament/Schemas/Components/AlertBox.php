<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class AlertBox extends Component
{
    protected string $view = 'filament.schemas.components.alert-box';

    protected string|Closure|null $icon = null;

    protected string|Closure|null $title = null;

    protected string|Closure|null $description = null;

    protected string|Closure $type = 'info';

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

    public function description(string|Closure $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
    }

    public function type(string|Closure $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->evaluate($this->type);
    }

    public function warning(): static
    {
        return $this->type('warning');
    }

    public function danger(): static
    {
        return $this->type('danger');
    }

    public function info(): static
    {
        return $this->type('info');
    }

    public function success(): static
    {
        return $this->type('success');
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
