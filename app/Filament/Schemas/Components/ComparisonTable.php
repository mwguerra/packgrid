<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class ComparisonTable extends Component
{
    protected string $view = 'filament.schemas.components.comparison-table';

    protected array|Closure $features = [];

    protected array|Closure $products = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function features(array|Closure $features): static
    {
        $this->features = $features;

        return $this;
    }

    public function getFeatures(): array
    {
        return $this->evaluate($this->features);
    }

    public function products(array|Closure $products): static
    {
        $this->products = $products;

        return $this;
    }

    public function getProducts(): array
    {
        return $this->evaluate($this->products);
    }
}
