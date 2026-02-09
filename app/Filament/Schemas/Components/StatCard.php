<?php

namespace App\Filament\Schemas\Components;

use Closure;

class StatCard
{
    protected string|Closure|null $icon = null;

    protected string|Closure $color = 'gray';

    protected string|Closure|null $title = null;

    protected string|Closure|null $description = null;

    public static function make(): static
    {
        return new static;
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

    public function color(string|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string
    {
        return $this->evaluate($this->color);
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

    public function toArray(): array
    {
        return [
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
        ];
    }

    protected function evaluate(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return $value();
        }

        return $value;
    }
}
