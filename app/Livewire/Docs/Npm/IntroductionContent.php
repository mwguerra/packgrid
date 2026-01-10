<?php

namespace App\Livewire\Docs\Npm;

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
                    ->badgeLabel('npm Registry')
                    ->title('Private npm packages from GitHub.')
                    ->description('Serve your private GitHub repositories as npm packages with simple Bearer token authentication.')
                    ->heroIcon('heroicon-o-cube')
                    ->heroIconGradient('red', 'orange'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-key')
                            ->color('amber')
                            ->title('One Credential')
                            ->description('GitHub token stays here'),
                        StatCard::make()
                            ->icon('heroicon-s-users')
                            ->color('blue')
                            ->title('Bearer Tokens')
                            ->description('Simple .npmrc auth'),
                        StatCard::make()
                            ->icon('heroicon-s-cube')
                            ->color('red')
                            ->title('Standard npm')
                            ->description('Works with npm, yarn, pnpm'),
                    ])
                    ->gridColumns(3),

                AlertBox::make()
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->title('Important Notice')
                    ->description('Please read before using Packgrid for npm packages.')
                    ->items([
                        '<strong>Scoped packages only</strong> — npm packages must be scoped (e.g., @myorg/package)',
                        '<strong>Bearer token authentication</strong> — npm uses Bearer tokens in .npmrc, not http-basic like Composer',
                        '<strong>Tarball proxying</strong> — Packgrid proxies .tgz downloads from GitHub releases or generates them from zipballs',
                        '<strong>Security is your responsibility</strong> — Ensure your server is properly secured with HTTPS',
                    ]),

                Section::make('What is npm Support?')
                    ->icon('heroicon-o-question-mark-circle')
                    ->iconColor('primary')
                    ->description('How Packgrid serves npm packages')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('Packgrid can serve your private GitHub repositories as an <strong>npm registry</strong>, allowing you to install them with npm, yarn, or pnpm just like any public package.'),
                        TextContent::make('The npm protocol uses a different format than Composer. Instead of <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">packages.json</code> and zip files, npm uses package metadata endpoints and <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">.tgz</code> tarballs.'),
                    ]),

                Section::make('Key Differences from Composer')
                    ->icon('heroicon-o-scale')
                    ->iconColor('primary')
                    ->description('Understanding npm vs Composer')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            '<strong>Scoped packages</strong> — npm packages must use scopes like <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@myorg/package-name</code>',
                            '<strong>Bearer authentication</strong> — npm uses <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">//registry:_authToken=TOKEN</code> in .npmrc instead of auth.json',
                            '<strong>Tarball format</strong> — npm downloads <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">.tgz</code> files instead of zip archives',
                            '<strong>Metadata format</strong> — npm uses a different JSON structure for package metadata',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),

                Section::make('Supported Features')
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('primary')
                    ->description('What works with npm support')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            '<strong>Scoped package installation</strong> — Install packages like <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">npm install @myorg/package</code>',
                            '<strong>Version resolution</strong> — Supports semantic versioning and version ranges',
                            '<strong>Bearer token auth</strong> — Standard npm authentication via .npmrc',
                            '<strong>Works with npm, yarn, pnpm</strong> — Compatible with all major package managers',
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),
                    ]),

                Section::make('Getting Started')
                    ->icon('heroicon-o-play')
                    ->iconColor('primary')
                    ->description('Ready to use npm support?')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('Head over to the <strong>Setup Guide</strong> tab for step-by-step instructions on configuring npm to use your Packgrid server. If you run into any issues, check the <strong>Troubleshooting</strong> tab for common error solutions.'),
                        BulletList::make([
                            'Add a GitHub credential for private repository access',
                            'Register your repositories with npm format',
                            'Create access tokens for authentication',
                            'Configure .npmrc in your projects',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.introduction-content');
    }
}
