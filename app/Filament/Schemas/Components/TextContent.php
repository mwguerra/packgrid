<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class TextContent extends Component
{
    protected string $view = 'filament.schemas.components.text-content';

    protected string|Closure|null $content = null;

    protected bool|Closure $isMarkdown = false;

    public static function make(string|Closure|null $content = null): static
    {
        $static = app(static::class);

        if ($content !== null) {
            $static->content($content);
        }

        return $static;
    }

    public function content(string|Closure $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->evaluate($this->content);
    }

    public function markdown(bool|Closure $condition = true): static
    {
        $this->isMarkdown = $condition;

        return $this;
    }

    public function isMarkdown(): bool
    {
        return $this->evaluate($this->isMarkdown);
    }
}
