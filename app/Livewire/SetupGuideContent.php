<?php

namespace App\Livewire;

use App\Filament\Schemas\Components\HeroSection;
use App\Filament\Schemas\Components\OrderedSchema;
use App\Filament\Schemas\Components\QuickTips;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class SetupGuideContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function showCopiedNotification(string $label = 'Content'): void
    {
        Notification::make()
            ->title("{$label} copied to clipboard")
            ->success()
            ->send();
    }

    public function getServerUrlProperty(): string
    {
        return rtrim(config('app.url') ?: url('/'), '/');
    }

    public function getComposerSnippetProperty(): string
    {
        return json_encode([
            'repositories' => [
                [
                    'type' => 'composer',
                    'url' => $this->serverUrl,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getAuthSnippetProperty(): string
    {
        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'your-packgrid-server.com';

        return json_encode([
            'http-basic' => [
                $host => [
                    'username' => 'composer',
                    'password' => 'YOUR_PACKGRID_TOKEN',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getHasTokensProperty(): bool
    {
        return \App\Models\Token::query()->exists();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                HeroSection::make()
                    ->badgeIcon('heroicon-s-rocket-launch')
                    ->badgeLabel('Quick Start')
                    ->title('Get Up and Running in 5 Minutes')
                    ->description('Follow these simple steps to configure Packgrid and start installing your private packages with Composer.')
                    ->heroIcon('heroicon-o-cog-6-tooth')
                    ->heroIconGradient('emerald', 'teal'),

                OrderedSchema::make()
                    ->number(1)
                    ->customView('livewire.setup-guide.step-github-token'),

                OrderedSchema::make()
                    ->number(2)
                    ->customView('livewire.setup-guide.step-server-url'),

                OrderedSchema::make()
                    ->number(3)
                    ->customView('livewire.setup-guide.step-composer-json'),

                OrderedSchema::make()
                    ->number(4)
                    ->customView('livewire.setup-guide.step-auth-json'),

                OrderedSchema::make()
                    ->number(5)
                    ->customView('livewire.setup-guide.step-install-package'),

                QuickTips::make()
                    ->icon('heroicon-o-light-bulb')
                    ->title('Quick Tips')
                    ->items([
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Private repos require a GitHub credential in Packgrid'],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Create Packgrid tokens for Composer authentication'],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Run a sync after adding repositories'],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Place auth.json next to composer.json or globally'],
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.setup-guide-content');
    }
}
