<?php

namespace App\Livewire;

use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\CodeBlock;
use App\Filament\Schemas\Components\ErrorSection;
use App\Filament\Schemas\Components\SearchComponent;
use App\Filament\Schemas\Components\TextContent;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class TroubleshootingContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function showCopiedNotification(string $label = 'Content'): void
    {
        Notification::make()
            ->title("{$label} copied to clipboard")
            ->success()
            ->send();
    }

    public function getSslSnippetProperty(): string
    {
        return json_encode([
            'repositories' => [
                [
                    'type' => 'composer',
                    'url' => 'https://packgrid.test',
                    'options' => [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getStabilitySnippetProperty(): string
    {
        return json_encode([
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SearchComponent::make()
                    ->heading('The solution for your error is probably here.')
                    ->placeholder('Search...')
                    ->githubUrl('https://github.com/mwguerra/packgrid/issues')
                    ->haystack([
                        // SSL Certificate Error
                        ErrorSection::make()
                            ->searchId('ssl')
                            ->errorIcon('heroicon-o-shield-exclamation')
                            ->errorTitle('SSL Certificate Error')
                            ->errorDescription('curl error 60: SSL peer certificate was not OK')
                            ->errorMessage('curl error 60 while downloading https://packgrid.test/packages.json: SSL peer certificate or SSH remote key was not OK')
                            ->solutionSchema([
                                TextContent::make('For local development with self-signed certificates, add SSL options to your repository config in <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">composer.json</code>:'),
                                CodeBlock::make($this->sslSnippet)
                                    ->copyLabel('Code'),
                            ]),

                        // Invalid JSON in auth.json
                        ErrorSection::make()
                            ->searchId('json')
                            ->errorIcon('heroicon-o-document-text')
                            ->errorTitle('Invalid JSON in auth.json')
                            ->errorDescription('Parse error in authentication file')
                            ->errorMessage('"auth.json" does not contain valid JSON
Parse error on line 1: Expected one of: \'STRING\', \'NUMBER\', \'NULL\', \'TRUE\', \'FALSE\', \'{\', \'[\'')
                            ->solutionSchema([
                                TextContent::make('Ensure your <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">auth.json</code> contains valid JSON. Use a JSON validator to check for syntax errors like missing quotes, trailing commas, or incorrect brackets.'),
                            ]),

                        // Invalid Credentials (HTTP 401)
                        ErrorSection::make()
                            ->searchId('credentials')
                            ->errorIcon('heroicon-o-key')
                            ->errorTitle('Invalid Credentials (HTTP 401)')
                            ->errorDescription('Authentication failed')
                            ->errorMessage('Invalid credentials (HTTP 401) for \'https://packgrid.test/packages.json\', aborting.')
                            ->solutionSchema([
                                BulletList::make([
                                    'Check that the token in your <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">auth.json</code> is correct',
                                    'Verify the token is enabled in Packgrid and has not expired',
                                    'Ensure the host in auth.json matches your Packgrid server exactly',
                                ]),
                            ]),

                        // Package Not Found
                        ErrorSection::make()
                            ->searchId('notfound')
                            ->errorIcon('heroicon-o-cube')
                            ->errorTitle('Package Not Found')
                            ->errorDescription('Could not find a matching version of package')
                            ->errorMessage('Could not find a matching version of package vendor/package. Check the package spelling, your version constraint and that the package is available in a stability which matches your minimum-stability (stable).')
                            ->solutionSchema([
                                BulletList::make([
                                    'The package name must match the <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">"name"</code> field in the repository\'s composer.json, not the GitHub repository name',
                                    'Ensure the repository has been synced in Packgrid (check the Repositories page for sync status)',
                                    'Laravel projects have <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">"name": "laravel/laravel"</code> by default - this needs to be changed to use them as packages',
                                ]),
                            ]),

                        // Minimum Stability Mismatch
                        ErrorSection::make()
                            ->searchId('stability')
                            ->errorIcon('heroicon-o-adjustments-horizontal')
                            ->errorTitle('Minimum Stability Mismatch')
                            ->errorDescription('Package requires different stability level')
                            ->errorMessage('Could not find a version of package vendor/package matching your minimum-stability (stable). Require it with an explicit version constraint allowing its desired stability.')
                            ->solutionSchema([
                                TextContent::make('If the package only has dev versions (no stable tags), you can either:'),
                                TextContent::make('Require with explicit dev version:'),
                                CodeBlock::make('composer require vendor/package:dev-main')
                                    ->copyLabel('Command'),
                                TextContent::make('Or add minimum-stability to your composer.json:'),
                                CodeBlock::make($this->stabilitySnippet)
                                    ->copyLabel('Code'),
                            ]),

                        // Branch Name Mismatch
                        ErrorSection::make()
                            ->searchId('branch')
                            ->errorIcon('heroicon-o-arrow-path')
                            ->errorTitle('Branch Name Mismatch')
                            ->errorDescription('dev-master vs dev-main')
                            ->errorMessage('Root composer.json requires vendor/package dev-master, found vendor/package[dev-main] but it does not match the constraint. Perhaps dev-master was renamed to dev-main?')
                            ->solutionSchema([
                                TextContent::make('Many repositories have renamed their default branch from <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">master</code> to <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">main</code>. Use the correct branch name:'),
                                CodeBlock::make('composer require vendor/package:dev-main')
                                    ->copyLabel('Command'),
                            ]),

                        // Dependency Resolution Failed
                        ErrorSection::make()
                            ->searchId('dependency')
                            ->errorIcon('heroicon-o-puzzle-piece')
                            ->errorTitle('Dependency Resolution Failed')
                            ->errorDescription('Locked packages prevent installation')
                            ->errorMessage('Your requirements could not be resolved to an installable set of packages.

Problem 1
  - vendor/package dev-main requires some/dependency ^4.0
  - some/dependency is fixed to 3.0.0 (lock file version)

Use the option --with-all-dependencies (-W) to allow upgrades.')
                            ->solutionSchema([
                                TextContent::make('When installing a package requires updating other locked dependencies, use the <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">-W</code> flag to allow Composer to update all related packages:'),
                                CodeBlock::make('composer require vendor/package:dev-main -W')
                                    ->copyLabel('Command'),
                            ]),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.troubleshooting-content');
    }
}
