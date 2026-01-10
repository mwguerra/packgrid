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
                    ->badgeLabel('Data Flow')
                    ->title('How npm Packages Flow Through Packgrid')
                    ->description('Understand how your private packages travel from GitHub to your project using the npm protocol.')
                    ->heroIcon('heroicon-o-server-stack')
                    ->heroIconGradient('red', 'orange'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-document-text')
                            ->color('blue')
                            ->title('Metadata Endpoint')
                            ->description('/@scope/package format'),
                        StatCard::make()
                            ->icon('heroicon-s-archive-box')
                            ->color('purple')
                            ->title('Tarball Downloads')
                            ->description('.tgz files proxied'),
                        StatCard::make()
                            ->icon('heroicon-s-shield-check')
                            ->color('emerald')
                            ->title('Bearer Auth')
                            ->description('Token in .npmrc'),
                    ])
                    ->gridColumns(3),

                Section::make('The Three Players')
                    ->icon('heroicon-o-user-group')
                    ->iconColor('primary')
                    ->description('Understanding the actors involved')
                    ->schema([
                        FlowDiagram::make()
                            ->actors([
                                [
                                    'name' => 'Your Project',
                                    'description' => 'Runs npm install',
                                    'icon' => 'heroicon-o-code-bracket',
                                    'color' => 'red',
                                ],
                                [
                                    'name' => 'Packgrid',
                                    'description' => 'npm Registry Proxy',
                                    'icon' => 'heroicon-o-server-stack',
                                    'color' => 'amber',
                                ],
                                [
                                    'name' => 'GitHub',
                                    'description' => 'Source of truth',
                                    'icon' => 'heroicon-o-cloud',
                                    'color' => 'gray',
                                ],
                            ])
                            ->steps([]),
                    ]),

                Section::make('Phase 1: Repository Sync')
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('primary')
                    ->description('How Packgrid learns about your npm packages')
                    ->collapsible()
                    ->schema([
                        TextContent::make('Before your project can install packages, Packgrid needs to sync the repository. During sync, Packgrid reads the <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">package.json</code> from each tag/branch.'),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => 'Fetch Repository Tags',
                                    'description' => 'Packgrid uses your stored GitHub credential to list all tags and branches.',
                                    'data' => 'GET /repos/{owner}/{repo}/tags',
                                    'dataLabel' => 'API Request',
                                    'icon' => 'heroicon-o-tag',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => 'Read package.json for Each Version',
                                    'description' => 'For each tag/branch, Packgrid fetches the package.json to understand the package name and dependencies.',
                                    'data' => 'GET /repos/{owner}/{repo}/contents/package.json?ref={tag}',
                                    'dataLabel' => 'API Request',
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-red-100 dark:bg-red-900',
                                    'iconColor' => 'text-red-600 dark:text-red-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'Storage',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                    'title' => 'Store npm Metadata',
                                    'description' => 'Packgrid builds npm-compatible package metadata with tarball URLs pointing back to Packgrid.',
                                    'data' => '{"name": "@scope/pkg", "versions": {"1.0.0": {"dist": {"tarball": "..."}}}}',
                                    'dataLabel' => 'Stored Data',
                                    'icon' => 'heroicon-o-circle-stack',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),

                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-light-bulb')
                            ->title('Scoped Packages')
                            ->description('npm packages from Packgrid must be scoped (e.g., @myorg/package). The scope is determined by the "name" field in package.json.'),
                    ]),

                Section::make('Phase 2: npm Install')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description('What happens when you run npm install')
                    ->collapsible()
                    ->schema([
                        TextContent::make('When you run <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">npm install @myorg/package</code> in your project:'),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => 'Project',
                                    'to' => 'Packgrid',
                                    'fromColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => 'Request Package Metadata',
                                    'description' => 'npm requests package info from Packgrid, authenticating with your Bearer token from .npmrc.',
                                    'data' => 'GET /@myorg/package (Authorization: Bearer {token})',
                                    'dataLabel' => 'HTTP Request',
                                    'icon' => 'heroicon-o-key',
                                    'iconBg' => 'bg-amber-100 dark:bg-amber-900',
                                    'iconColor' => 'text-amber-600 dark:text-amber-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'Project',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'title' => 'Return Package Metadata',
                                    'description' => 'Packgrid returns npm-format metadata with version info and tarball URLs.',
                                    'data' => '{"name": "@myorg/package", "versions": {"1.0.0": {"dist": {"tarball": "https://packgrid/..."}}}}',
                                    'dataLabel' => 'JSON Response',
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-red-100 dark:bg-red-900',
                                    'iconColor' => 'text-red-600 dark:text-red-400',
                                ],
                                [
                                    'from' => 'Project',
                                    'to' => 'Packgrid',
                                    'fromColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => 'Request Tarball Download',
                                    'description' => 'npm requests the .tgz tarball from the URL in the metadata.',
                                    'data' => 'GET /-/@myorg/package-1.0.0.tgz',
                                    'dataLabel' => 'HTTP Request',
                                    'icon' => 'heroicon-o-archive-box-arrow-down',
                                    'iconBg' => 'bg-purple-100 dark:bg-purple-900',
                                    'iconColor' => 'text-purple-600 dark:text-purple-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => 'Fetch from GitHub',
                                    'description' => 'Packgrid downloads the zipball from GitHub and converts it to a tarball.',
                                    'data' => 'GET /repos/{owner}/{repo}/zipball/{ref}',
                                    'dataLabel' => 'GitHub API Request',
                                    'icon' => 'heroicon-o-cloud-arrow-down',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => 'GitHub',
                                    'to' => 'Project',
                                    'fromColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'toColor' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'title' => 'Stream Tarball to Project',
                                    'description' => 'The .tgz file streams through Packgrid directly to your project.',
                                    'data' => '.tgz tarball (gzipped tar archive)',
                                    'dataLabel' => 'Streamed Data',
                                    'icon' => 'heroicon-o-arrow-down-circle',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),
                    ]),

                Section::make('Authentication Flow')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->description('How Bearer token authentication works')
                    ->collapsible()
                    ->schema([
                        TextContent::make('npm uses Bearer token authentication configured in <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">.npmrc</code>:'),

                        BulletList::make([
                            '<strong>Your Project</strong> sends the Bearer token from .npmrc with each request',
                            '<strong>Packgrid</strong> validates the token and uses GitHub credentials internally',
                            '<strong>GitHub</strong> only sees requests from Packgrid with its stored credentials',
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),

                        AlertBox::make()
                            ->success()
                            ->icon('heroicon-o-shield-check')
                            ->title('Security Benefit')
                            ->description('Your GitHub token stays on the Packgrid server. Team members only need revocable Packgrid tokens in their .npmrc files.'),
                    ]),

                Section::make('Summary')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('primary')
                    ->description('The complete picture')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            '<strong>Sync Phase:</strong> Packgrid reads package.json from GitHub and builds npm metadata',
                            '<strong>Install Phase:</strong> npm gets metadata from /@scope/package, then downloads .tgz tarballs',
                            '<strong>Authentication:</strong> Bearer token in .npmrc authenticates with Packgrid',
                            '<strong>Data Flow:</strong> Tarballs stream through Packgrid (converted from GitHub zipballs)',
                            '<strong>Security:</strong> GitHub credentials stay on Packgrid; only revocable tokens are distributed',
                        ])->bulletIcon('heroicon-s-check')->bulletColor('primary'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.how-it-works-content');
    }
}
