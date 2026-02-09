<?php

namespace App\Livewire;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\FlowDiagram;
use App\Filament\Schemas\Components\HeroSection;
use App\Filament\Schemas\Components\StatCard;
use App\Filament\Schemas\Components\StatCards;
use App\Filament\Schemas\Components\TextContent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class HowItWorksContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                HeroSection::make()
                    ->badgeIcon('heroicon-s-arrows-right-left')
                    ->badgeLabel(__('docs.how.badge'))
                    ->title(__('docs.how.title'))
                    ->description(__('docs.how.description'))
                    ->heroIcon('heroicon-o-server-stack')
                    ->heroIconGradient('blue', 'indigo'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-document-text')
                            ->color('blue')
                            ->title(__('docs.how.stat.metadata'))
                            ->description(__('docs.how.stat.metadata_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-arrows-right-left')
                            ->color('purple')
                            ->title(__('docs.how.stat.proxied'))
                            ->description(__('docs.how.stat.proxied_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-shield-check')
                            ->color('emerald')
                            ->title(__('docs.how.stat.single'))
                            ->description(__('docs.how.stat.single_desc')),
                    ])
                    ->gridColumns(3),

                Section::make(__('docs.how.section.players'))
                    ->icon('heroicon-o-user-group')
                    ->iconColor('primary')
                    ->description(__('docs.how.section.players_desc'))
                    ->schema([
                        FlowDiagram::make()
                            ->actors([
                                [
                                    'name' => __('docs.how.actor.project'),
                                    'description' => __('docs.how.actor.project_desc'),
                                    'icon' => 'heroicon-o-code-bracket',
                                    'color' => 'blue',
                                ],
                                [
                                    'name' => __('docs.how.actor.packgrid'),
                                    'description' => __('docs.how.actor.packgrid_desc'),
                                    'icon' => 'heroicon-o-server-stack',
                                    'color' => 'amber',
                                ],
                                [
                                    'name' => __('docs.how.actor.github'),
                                    'description' => __('docs.how.actor.github_desc'),
                                    'icon' => 'heroicon-o-cloud',
                                    'color' => 'gray',
                                ],
                            ])
                            ->steps([]),
                    ]),

                Section::make(__('docs.how.section.phase1'))
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('primary')
                    ->description(__('docs.how.section.phase1_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.how.phase1.intro')),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => __('docs.how.actor.packgrid'),
                                    'to' => __('docs.how.actor.github'),
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => __('docs.how.phase1.step1_title'),
                                    'description' => __('docs.how.phase1.step1_desc'),
                                    'data' => __('docs.how.phase1.step1_data'),
                                    'dataLabel' => __('docs.how.phase1.step1_label'),
                                    'icon' => 'heroicon-o-tag',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => __('docs.how.actor.packgrid'),
                                    'to' => __('docs.how.actor.github'),
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => __('docs.how.phase1.step2_title'),
                                    'description' => __('docs.how.phase1.step2_desc'),
                                    'data' => __('docs.how.phase1.step2_data'),
                                    'dataLabel' => __('docs.how.phase1.step2_label'),
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-blue-100 dark:bg-blue-900',
                                    'iconColor' => 'text-blue-600 dark:text-blue-400',
                                ],
                                [
                                    'from' => __('docs.how.actor.packgrid'),
                                    'to' => __('docs.how.actor.storage'),
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                    'title' => __('docs.how.phase1.step3_title'),
                                    'description' => __('docs.how.phase1.step3_desc'),
                                    'data' => '{"packages": {"vendor/name": {"1.0.0": {...}, "2.0.0": {...}}}}',
                                    'dataLabel' => __('docs.how.phase1.step3_label'),
                                    'icon' => 'heroicon-o-circle-stack',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),

                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-light-bulb')
                            ->title(__('docs.how.alert.no_files'))
                            ->description(__('docs.how.alert.no_files_desc')),
                    ]),

                Section::make(__('docs.how.section.phase2'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description(__('docs.how.section.phase2_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.how.phase2.intro')),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => __('docs.how.actor.project'),
                                    'to' => __('docs.how.actor.packgrid'),
                                    'fromColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => __('docs.how.phase2.step1_title'),
                                    'description' => __('docs.how.phase2.step1_desc'),
                                    'data' => __('docs.how.phase2.step1_data'),
                                    'dataLabel' => __('docs.how.phase2.step1_label'),
                                    'icon' => 'heroicon-o-key',
                                    'iconBg' => 'bg-amber-100 dark:bg-amber-900',
                                    'iconColor' => 'text-amber-600 dark:text-amber-400',
                                ],
                                [
                                    'from' => __('docs.how.actor.packgrid'),
                                    'to' => __('docs.how.actor.project'),
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'title' => __('docs.how.phase2.step2_title'),
                                    'description' => __('docs.how.phase2.step2_desc'),
                                    'data' => '{"packages": {"vendor/package": {"1.0.0": {"dist": {"url": "https://packgrid/dist/..."}}}}}',
                                    'dataLabel' => __('docs.how.phase2.step2_label'),
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-blue-100 dark:bg-blue-900',
                                    'iconColor' => 'text-blue-600 dark:text-blue-400',
                                ],
                                [
                                    'from' => __('docs.how.actor.project'),
                                    'to' => __('docs.how.actor.packgrid'),
                                    'fromColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => __('docs.how.phase2.step3_title'),
                                    'description' => __('docs.how.phase2.step3_desc'),
                                    'data' => __('docs.how.phase2.step3_data'),
                                    'dataLabel' => __('docs.how.phase2.step3_label'),
                                    'icon' => 'heroicon-o-archive-box-arrow-down',
                                    'iconBg' => 'bg-purple-100 dark:bg-purple-900',
                                    'iconColor' => 'text-purple-600 dark:text-purple-400',
                                ],
                                [
                                    'from' => __('docs.how.actor.packgrid'),
                                    'to' => __('docs.how.actor.github'),
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => __('docs.how.phase2.step4_title'),
                                    'description' => __('docs.how.phase2.step4_desc'),
                                    'data' => __('docs.how.phase2.step4_data'),
                                    'dataLabel' => __('docs.how.phase2.step4_label'),
                                    'icon' => 'heroicon-o-cloud-arrow-down',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => __('docs.how.actor.github'),
                                    'to' => __('docs.how.actor.project'),
                                    'fromColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'toColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'title' => __('docs.how.phase2.step5_title'),
                                    'description' => __('docs.how.phase2.step5_desc'),
                                    'data' => __('docs.how.phase2.step5_data'),
                                    'dataLabel' => __('docs.how.phase2.step5_label'),
                                    'icon' => 'heroicon-o-arrow-down-circle',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),
                    ]),

                Section::make(__('docs.how.section.auth_flow'))
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->description(__('docs.how.section.auth_flow_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.how.auth.intro')),

                        BulletList::make([
                            __('docs.how.auth.point1'),
                            __('docs.how.auth.point2'),
                            __('docs.how.auth.point3'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),

                        AlertBox::make()
                            ->success()
                            ->icon('heroicon-o-shield-check')
                            ->title(__('docs.how.alert.security'))
                            ->description(__('docs.how.alert.security_desc')),
                    ]),

                Section::make(__('docs.how.section.data_transfer'))
                    ->icon('heroicon-o-funnel')
                    ->iconColor('primary')
                    ->description(__('docs.how.section.data_transfer_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.how.data.intro')),

                        BulletList::make([
                            __('docs.how.data.point1'),
                            __('docs.how.data.point2'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),

                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-arrow-path')
                            ->title(__('docs.how.alert.streaming'))
                            ->description(__('docs.how.alert.streaming_desc')),
                    ]),

                Section::make(__('docs.how.section.summary'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('primary')
                    ->description(__('docs.how.section.summary_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            __('docs.how.summary.point1'),
                            __('docs.how.summary.point2'),
                            __('docs.how.summary.point3'),
                            __('docs.how.summary.point4'),
                            __('docs.how.summary.point5'),
                        ])->bulletIcon('heroicon-s-check')->bulletColor('primary'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.how-it-works-content');
    }
}
