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
            ->title("{$label} copied to clipboard")
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
                    ->badgeLabel('Quick Start')
                    ->title('Configure npm for Private Packages')
                    ->description('Follow these steps to configure your project to install private npm packages from Packgrid.')
                    ->heroIcon('heroicon-o-cog-6-tooth')
                    ->heroIconGradient('red', 'orange'),

                Section::make('Step 1: Create a GitHub Credential')
                    ->icon('heroicon-o-key')
                    ->iconColor('primary')
                    ->description('Required for private repositories')
                    ->collapsible()
                    ->schema([
                        TextContent::make('If your npm packages are in private GitHub repositories, you need to add a GitHub credential in Packgrid:'),
                        BulletList::make([
                            'Go to <strong>Credentials</strong> in the sidebar',
                            'Click <strong>Add Credential</strong>',
                            'Enter your GitHub Personal Access Token with <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">repo</code> scope',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),

                Section::make('Step 2: Register Your Repository')
                    ->icon('heroicon-o-cube')
                    ->iconColor('primary')
                    ->description('Add your npm package repository')
                    ->collapsible()
                    ->schema([
                        TextContent::make('Register your GitHub repository as an npm package:'),
                        BulletList::make([
                            'Go to <strong>Repositories</strong> in the sidebar',
                            'Click <strong>Add Repository</strong>',
                            'Enter the GitHub repository URL',
                            'Select <strong>npm</strong> as the format',
                            'Click <strong>Sync</strong> to fetch package metadata',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-information-circle')
                            ->title('package.json Required')
                            ->description('Your repository must have a package.json with a scoped name like "@myorg/package-name".'),
                    ]),

                Section::make('Step 3: Create a Packgrid Token')
                    ->icon('heroicon-o-ticket')
                    ->iconColor('primary')
                    ->description('For npm authentication')
                    ->collapsible()
                    ->schema([
                        TextContent::make('Create an access token to authenticate npm requests:'),
                        BulletList::make([
                            'Go to <strong>Tokens</strong> in the sidebar',
                            'Click <strong>Create Token</strong>',
                            'Copy the generated token (you\'ll need it for .npmrc)',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),

                Section::make('Step 4: Configure .npmrc')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description('Point npm to Packgrid')
                    ->collapsible()
                    ->schema([
                        TextContent::make('Create or edit <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">.npmrc</code> in your project root (or globally in your home directory):'),
                        CodeBlock::make($this->npmrcSnippet)
                            ->copyLabel('.npmrc'),
                        AlertBox::make()
                            ->warning()
                            ->icon('heroicon-o-exclamation-triangle')
                            ->title('Replace Values')
                            ->description('Replace @myorg with your actual scope and YOUR_PACKGRID_TOKEN with the token from Step 3.'),
                    ]),

                Section::make('Step 5: Install Your Package')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description('Test the installation')
                    ->collapsible()
                    ->schema([
                        TextContent::make('Now you can install your private package:'),
                        CodeBlock::make('npm install @myorg/your-package')
                            ->copyLabel('Command'),
                        TextContent::make('Or with yarn:'),
                        CodeBlock::make('yarn add @myorg/your-package')
                            ->copyLabel('Command'),
                    ]),

                QuickTips::make()
                    ->icon('heroicon-o-light-bulb')
                    ->title('Quick Tips')
                    ->items([
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Package names must be scoped (e.g., @myorg/package)'],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'The scope in .npmrc must match your package scope'],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Run sync after adding new repositories'],
                        ['icon' => 'heroicon-s-check', 'color' => 'emerald', 'text' => 'Works with npm, yarn, and pnpm'],
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.setup-guide-content');
    }
}
