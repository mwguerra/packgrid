<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class FlowDiagram extends Component
{
    protected string $view = 'filament.schemas.components.flow-diagram';

    protected array|Closure $steps = [];

    protected array|Closure $actors = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function actors(array|Closure $actors): static
    {
        $this->actors = $actors;

        return $this;
    }

    public function getActors(): array
    {
        return $this->evaluate($this->actors);
    }

    public function steps(array|Closure $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    public function getSteps(): array
    {
        return $this->evaluate($this->steps);
    }
}
