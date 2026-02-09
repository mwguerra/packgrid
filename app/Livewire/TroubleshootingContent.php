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
            ->title(__('docs.troubleshooting.copied_notification', ['label' => $label]))
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
                    ->heading(__('docs.troubleshooting.heading'))
                    ->placeholder(__('docs.troubleshooting.search_placeholder'))
                    ->githubUrl('https://github.com/mwguerra/packgrid/issues')
                    ->haystack([
                        // SSL Certificate Error
                        ErrorSection::make()
                            ->searchId('ssl')
                            ->errorIcon('heroicon-o-shield-exclamation')
                            ->errorTitle(__('docs.troubleshooting.ssl.title'))
                            ->errorDescription(__('docs.troubleshooting.ssl.description'))
                            ->errorMessage(__('docs.troubleshooting.ssl.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.troubleshooting.ssl.solution')),
                                CodeBlock::make($this->sslSnippet)
                                    ->copyLabel('Code'),
                            ]),

                        // Invalid JSON in auth.json
                        ErrorSection::make()
                            ->searchId('json')
                            ->errorIcon('heroicon-o-document-text')
                            ->errorTitle(__('docs.troubleshooting.json.title'))
                            ->errorDescription(__('docs.troubleshooting.json.description'))
                            ->errorMessage(__('docs.troubleshooting.json.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.troubleshooting.json.solution')),
                            ]),

                        // Invalid Credentials (HTTP 401)
                        ErrorSection::make()
                            ->searchId('credentials')
                            ->errorIcon('heroicon-o-key')
                            ->errorTitle(__('docs.troubleshooting.credentials.title'))
                            ->errorDescription(__('docs.troubleshooting.credentials.description'))
                            ->errorMessage(__('docs.troubleshooting.credentials.message'))
                            ->solutionSchema([
                                BulletList::make([
                                    __('docs.troubleshooting.credentials.solution1'),
                                    __('docs.troubleshooting.credentials.solution2'),
                                    __('docs.troubleshooting.credentials.solution3'),
                                ]),
                            ]),

                        // Package Not Found
                        ErrorSection::make()
                            ->searchId('notfound')
                            ->errorIcon('heroicon-o-cube')
                            ->errorTitle(__('docs.troubleshooting.notfound.title'))
                            ->errorDescription(__('docs.troubleshooting.notfound.description'))
                            ->errorMessage(__('docs.troubleshooting.notfound.message'))
                            ->solutionSchema([
                                BulletList::make([
                                    __('docs.troubleshooting.notfound.solution1'),
                                    __('docs.troubleshooting.notfound.solution2'),
                                    __('docs.troubleshooting.notfound.solution3'),
                                ]),
                            ]),

                        // Minimum Stability Mismatch
                        ErrorSection::make()
                            ->searchId('stability')
                            ->errorIcon('heroicon-o-adjustments-horizontal')
                            ->errorTitle(__('docs.troubleshooting.stability.title'))
                            ->errorDescription(__('docs.troubleshooting.stability.description'))
                            ->errorMessage(__('docs.troubleshooting.stability.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.troubleshooting.stability.solution1')),
                                TextContent::make(__('docs.troubleshooting.stability.solution2')),
                                CodeBlock::make('composer require vendor/package:dev-main')
                                    ->copyLabel('Command'),
                                TextContent::make(__('docs.troubleshooting.stability.solution3')),
                                CodeBlock::make($this->stabilitySnippet)
                                    ->copyLabel('Code'),
                            ]),

                        // Branch Name Mismatch
                        ErrorSection::make()
                            ->searchId('branch')
                            ->errorIcon('heroicon-o-arrow-path')
                            ->errorTitle(__('docs.troubleshooting.branch.title'))
                            ->errorDescription(__('docs.troubleshooting.branch.description'))
                            ->errorMessage(__('docs.troubleshooting.branch.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.troubleshooting.branch.solution')),
                                CodeBlock::make('composer require vendor/package:dev-main')
                                    ->copyLabel('Command'),
                            ]),

                        // Dependency Resolution Failed
                        ErrorSection::make()
                            ->searchId('dependency')
                            ->errorIcon('heroicon-o-puzzle-piece')
                            ->errorTitle(__('docs.troubleshooting.dependency.title'))
                            ->errorDescription(__('docs.troubleshooting.dependency.description'))
                            ->errorMessage(__('docs.troubleshooting.dependency.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.troubleshooting.dependency.solution')),
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
