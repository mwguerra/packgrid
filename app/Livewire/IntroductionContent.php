<?php

namespace App\Livewire;

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
                    ->badgeLabel(__('docs.intro.badge'))
                    ->title(__('docs.intro.title'))
                    ->description(__('docs.intro.description'))
                    ->heroIcon('heroicon-o-cube')
                    ->heroIconGradient('amber', 'orange'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-key')
                            ->color('amber')
                            ->title(__('docs.intro.stat.one_credential'))
                            ->description(__('docs.intro.stat.one_credential_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-users')
                            ->color('blue')
                            ->title(__('docs.intro.stat.team_tokens'))
                            ->description(__('docs.intro.stat.team_tokens_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-cube')
                            ->color('emerald')
                            ->title(__('docs.intro.stat.standard_composer'))
                            ->description(__('docs.intro.stat.standard_composer_desc')),
                    ])
                    ->gridColumns(3),

                AlertBox::make()
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->title(__('docs.intro.warning.title'))
                    ->description(__('docs.intro.warning.description'))
                    ->items([
                        __('docs.intro.warning.item1'),
                        __('docs.intro.warning.item2'),
                        __('docs.intro.warning.item3'),
                        __('docs.intro.warning.item4'),
                        __('docs.intro.warning.item5'),
                    ]),

                Section::make(__('docs.intro.section.what_is'))
                    ->icon('heroicon-o-question-mark-circle')
                    ->iconColor('primary')
                    ->description(__('docs.intro.section.what_is_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.intro.what_is.p1')),
                        TextContent::make(__('docs.intro.what_is.p2')),
                    ]),

                Section::make(__('docs.intro.section.problem'))
                    ->icon('heroicon-o-light-bulb')
                    ->iconColor('primary')
                    ->description(__('docs.intro.section.problem_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.intro.problem.intro')),
                        BulletList::make([
                            __('docs.intro.problem.option1'),
                            __('docs.intro.problem.option2'),
                        ])->bulletIcon('heroicon-s-x-mark')->bulletColor('red'),
                        TextContent::make(__('docs.intro.problem.packgrid_intro')),
                        BulletList::make([
                            __('docs.intro.problem.packgrid1'),
                            __('docs.intro.problem.packgrid2'),
                            __('docs.intro.problem.packgrid3'),
                        ])->bulletIcon('heroicon-s-check')->bulletColor('emerald'),
                    ]),

                Section::make(__('docs.intro.section.compare'))
                    ->icon('heroicon-o-scale')
                    ->iconColor('primary')
                    ->description(__('docs.intro.section.compare_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.intro.compare.intro')),
                        ComparisonTable::make()
                            ->products([
                                'packgrid' => ['name' => __('docs.compare.product.packgrid'), 'highlight' => true],
                                'packagist' => ['name' => __('docs.compare.product.packagist')],
                                'satis' => ['name' => __('docs.compare.product.satis')],
                                'repman' => ['name' => __('docs.compare.product.repman')],
                            ])
                            ->features([
                                [
                                    'name' => __('docs.compare.feature.multi_protocol'),
                                    'description' => __('docs.compare.feature.multi_protocol_desc'),
                                    'values' => [
                                        'packgrid' => true,
                                        'packagist' => false,
                                        'satis' => false,
                                        'repman' => false,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.hosting'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.self_hosted'),
                                        'packagist' => __('docs.compare.value.cloud_or_self'),
                                        'satis' => __('docs.compare.value.self_hosted'),
                                        'repman' => __('docs.compare.value.self_hosted'),
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.cost'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.free'),
                                        'packagist' => __('docs.compare.value.packagist_price'),
                                        'satis' => __('docs.compare.value.free'),
                                        'repman' => __('docs.compare.value.free'),
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.admin_panel'),
                                    'values' => [
                                        'packgrid' => true,
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.github'),
                                    'values' => [
                                        'packgrid' => true,
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.gitlab'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.planned'),
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.bitbucket'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.planned'),
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.webhooks'),
                                    'description' => __('docs.compare.feature.webhooks_desc'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.planned'),
                                        'packagist' => true,
                                        'satis' => __('docs.compare.value.partial'),
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.security'),
                                    'description' => __('docs.compare.feature.security_desc'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.planned'),
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.mirroring'),
                                    'description' => __('docs.compare.feature.mirroring_desc'),
                                    'values' => [
                                        'packgrid' => false,
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => false,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.permissions'),
                                    'description' => __('docs.compare.feature.permissions_desc'),
                                    'values' => [
                                        'packgrid' => false,
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.license'),
                                    'values' => [
                                        'packgrid' => false,
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => false,
                                    ],
                                ],
                                [
                                    'name' => __('docs.compare.feature.setup'),
                                    'values' => [
                                        'packgrid' => __('docs.compare.value.simple'),
                                        'packagist' => __('docs.compare.value.managed'),
                                        'satis' => __('docs.compare.value.manual'),
                                        'repman' => __('docs.compare.value.moderate'),
                                    ],
                                ],
                            ]),
                        TextContent::make(__('docs.intro.compare.summary')),
                    ]),

                Section::make(__('docs.intro.section.features'))
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('primary')
                    ->description(__('docs.intro.section.features_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            __('docs.intro.features.item1'),
                            __('docs.intro.features.item2'),
                            __('docs.intro.features.item3'),
                            __('docs.intro.features.item4'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),
                    ]),

                Section::make(__('docs.intro.section.future'))
                    ->icon('heroicon-o-rocket-launch')
                    ->iconColor('primary')
                    ->description(__('docs.intro.section.future_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.intro.future.intro')),
                        BulletList::make([
                            __('docs.intro.future.item1'),
                            __('docs.intro.future.item2'),
                            __('docs.intro.future.item3'),
                            __('docs.intro.future.item4'),
                            __('docs.intro.future.item5'),
                            __('docs.intro.future.item6'),
                        ])->bulletIcon('heroicon-o-clock')->bulletColor('blue'),
                        TextContent::make(__('docs.intro.future.outro')),
                    ]),

                Section::make(__('docs.intro.section.getting_started'))
                    ->icon('heroicon-o-play')
                    ->iconColor('primary')
                    ->description(__('docs.intro.section.getting_started_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.intro.getting_started.intro')),
                        BulletList::make([
                            __('docs.intro.getting_started.item1'),
                            __('docs.intro.getting_started.item2'),
                            __('docs.intro.getting_started.item3'),
                            __('docs.intro.getting_started.item4'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.introduction-content');
    }
}
