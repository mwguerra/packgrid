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

                Section::make('How Packgrid Compares')
                    ->icon('heroicon-o-scale')
                    ->iconColor('primary')
                    ->description('Comparison with npm private registry alternatives')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('There are several solutions for hosting private npm packages. Here\'s how Packgrid compares:'),
                        ComparisonTable::make()
                            ->products([
                                'packgrid' => ['name' => 'Packgrid', 'highlight' => true],
                                'verdaccio' => ['name' => 'Verdaccio'],
                                'github' => ['name' => 'GitHub Packages'],
                                'artifactory' => ['name' => 'JFrog Artifactory'],
                            ])
                            ->features([
                                [
                                    'name' => 'Multi-Protocol',
                                    'description' => 'Composer (PHP) + npm (JS)',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => false,
                                        'github' => true,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Hosting',
                                    'values' => [
                                        'packgrid' => 'Self-hosted',
                                        'verdaccio' => 'Self-hosted',
                                        'github' => 'Cloud',
                                        'artifactory' => 'Both',
                                    ],
                                ],
                                [
                                    'name' => 'Cost',
                                    'values' => [
                                        'packgrid' => 'Free',
                                        'verdaccio' => 'Free',
                                        'github' => 'Free tier + Paid',
                                        'artifactory' => 'Paid',
                                    ],
                                ],
                                [
                                    'name' => 'Open Source',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => true,
                                        'github' => false,
                                        'artifactory' => false,
                                    ],
                                ],
                                [
                                    'name' => 'Web Admin Panel',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => true,
                                        'github' => true,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => 'GitHub Integration',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => false,
                                        'github' => true,
                                        'artifactory' => 'partial',
                                    ],
                                ],
                                [
                                    'name' => 'Public Mirroring',
                                    'description' => 'Mirror npmjs.com',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => true,
                                        'github' => false,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Token Management',
                                    'description' => 'Advanced token controls',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => 'partial',
                                        'github' => true,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => 'IP Restrictions',
                                    'values' => [
                                        'packgrid' => true,
                                        'verdaccio' => false,
                                        'github' => false,
                                        'artifactory' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Setup Complexity',
                                    'values' => [
                                        'packgrid' => 'Simple',
                                        'verdaccio' => 'Simple',
                                        'github' => 'Managed',
                                        'artifactory' => 'Complex',
                                    ],
                                ],
                            ]),
                        TextContent::make('<strong>Packgrid\'s advantage:</strong> If you already use Packgrid for Composer packages, adding npm support requires zero additional setup. You get a unified registry for both PHP and JavaScript packages with the same token management and GitHub integration.'),
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
