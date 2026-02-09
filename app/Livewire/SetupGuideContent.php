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
            ->title(__('docs.setup.copied_notification', ['label' => $label]))
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
                    ->badgeLabel(__('docs.setup.badge'))
                    ->title(__('docs.setup.title'))
                    ->description(__('docs.setup.description'))
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
                    ->title(__('docs.setup.tips_title'))
                    ->items([
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.setup.tip1')],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.setup.tip2')],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.setup.tip3')],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.setup.tip4')],
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.setup-guide-content');
    }
}
