<?php

namespace App\Livewire\Docs\Docker;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\HeroSection;
use App\Filament\Schemas\Components\StatCard;
use App\Filament\Schemas\Components\StatCards;
use App\Filament\Schemas\Components\TextContent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class IntroductionContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                HeroSection::make()
                    ->badgeIcon('heroicon-s-server')
                    ->badgeLabel(__('docs.docker.intro.badge'))
                    ->title(__('docs.docker.intro.title'))
                    ->description(__('docs.docker.intro.description'))
                    ->heroIcon('heroicon-o-cube-transparent')
                    ->heroIconGradient('blue', 'cyan'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-server')
                            ->color('blue')
                            ->title(__('docs.docker.intro.stat.oci_compliant'))
                            ->description(__('docs.docker.intro.stat.oci_compliant_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-shield-check')
                            ->color('emerald')
                            ->title(__('docs.docker.intro.stat.token_auth'))
                            ->description(__('docs.docker.intro.stat.token_auth_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-circle-stack')
                            ->color('amber')
                            ->title(__('docs.docker.intro.stat.blob_dedup'))
                            ->description(__('docs.docker.intro.stat.blob_dedup_desc')),
                    ])
                    ->gridColumns(3),

                AlertBox::make()
                    ->info()
                    ->icon('heroicon-o-information-circle')
                    ->title(__('docs.docker.intro.info.title'))
                    ->description(__('docs.docker.intro.info.description'))
                    ->items([
                        __('docs.docker.intro.info.item1'),
                        __('docs.docker.intro.info.item2'),
                        __('docs.docker.intro.info.item3'),
                    ]),

                Section::make(__('docs.docker.intro.section.what_is'))
                    ->icon('heroicon-o-question-mark-circle')
                    ->iconColor('primary')
                    ->description(__('docs.docker.intro.section.what_is_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.intro.what_is.p1')),
                        TextContent::make(__('docs.docker.intro.what_is.p2')),
                    ]),

                Section::make(__('docs.docker.intro.section.features'))
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('primary')
                    ->description(__('docs.docker.intro.section.features_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            __('docs.docker.intro.features.item1'),
                            __('docs.docker.intro.features.item2'),
                            __('docs.docker.intro.features.item3'),
                            __('docs.docker.intro.features.item4'),
                            __('docs.docker.intro.features.item5'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),
                    ]),

                Section::make(__('docs.docker.intro.section.getting_started'))
                    ->icon('heroicon-o-play')
                    ->iconColor('primary')
                    ->description(__('docs.docker.intro.section.getting_started_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.intro.getting_started.intro')),
                        BulletList::make([
                            __('docs.docker.intro.getting_started.item1'),
                            __('docs.docker.intro.getting_started.item2'),
                            __('docs.docker.intro.getting_started.item3'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.docker.introduction-content');
    }
}
