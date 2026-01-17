<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class CodeBlock extends Component
{
    protected string $view = 'filament.schemas.components.code-block';

    protected string|Closure|null $code = null;

    protected string|Closure|null $language = null;

    protected string|Closure|null $copyLabel = 'Code';

    protected bool|Closure $hasCopyButton = true;

    public static function make(string|Closure|null $code = null): static
    {
        $static = app(static::class);

        if ($code !== null) {
            $static->code($code);
        }

        return $static;
    }

    public function code(string|Closure $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->evaluate($this->code);
    }

    public function language(string|Closure|null $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->evaluate($this->language);
    }

    public function copyLabel(string|Closure $label): static
    {
        $this->copyLabel = $label;

        return $this;
    }

    public function getCopyLabel(): ?string
    {
        return $this->evaluate($this->copyLabel);
    }

    public function copyButton(bool|Closure $condition = true): static
    {
        $this->hasCopyButton = $condition;

        return $this;
    }

    public function copyable(bool|Closure $condition = true): static
    {
        return $this->copyButton($condition);
    }

    public function hasCopyButton(): bool
    {
        return $this->evaluate($this->hasCopyButton);
    }
}
