<?php

namespace App\Livewire\Docs\Docker;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\CodeBlock;
use App\Filament\Schemas\Components\TextContent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class TroubleshootingContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('docs.docker.trouble.section.auth'))
                    ->icon('heroicon-o-key')
                    ->iconColor('danger')
                    ->description(__('docs.docker.trouble.section.auth_desc'))
                    ->schema([
                        TextContent::make(__('docs.docker.trouble.auth.intro')),
                        BulletList::make([
                            __('docs.docker.trouble.auth.check1'),
                            __('docs.docker.trouble.auth.check2'),
                            __('docs.docker.trouble.auth.check3'),
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('amber'),
                        CodeBlock::make("# Re-login to refresh credentials\ndocker logout ".str_replace(['http://', 'https://'], '', config('app.url'))."\ndocker login ".str_replace(['http://', 'https://'], '', config('app.url')).' -u token -p YOUR_TOKEN')
                            ->language('bash')
                            ->copyable(),
                    ]),

                Section::make(__('docs.docker.trouble.section.push_errors'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->iconColor('danger')
                    ->description(__('docs.docker.trouble.section.push_errors_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.trouble.push.intro')),
                        BulletList::make([
                            __('docs.docker.trouble.push.issue1'),
                            __('docs.docker.trouble.push.issue2'),
                            __('docs.docker.trouble.push.issue3'),
                        ])->bulletIcon('heroicon-s-exclamation-triangle')->bulletColor('amber'),
                    ]),

                Section::make(__('docs.docker.trouble.section.pull_errors'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('danger')
                    ->description(__('docs.docker.trouble.section.pull_errors_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.trouble.pull.intro')),
                        BulletList::make([
                            __('docs.docker.trouble.pull.issue1'),
                            __('docs.docker.trouble.pull.issue2'),
                        ])->bulletIcon('heroicon-s-exclamation-triangle')->bulletColor('amber'),
                    ]),

                Section::make(__('docs.docker.trouble.section.ssl'))
                    ->icon('heroicon-o-lock-closed')
                    ->iconColor('danger')
                    ->description(__('docs.docker.trouble.section.ssl_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.trouble.ssl.intro')),
                        AlertBox::make()
                            ->warning()
                            ->icon('heroicon-o-exclamation-triangle')
                            ->description(__('docs.docker.trouble.ssl.warning')),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.docker.troubleshooting-content');
    }
}
