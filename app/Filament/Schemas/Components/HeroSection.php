<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class HeroSection extends Component
{
    protected string $view = 'filament.schemas.components.hero-section';

    protected string|Closure|null $badgeIcon = null;

    protected string|Closure|null $badgeLabel = null;

    protected string|Closure|null $title = null;

    protected string|Closure|null $description = null;

    protected string|Closure|null $heroIcon = null;

    protected string|Closure $heroIconFromColor = 'amber';

    protected string|Closure $heroIconToColor = 'orange';

    public static function make(): static
    {
        return app(static::class);
    }

    public function badgeIcon(string|Closure $icon): static
    {
        $this->badgeIcon = $icon;

        return $this;
    }

    public function getBadgeIcon(): ?string
    {
        return $this->evaluate($this->badgeIcon);
    }

    public function badgeLabel(string|Closure $label): static
    {
        $this->badgeLabel = $label;

        return $this;
    }

    public function getBadgeLabel(): ?string
    {
        return $this->evaluate($this->badgeLabel);
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

    public function heroIcon(string|Closure $icon): static
    {
        $this->heroIcon = $icon;

        return $this;
    }

    public function getHeroIcon(): ?string
    {
        return $this->evaluate($this->heroIcon);
    }

    public function heroIconGradient(string|Closure $fromColor, string|Closure $toColor): static
    {
        $this->heroIconFromColor = $fromColor;
        $this->heroIconToColor = $toColor;

        return $this;
    }

    public function getHeroIconFromColor(): string
    {
        return $this->evaluate($this->heroIconFromColor);
    }

    public function getHeroIconToColor(): string
    {
        return $this->evaluate($this->heroIconToColor);
    }
}
