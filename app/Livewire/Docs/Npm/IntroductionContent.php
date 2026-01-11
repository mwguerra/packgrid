<?php

namespace App\Livewire\Docs\Npm;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\ComparisonTable;
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
                    ->badgeLabel(__('docs.npm.intro.badge'))
                    ->title(__('docs.npm.intro.title'))
                    ->description(__('docs.npm.intro.description'))
                    ->heroIcon('heroicon-o-cube')
                    ->heroIconGradient('red', 'orange'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-key')
                            ->color('amber')
                            ->title(__('docs.npm.intro.stat.one_credential'))
                            ->description(__('docs.npm.intro.stat.one_credential_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-users')
                            ->color('blue')
                            ->title(__('docs.npm.intro.stat.bearer_tokens'))
                            ->description(__('docs.npm.intro.stat.bearer_tokens_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-cube')
                            ->color('red')
                            ->title(__('docs.npm.intro.stat.standard_npm'))
                            ->description(__('docs.npm.intro.stat.standard_npm_desc')),
                    ])
                    ->gridColumns(3),

                AlertBox::make()
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->title(__('docs.npm.intro.warning.title'))
                    ->description(__('docs.npm.intro.warning.description'))
                    ->items([
                        __('docs.npm.intro.warning.item1'),
                        __('docs.npm.intro.warning.item2'),
                        __('docs.npm.intro.warning.item3'),
                        __('docs.npm.intro.warning.item4'),
                    ]),

                Section::make(__('docs.npm.intro.section.what_is'))
                    ->icon('heroicon-o-question-mark-circle')
                    ->iconColor('primary')
                    ->description(__('docs.npm.intro.section.what_is_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.npm.intro.what_is.p1')),
                        TextContent::make(__('docs.npm.intro.what_is.p2')),
                    ]),

                Section::make(__('docs.npm.intro.section.differences'))
                    ->icon('heroicon-o-scale')
                    ->iconColor('primary')
                    ->description(__('docs.npm.intro.section.differences_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            __('docs.npm.intro.differences.item1'),
                            __('docs.npm.intro.differences.item2'),
                            __('docs.npm.intro.differences.item3'),
                            __('docs.npm.intro.differences.item4'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),

                Section::make(__('docs.npm.intro.section.features'))
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('primary')
                    ->description(__('docs.npm.intro.section.features_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            __('docs.npm.intro.features.item1'),
                            __('docs.npm.intro.features.item2'),
                            __('docs.npm.intro.features.item3'),
                            __('docs.npm.intro.features.item4'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),
                    ]),

                Section::make(__('docs.npm.intro.section.compare'))
                    ->icon('heroicon-o-scale')
                    ->iconColor('primary')
                    ->description(__('docs.npm.intro.section.compare_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.npm.intro.compare.intro')),
                        ComparisonTable::make()
                            ->products([
                                'packgrid' => ['name' => __('docs.npm.compare.product.packgrid'), 'highlight' => true],
                                'verdaccio' => ['name' => __('docs.npm.compare.product.verdaccio')],
                                'github' => ['name' => __('docs.npm.compare.product.github')],
                                'artifactory' => ['name' => __('docs.npm.compare.product.artifactory')],
                            ])
                            ->features([
                                [
                                    'name' => __('docs.npm.compare.feature.multi_protocol'),
                                    'description' => __('docs.npm.compare.feature.multi_protocol_desc'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => false,
                                        'github' => true,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.hosting'),
                                    'values' => [
                                        'packgrid' => __('docs.npm.compare.value.self_hosted'),
                                        'verdaccio' => __('docs.npm.compare.value.self_hosted'),
                                        'github' => __('docs.npm.compare.value.cloud'),
                                        'artifactory' => __('docs.npm.compare.value.both'),
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.cost'),
                                    'values' => [
                                        'packgrid' => __('docs.npm.compare.value.free'),
                                        'verdaccio' => __('docs.npm.compare.value.free'),
                                        'github' => __('docs.npm.compare.value.free_tier_paid'),
                                        'artifactory' => __('docs.npm.compare.value.paid'),
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.open_source'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => true,
                                        'github' => false,
                                        'artifactory' => false,
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.admin_panel'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => true,
                                        'github' => true,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.github_integration'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => false,
                                        'github' => true,
                                        'artifactory' => __('docs.npm.compare.value.partial'),
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.mirroring'),
                                    'description' => __('docs.npm.compare.feature.mirroring_desc'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => true,
                                        'github' => false,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.token_management'),
                                    'description' => __('docs.npm.compare.feature.token_management_desc'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => __('docs.npm.compare.value.partial'),
                                        'github' => true,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.ip_restrictions'),
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => false,
                                        'github' => false,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.npm.compare.feature.setup'),
                                    'values' => [
                                        'packgrid' => __('docs.npm.compare.value.simple'),
                                        'verdaccio' => __('docs.npm.compare.value.simple'),
                                        'github' => __('docs.npm.compare.value.managed'),
                                        'artifactory' => __('docs.npm.compare.value.complex'),
                                    ],
                                ],
                            ]),
                        TextContent::make(__('docs.npm.intro.compare.summary')),
                    ]),

                Section::make(__('docs.npm.intro.section.getting_started'))
                    ->icon('heroicon-o-play')
                    ->iconColor('primary')
                    ->description(__('docs.npm.intro.section.getting_started_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.npm.intro.getting_started.intro')),
                        BulletList::make([
                            __('docs.npm.intro.getting_started.item1'),
                            __('docs.npm.intro.getting_started.item2'),
                            __('docs.npm.intro.getting_started.item3'),
                            __('docs.npm.intro.getting_started.item4'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.introduction-content');
    }
}
