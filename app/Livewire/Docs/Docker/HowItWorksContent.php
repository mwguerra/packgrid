<?php

namespace App\Livewire\Docs\Docker;

use App\Filament\Schemas\Components\BulletList;
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
                Section::make(__('docs.docker.how.section.overview'))
                    ->icon('heroicon-o-rectangle-stack')
                    ->iconColor('primary')
                    ->description(__('docs.docker.how.section.overview_desc'))
                    ->schema([
                        TextContent::make(__('docs.docker.how.overview.p1')),
                        BulletList::make([
                            __('docs.docker.how.overview.item1'),
                            __('docs.docker.how.overview.item2'),
                            __('docs.docker.how.overview.item3'),
                            __('docs.docker.how.overview.item4'),
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('blue'),
                    ]),

                Section::make(__('docs.docker.how.section.push_flow'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->iconColor('primary')
                    ->description(__('docs.docker.how.section.push_flow_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.how.push_flow.intro')),
                        BulletList::make([
                            __('docs.docker.how.push_flow.step1'),
                            __('docs.docker.how.push_flow.step2'),
                            __('docs.docker.how.push_flow.step3'),
                            __('docs.docker.how.push_flow.step4'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),
                    ]),

                Section::make(__('docs.docker.how.section.pull_flow'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description(__('docs.docker.how.section.pull_flow_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.how.pull_flow.intro')),
                        BulletList::make([
                            __('docs.docker.how.pull_flow.step1'),
                            __('docs.docker.how.pull_flow.step2'),
                            __('docs.docker.how.pull_flow.step3'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('blue'),
                    ]),

                Section::make(__('docs.docker.how.section.storage'))
                    ->icon('heroicon-o-circle-stack')
                    ->iconColor('primary')
                    ->description(__('docs.docker.how.section.storage_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.how.storage.p1')),
                        TextContent::make(__('docs.docker.how.storage.p2')),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.docker.how-it-works-content');
    }
}
