<?php

namespace App\Livewire\Docs\Docker;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\CodeBlock;
use App\Filament\Schemas\Components\TextContent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class SetupGuideContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function form(Schema $form): Schema
    {
        $registryUrl = str_replace(['http://', 'https://'], '', config('app.url'));

        return $form
            ->schema([
                Section::make(__('docs.docker.setup.section.login'))
                    ->icon('heroicon-o-key')
                    ->iconColor('primary')
                    ->description(__('docs.docker.setup.section.login_desc'))
                    ->schema([
                        TextContent::make(__('docs.docker.setup.login.intro')),
                        CodeBlock::make("docker login {$registryUrl} -u token -p YOUR_PACKGRID_TOKEN")
                            ->language('bash')
                            ->copyable(),
                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-information-circle')
                            ->description(__('docs.docker.setup.login.token_note')),
                    ]),

                Section::make(__('docs.docker.setup.section.push'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->iconColor('primary')
                    ->description(__('docs.docker.setup.section.push_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.setup.push.intro')),
                        CodeBlock::make("# Tag your image\ndocker tag myapp:latest {$registryUrl}/myorg/myapp:v1.0\n\n# Push to registry\ndocker push {$registryUrl}/myorg/myapp:v1.0")
                            ->language('bash')
                            ->copyable(),
                    ]),

                Section::make(__('docs.docker.setup.section.pull'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description(__('docs.docker.setup.section.pull_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.setup.pull.intro')),
                        CodeBlock::make("docker pull {$registryUrl}/myorg/myapp:v1.0")
                            ->language('bash')
                            ->copyable(),
                    ]),

                Section::make(__('docs.docker.setup.section.compose'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description(__('docs.docker.setup.section.compose_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.setup.compose.intro')),
                        CodeBlock::make("services:\n  app:\n    image: {$registryUrl}/myorg/myapp:v1.0\n    ports:\n      - \"8080:80\"")
                            ->language('yaml')
                            ->copyable(),
                    ]),

                Section::make(__('docs.docker.setup.section.server_config'))
                    ->icon('heroicon-o-server-stack')
                    ->iconColor('warning')
                    ->description(__('docs.docker.setup.section.server_config_desc'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make(__('docs.docker.setup.server_config.intro')),
                        TextContent::make(__('docs.docker.setup.server_config.php_label')),
                        CodeBlock::make("post_max_size = 500M\nupload_max_filesize = 500M")
                            ->language('ini')
                            ->copyable(),
                        TextContent::make(__('docs.docker.setup.server_config.nginx_label')),
                        CodeBlock::make("client_max_body_size 0;")
                            ->language('nginx')
                            ->copyable(),
                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-information-circle')
                            ->description(__('docs.docker.setup.server_config.nginx_note')),
                        AlertBox::make()
                            ->warning()
                            ->icon('heroicon-o-exclamation-triangle')
                            ->description(__('docs.docker.setup.server_config.restart_note')),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.docker.setup-guide-content');
    }
}
