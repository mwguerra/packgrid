<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;

class ErrorSection extends Section
{
    protected string $view = 'filament.schemas.components.error-section';

    protected string|Closure|null $errorIcon = 'heroicon-o-exclamation-triangle';

    protected string|Closure|null $errorTitle = null;

    protected string|Closure|null $errorDescription = null;

    protected string|Closure|null $errorMessage = null;

    protected array|Closure $solutionSchema = [];

    protected string|Closure|null $searchId = null;

    public static function make(Htmlable|Closure|array|string|null $heading = null): static
    {
        $static = app(static::class);

        if ($heading !== null) {
            $static->heading($heading);
        }

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->iconColor('danger');
        $this->collapsible();
    }

    public function errorIcon(string|Closure $icon): static
    {
        $this->errorIcon = $icon;
        $this->icon($icon);

        return $this;
    }

    public function getErrorIcon(): ?string
    {
        return $this->evaluate($this->errorIcon);
    }

    public function errorTitle(string|Closure $title): static
    {
        $this->errorTitle = $title;
        $this->heading($title);

        return $this;
    }

    public function getErrorTitle(): ?string
    {
        return $this->evaluate($this->errorTitle);
    }

    public function errorDescription(string|Closure $description): static
    {
        $this->errorDescription = $description;
        $this->description($description);

        return $this;
    }

    public function getErrorDescription(): ?string
    {
        return $this->evaluate($this->errorDescription);
    }

    public function errorMessage(string|Closure $message): static
    {
        $this->errorMessage = $message;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->evaluate($this->errorMessage);
    }

    public function solutionSchema(array|Closure $schema): static
    {
        $this->solutionSchema = $schema;

        // Also set the schema for child rendering
        $this->schema($schema);

        return $this;
    }

    public function getSolutionSchema(): array
    {
        return $this->evaluate($this->solutionSchema);
    }

    public function searchId(string|Closure $id): static
    {
        $this->searchId = $id;

        return $this;
    }

    public function getSearchId(): ?string
    {
        return $this->evaluate($this->searchId);
    }

    public function getSearchableContent(): string
    {
        $content = [];

        if ($this->getErrorTitle()) {
            $content[] = $this->getErrorTitle();
        }

        if ($this->getErrorDescription()) {
            $content[] = $this->getErrorDescription();
        }

        if ($this->getErrorMessage()) {
            $content[] = $this->getErrorMessage();
        }

        return implode(' ', $content);
    }
}
