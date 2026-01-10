<?php

namespace App\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Component;

class SearchComponent extends Component
{
    protected string $view = 'filament.schemas.components.search-component';

    protected array|Closure $haystack = [];

    protected string|Closure|null $heading = 'The solution for your error is probably here.';

    protected string|Closure|null $placeholder = 'Search...';

    protected string|Closure|null $noResultsText = 'No results found';

    protected string|Closure|null $noResultsDescription = 'Try adjusting your search terms.';

    protected string|Closure|null $githubUrl = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function haystack(array|Closure $haystack): static
    {
        $this->haystack = $haystack;

        // Also set the haystack as the child schema
        $this->schema($haystack);

        return $this;
    }

    public function getHaystack(): array
    {
        return $this->evaluate($this->haystack);
    }

    public function heading(string|Closure $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->evaluate($this->heading);
    }

    public function placeholder(string|Closure $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->evaluate($this->placeholder);
    }

    public function noResultsText(string|Closure $text): static
    {
        $this->noResultsText = $text;

        return $this;
    }

    public function getNoResultsText(): ?string
    {
        return $this->evaluate($this->noResultsText);
    }

    public function noResultsDescription(string|Closure $description): static
    {
        $this->noResultsDescription = $description;

        return $this;
    }

    public function getNoResultsDescription(): ?string
    {
        return $this->evaluate($this->noResultsDescription);
    }

    public function githubUrl(string|Closure|null $url): static
    {
        $this->githubUrl = $url;

        return $this;
    }

    public function getGithubUrl(): ?string
    {
        return $this->evaluate($this->githubUrl);
    }

    public function getHaystackData(): array
    {
        $data = [];

        foreach ($this->getHaystack() as $item) {
            if ($item instanceof ErrorSection) {
                $data[] = [
                    'id' => $item->getSearchId() ?? $item->getKey() ?? uniqid(),
                    'title' => $item->getErrorTitle() ?? $item->getHeading(),
                    'description' => $item->getErrorDescription() ?? $item->getDescription(),
                    'error' => $item->getErrorMessage(),
                ];
            }
        }

        return $data;
    }
}
