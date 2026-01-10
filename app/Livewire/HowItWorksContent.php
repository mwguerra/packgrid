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
                    ->badgeLabel('Data Flow')
                    ->title('Understanding the Magic Behind Packgrid')
                    ->description('See exactly how your private packages travel from GitHub to your project, and how authentication keeps everything secure.')
                    ->heroIcon('heroicon-o-server-stack')
                    ->heroIconGradient('blue', 'indigo'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-document-text')
                            ->color('blue')
                            ->title('Metadata Only')
                            ->description('Packgrid stores package info'),
                        StatCard::make()
                            ->icon('heroicon-s-arrows-right-left')
                            ->color('purple')
                            ->title('Proxied Downloads')
                            ->description('Files stream through Packgrid'),
                        StatCard::make()
                            ->icon('heroicon-s-shield-check')
                            ->color('emerald')
                            ->title('Single Credential')
                            ->description('GitHub token never leaves'),
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
                                    'description' => 'Runs composer require',
                                    'icon' => 'heroicon-o-code-bracket',
                                    'color' => 'blue',
                                ],
                                [
                                    'name' => 'Packgrid',
                                    'description' => 'Middleware & Proxy',
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
                    ->description('How Packgrid learns about your packages')
                    ->collapsible()
                    ->schema([
                        TextContent::make('Before your project can install packages, Packgrid needs to know about them. This happens during the <strong>sync phase</strong>, triggered manually or by schedule.'),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'GitHub',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'title' => 'Fetch Repository Metadata',
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
                                    'title' => 'Read composer.json for Each Version',
                                    'description' => 'For each tag/branch, Packgrid fetches the composer.json to understand dependencies and package name.',
                                    'data' => 'GET /repos/{owner}/{repo}/contents/composer.json?ref={tag}',
                                    'dataLabel' => 'API Request',
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-blue-100 dark:bg-blue-900',
                                    'iconColor' => 'text-blue-600 dark:text-blue-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'Storage',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                    'title' => 'Store Package Index',
                                    'description' => 'Packgrid builds and stores a Composer-compatible package index (packages.json) with download URLs pointing back to Packgrid.',
                                    'data' => '{"packages": {"vendor/name": {"1.0.0": {...}, "2.0.0": {...}}}}',
                                    'dataLabel' => 'Stored Data',
                                    'icon' => 'heroicon-o-circle-stack',
                                    'iconBg' => 'bg-emerald-100 dark:bg-emerald-900',
                                    'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                                ],
                            ]),

                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-light-bulb')
                            ->title('No Files Stored')
                            ->description('During sync, Packgrid only stores metadata (package names, versions, dependencies). The actual source code files are never downloaded or stored on Packgrid.'),
                    ]),

                Section::make('Phase 2: Composer Install')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconColor('primary')
                    ->description('What happens when you run composer require')
                    ->collapsible()
                    ->schema([
                        TextContent::make('When you run <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">composer require vendor/package</code> in your project, here\'s the complete flow:'),

                        FlowDiagram::make()
                            ->actors([])
                            ->steps([
                                [
                                    'from' => 'Project',
                                    'to' => 'Packgrid',
                                    'fromColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => 'Request Package Index',
                                    'description' => 'Composer requests the package list from Packgrid, authenticating with your Packgrid token from auth.json.',
                                    'data' => 'GET /packages.json (Authorization: Bearer {packgrid-token})',
                                    'dataLabel' => 'HTTP Request',
                                    'icon' => 'heroicon-o-key',
                                    'iconBg' => 'bg-amber-100 dark:bg-amber-900',
                                    'iconColor' => 'text-amber-600 dark:text-amber-400',
                                ],
                                [
                                    'from' => 'Packgrid',
                                    'to' => 'Project',
                                    'fromColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'toColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'title' => 'Return Package Metadata',
                                    'description' => 'Packgrid returns the package index with all available versions and their download URLs.',
                                    'data' => '{"packages": {"vendor/package": {"1.0.0": {"dist": {"url": "https://packgrid/dist/..."}}}}}',
                                    'dataLabel' => 'JSON Response',
                                    'icon' => 'heroicon-o-document-text',
                                    'iconBg' => 'bg-blue-100 dark:bg-blue-900',
                                    'iconColor' => 'text-blue-600 dark:text-blue-400',
                                ],
                                [
                                    'from' => 'Project',
                                    'to' => 'Packgrid',
                                    'fromColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'toColor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                    'title' => 'Request Package Download',
                                    'description' => 'Composer requests the actual package zip file from the URL in the metadata.',
                                    'data' => 'GET /dist/{owner}/{repo}/{ref}.zip',
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
                                    'title' => 'Proxy Download from GitHub',
                                    'description' => 'Packgrid uses its stored GitHub credential to download the zipball from GitHub\'s API.',
                                    'data' => 'GET /repos/{owner}/{repo}/zipball/{ref} (Authorization: Bearer {github-token})',
                                    'dataLabel' => 'GitHub API Request',
                                    'icon' => 'heroicon-o-cloud-arrow-down',
                                    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                    'iconColor' => 'text-gray-600 dark:text-gray-400',
                                ],
                                [
                                    'from' => 'GitHub',
                                    'to' => 'Project',
                                    'fromColor' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                    'toColor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'title' => 'Stream Package to Project',
                                    'description' => 'The zip file streams through Packgrid directly to your project. Packgrid acts as a secure proxy.',
                                    'data' => '~500KB - 50MB (typical package size)',
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
                    ->description('How security is maintained at each step')
                    ->collapsible()
                    ->schema([
                        TextContent::make('The key security benefit of Packgrid is <strong>credential isolation</strong>. Your GitHub token never leaves the Packgrid server.'),

                        BulletList::make([
                            '<strong>Your Project</strong> only knows about the Packgrid token. It has no access to GitHub credentials.',
                            '<strong>Packgrid</strong> validates Packgrid tokens and uses GitHub credentials internally for API calls.',
                            '<strong>GitHub</strong> only sees requests from Packgrid, never from individual developers or CI/CD systems.',
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),

                        AlertBox::make()
                            ->success()
                            ->icon('heroicon-o-shield-check')
                            ->title('Security Benefit')
                            ->description('If a developer\'s machine is compromised, the attacker only gets a Packgrid token which can be easily revoked. Your GitHub token (which may have broader access) remains safe on the Packgrid server.'),
                    ]),

                Section::make('What Passes Through Packgrid?')
                    ->icon('heroicon-o-funnel')
                    ->iconColor('primary')
                    ->description('Understanding data transfer')
                    ->collapsible()
                    ->schema([
                        TextContent::make('A common question is whether those thousands of files from your private repository actually pass through Packgrid. <strong>Yes, they do</strong> — but as a stream.'),

                        BulletList::make([
                            '<strong>Metadata requests</strong> — Package index and version info (small JSON files, ~1-100KB)',
                            '<strong>Package downloads</strong> — Zip files containing all repository files (streamed, not stored)',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),

                        AlertBox::make()
                            ->info()
                            ->icon('heroicon-o-arrow-path')
                            ->title('Streaming Proxy')
                            ->description('Packgrid does not store package files on disk. When you download a package, it streams directly from GitHub through Packgrid to your project. This means no additional storage is needed, but it also means each download requires a GitHub API call.'),
                    ]),

                Section::make('Summary')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('primary')
                    ->description('The complete picture')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            '<strong>Sync Phase:</strong> Packgrid reads repository metadata from GitHub and builds a Composer-compatible index',
                            '<strong>Install Phase:</strong> Composer gets metadata from Packgrid, then downloads packages through Packgrid',
                            '<strong>Authentication:</strong> Projects use Packgrid tokens; Packgrid uses GitHub tokens internally',
                            '<strong>Data Flow:</strong> Actual package files stream through Packgrid (not stored) on every download',
                            '<strong>Security:</strong> GitHub credentials never leave Packgrid; only revocable Packgrid tokens are distributed',
                        ])->bulletIcon('heroicon-s-check')->bulletColor('primary'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.how-it-works-content');
    }
}
