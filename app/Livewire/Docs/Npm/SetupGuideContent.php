<?php

namespace App\Livewire\Docs\Npm;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\CodeBlock;
use App\Filament\Schemas\Components\HeroSection;
use App\Filament\Schemas\Components\QuickTips;
use App\Filament\Schemas\Components\TextContent;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
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
            ->title(__('docs.npm.setup.copied_notification', ['label' => $label]))
            ->success()
            ->send();
    }

    public function getServerUrlProperty(): string
    {
        return rtrim(config('app.url') ?: url('/'), '/');
    }

    public function getNpmRegistryUrlProperty(): string
    {
        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'your-packgrid-server.com';
        $port = parse_url($this->serverUrl, PHP_URL_PORT);
        $scheme = parse_url($this->serverUrl, PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$host.($port ? ':'.$port : '').'/npm/';
    }

    public function getNpmrcSnippetProperty(): string
    {
        $registryUrl = $this->npmRegistryUrl;
        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'your-packgrid-server.com';
        $port = parse_url($this->serverUrl, PHP_URL_PORT);

        $hostWithPort = $host.($port ? ':'.$port : '');

        return "@myorg:registry={$registryUrl}\n//{$hostWithPort}/npm/:_authToken=YOUR_PACKGRID_TOKEN";
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                HeroSection::make()
                    ->badgeIcon('heroicon-s-rocket-launch')
                    ->badgeLabel(__('docs.npm.setup.badge'))
                    ->title(__('docs.npm.setup.title'))
                    ->description(__('docs.npm.setup.description'))
                    ->heroIcon('heroicon-o-cog-6-tooth')
                    ->heroIconGradient('red', 'orange'),

                Section::make(__('docs.npm.setup.step1.title'))
                    ->icon('heroicon-o-key')
                    ->iconColor('primary')
                    ->description(__('docs.npm.setup.step1.description'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.setup.step1.intro')),
                        BulletList::make([
                            __('docs.npm.setup.step1.item1'),
                            __('docs.npm.setup.step1.item2'),
                            __('docs.npm.setup.step1.item3'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),

                Section::make(__('docs.npm.setup.step2.title'))
                    ->icon('heroicon-o-cube')
                    ->iconColor('primary')
                    ->description(__('docs.npm.setup.step2.description'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.setup.step2.intro')),
                        BulletList::make([
                            __('docs.npm.setup.step2.item1'),
                            __('docs.npm.setup.step2.item2'),
                            __('docs.npm.setup.step2.item3'),
                            __('docs.npm.setup.step2.item4'),
                            __('docs.npm.setup.step2.item5'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-information-circle')
                            ->title(__('docs.npm.setup.step2.alert.title'))
                            ->description(__('docs.npm.setup.step2.alert.description')),
                    ]),

                Section::make(__('docs.npm.setup.step3.title'))
                    ->icon('heroicon-o-ticket')
                    ->iconColor('primary')
                    ->description(__('docs.npm.setup.step3.description'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.setup.step3.intro')),
                        BulletList::make([
                            __('docs.npm.setup.step3.item1'),
                            __('docs.npm.setup.step3.item2'),
                            __('docs.npm.setup.step3.item3'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),

                Section::make(__('docs.npm.setup.step4.title'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description(__('docs.npm.setup.step4.description'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.setup.step4.intro')),
                        CodeBlock::make($this->npmrcSnippet)
                            ->copyLabel('.npmrc'),
                        AlertBox::make()
                            ->warning()
                            ->icon('heroicon-o-exclamation-triangle')
                            ->title(__('docs.npm.setup.step4.alert.title'))
                            ->description(__('docs.npm.setup.step4.alert.description')),
                    ]),

                Section::make(__('docs.npm.setup.step5.title'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description(__('docs.npm.setup.step5.description'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.setup.step5.intro')),
                        CodeBlock::make('npm install @myorg/your-package')
                            ->copyLabel('Command'),
                        TextContent::make(__('docs.npm.setup.step5.or_yarn')),
                        CodeBlock::make('yarn add @myorg/your-package')
                            ->copyLabel('Command'),
                    ]),

                QuickTips::make()
                    ->icon('heroicon-o-light-bulb')
                    ->title(__('docs.npm.setup.tips_title'))
                    ->items([
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.npm.setup.tip1')],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.npm.setup.tip2')],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.npm.setup.tip3')],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => __('docs.npm.setup.tip4')],
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.setup-guide-content');
    }
}
