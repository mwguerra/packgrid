<?php

namespace App\Livewire\Docs\Npm;

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
                    ->badgeLabel(__('docs.npm.how.badge'))
                    ->title(__('docs.npm.how.title'))
                    ->description(__('docs.npm.how.description'))
                    ->heroIcon('heroicon-o-server-stack')
                    ->heroIconGradient('red', 'orange'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-document-text')
                            ->color('blue')
                            ->title(__('docs.npm.how.stat.metadata'))
                            ->description(__('docs.npm.how.stat.metadata_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-archive-box')
                            ->color('purple')
                            ->title(__('docs.npm.how.stat.tarball'))
                            ->description(__('docs.npm.how.stat.tarball_desc')),
                        StatCard::make()
                            ->icon('heroicon-s-shield-check')
                            ->color('emerald')
                            ->title(__('docs.npm.how.stat.bearer'))
                            ->description(__('docs.npm.how.stat.bearer_desc')),
                    ])
                    ->gridColumns(3),

                Section::make(__('docs.npm.how.section.players'))
                    ->icon('heroicon-o-user-group')
                    ->iconColor('primary')
                    ->description(__('docs.npm.how.section.players_desc'))
                    ->schema([
                        FlowDiagram::make()
                            ->actors([
                                [
                                    'name' => __('docs.npm.how.actor.project'),
                                    'description' => __('docs.npm.how.actor.project_desc'),
                                    'icon' => 'heroicon-o-code-bracket',
                                    'color' => 'red',
                                ],
                                [
                                    'name' => __('docs.npm.how.actor.packgrid'),
                                    'description' => __('docs.npm.how.actor.packgrid_desc'),
                                    'icon' => 'heroicon-o-server-stack',
                                    'color' => 'amber',
                                ],
                                [
                                    'name' => __('docs.npm.how.actor.github'),
                                    'description' => __('docs.npm.how.actor.github_desc'),
                                    'icon' => 'heroicon-o-cloud',
                                    'color' => 'gray',
                                ],
                            ])
                            ->steps([]),
                    ]),

                Section::make(__('docs.npm.how.section.phase1'))
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('primary')
                    ->description(__('docs.npm.how.section.phase1_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.how.phase1.intro')),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => __('docs.npm.how.phase1.step1_title'),
                                    'description' => __('docs.npm.how.phase1.step1_desc'),
                                    'data' => __('docs.npm.how.phase1.step1_data'),
                                    'dataLabel' => __('docs.npm.how.phase1.step1_label'),
                                    'icon' => 'heroicon-o-tag',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => __('docs.npm.how.phase1.step2_title'),
                                    'description' => __('docs.npm.how.phase1.step2_desc'),
                                    'data' => __('docs.npm.how.phase1.step2_data'),
                                    'dataLabel' => __('docs.npm.how.phase1.step2_label'),
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-red-100 dark:bg-red-900',
                                    'iconColor' => 'text-red-600 dark:text-red-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => __('docs.npm.how.actor.storage'),
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                    'title' => __('docs.npm.how.phase1.step3_title'),
                                    'description' => __('docs.npm.how.phase1.step3_desc'),
                                    'data' => '{"name": "@scope/pkg", "versions": {"1.0.0": {"dist": {"tarball": "..."}}}}',
                                    'dataLabel' => __('docs.npm.how.phase1.step3_label'),
                                    'icon' => 'heroicon-o-circle-stack',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),

                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-light-bulb')
                            ->title(__('docs.npm.how.alert.scoped'))
                            ->description(__('docs.npm.how.alert.scoped_desc')),
                    ]),

                Section::make(__('docs.npm.how.section.phase2'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description(__('docs.npm.how.section.phase2_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.how.phase2.intro')),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => 'Project',
                                    'to' => 'Packgrid',
                                    'fromColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => __('docs.npm.how.phase2.step1_title'),
                                    'description' => __('docs.npm.how.phase2.step1_desc'),
                                    'data' => __('docs.npm.how.phase2.step1_data'),
                                    'dataLabel' => __('docs.npm.how.phase2.step1_label'),
                                    'icon' => 'heroicon-o-key',
                                    'iconBg' => 'bg-amber-100 dark:bg-amber-900',
                                    'iconColor' => 'text-amber-600 dark:text-amber-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'Project',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'title' => __('docs.npm.how.phase2.step2_title'),
                                    'description' => __('docs.npm.how.phase2.step2_desc'),
                                    'data' => '{"name": "@myorg/package", "versions": {"1.0.0": {"dist": {"tarball": "https://packgrid/..."}}}}',
                                    'dataLabel' => __('docs.npm.how.phase2.step2_label'),
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-red-100 dark:bg-red-900',
                                    'iconColor' => 'text-red-600 dark:text-red-400',
                                ],
                                [
                                    'from' => 'Project',
                                    'to' => 'Packgrid',
                                    'fromColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => __('docs.npm.how.phase2.step3_title'),
                                    'description' => __('docs.npm.how.phase2.step3_desc'),
                                    'data' => __('docs.npm.how.phase2.step3_data'),
                                    'dataLabel' => __('docs.npm.how.phase2.step3_label'),
                                    'icon' => 'heroicon-o-archive-box-arrow-down',
                                    'iconBg' => 'bg-purple-100 dark:bg-purple-900',
                                    'iconColor' => 'text-purple-600 dark:text-purple-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => __('docs.npm.how.phase2.step4_title'),
                                    'description' => __('docs.npm.how.phase2.step4_desc'),
                                    'data' => __('docs.npm.how.phase2.step4_data'),
                                    'dataLabel' => __('docs.npm.how.phase2.step4_label'),
                                    'icon' => 'heroicon-o-cloud-arrow-down',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => 'GitHub',
                                    'to' => 'Project',
                                    'fromColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'toColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'title' => __('docs.npm.how.phase2.step5_title'),
                                    'description' => __('docs.npm.how.phase2.step5_desc'),
                                    'data' => __('docs.npm.how.phase2.step5_data'),
                                    'dataLabel' => __('docs.npm.how.phase2.step5_label'),
                                    'icon' => 'heroicon-o-arrow-down-circle',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),
                    ]),

                Section::make(__('docs.npm.how.section.auth_flow'))
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->description(__('docs.npm.how.section.auth_flow_desc'))
                    ->collapsible()
                    ->schema([
                        TextContent::make(__('docs.npm.how.auth.intro')),

                        BulletList::make([
                            __('docs.npm.how.auth.point1'),
                            __('docs.npm.how.auth.point2'),
                            __('docs.npm.how.auth.point3'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),

                        AlertBox::make()
                            ->success()
                            ->icon('heroicon-o-shield-check')
                            ->title(__('docs.npm.how.alert.security'))
                            ->description(__('docs.npm.how.alert.security_desc')),
                    ]),

                Section::make(__('docs.npm.how.section.summary'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('primary')
                    ->description(__('docs.npm.how.section.summary_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            __('docs.npm.how.summary.point1'),
                            __('docs.npm.how.summary.point2'),
                            __('docs.npm.how.summary.point3'),
                            __('docs.npm.how.summary.point4'),
                            __('docs.npm.how.summary.point5'),
                        ])->bulletIcon('heroicon-s-check')->bulletColor('primary'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.how-it-works-content');
    }
}
