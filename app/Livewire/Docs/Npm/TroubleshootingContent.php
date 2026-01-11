<?php

namespace App\Livewire\Docs\Npm;

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
            ->title(__('docs.npm.troubleshooting.copied_notification', ['label' => $label]))
            ->success()
            ->send();
    }

    public function getNpmrcExampleProperty(): string
    {
        return "@myorg:registry=https://packgrid.test/npm/\n//packgrid.test/npm/:_authToken=your-token-here";
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SearchComponent::make()
                    ->heading(__('docs.npm.troubleshooting.heading'))
                    ->placeholder(__('docs.npm.troubleshooting.search_placeholder'))
                    ->githubUrl('https://github.com/mwguerra/packgrid/issues')
                    ->haystack([
                        // E401 Unauthorized
                        ErrorSection::make()
                            ->searchId('e401')
                            ->errorIcon('heroicon-o-key')
                            ->errorTitle(__('docs.npm.troubleshooting.e401.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.e401.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.e401.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.npm.troubleshooting.e401.solution_intro')),
                                BulletList::make([
                                    __('docs.npm.troubleshooting.e401.solution1'),
                                    __('docs.npm.troubleshooting.e401.solution2'),
                                    __('docs.npm.troubleshooting.e401.solution3'),
                                ]),
                                TextContent::make(__('docs.npm.troubleshooting.e401.solution_example')),
                                CodeBlock::make($this->npmrcExample)
                                    ->copyLabel('.npmrc'),
                            ]),

                        // E404 Package Not Found
                        ErrorSection::make()
                            ->searchId('e404')
                            ->errorIcon('heroicon-o-cube')
                            ->errorTitle(__('docs.npm.troubleshooting.e404.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.e404.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.e404.message'))
                            ->solutionSchema([
                                BulletList::make([
                                    __('docs.npm.troubleshooting.e404.solution1'),
                                    __('docs.npm.troubleshooting.e404.solution2'),
                                    __('docs.npm.troubleshooting.e404.solution3'),
                                    __('docs.npm.troubleshooting.e404.solution4'),
                                ]),
                            ]),

                        // E400 Bad Request (URL encoding)
                        ErrorSection::make()
                            ->searchId('e400')
                            ->errorIcon('heroicon-o-exclamation-triangle')
                            ->errorTitle(__('docs.npm.troubleshooting.e400.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.e400.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.e400.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.npm.troubleshooting.e400.solution_intro')),
                                BulletList::make([
                                    __('docs.npm.troubleshooting.e400.solution1'),
                                    __('docs.npm.troubleshooting.e400.solution2'),
                                    __('docs.npm.troubleshooting.e400.solution3'),
                                ]),
                            ]),

                        // SSL Certificate Error
                        ErrorSection::make()
                            ->searchId('ssl')
                            ->errorIcon('heroicon-o-shield-exclamation')
                            ->errorTitle(__('docs.npm.troubleshooting.ssl.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.ssl.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.ssl.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.npm.troubleshooting.ssl.solution_intro')),
                                CodeBlock::make('npm config set strict-ssl false')
                                    ->copyLabel('Command'),
                                TextContent::make(__('docs.npm.troubleshooting.ssl.or_npmrc')),
                                CodeBlock::make('strict-ssl=false')
                                    ->copyLabel('.npmrc'),
                                TextContent::make(__('docs.npm.troubleshooting.ssl.warning')),
                            ]),

                        // Registry Scope Mismatch
                        ErrorSection::make()
                            ->searchId('scope')
                            ->errorIcon('heroicon-o-at-symbol')
                            ->errorTitle(__('docs.npm.troubleshooting.scope.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.scope.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.scope.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.npm.troubleshooting.scope.solution_intro')),
                                BulletList::make([
                                    __('docs.npm.troubleshooting.scope.solution1'),
                                    __('docs.npm.troubleshooting.scope.solution2'),
                                    __('docs.npm.troubleshooting.scope.solution3'),
                                ]),
                            ]),

                        // Tarball Download Failed
                        ErrorSection::make()
                            ->searchId('tarball')
                            ->errorIcon('heroicon-o-archive-box')
                            ->errorTitle(__('docs.npm.troubleshooting.tarball.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.tarball.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.tarball.message'))
                            ->solutionSchema([
                                BulletList::make([
                                    __('docs.npm.troubleshooting.tarball.solution1'),
                                    __('docs.npm.troubleshooting.tarball.solution2'),
                                    __('docs.npm.troubleshooting.tarball.solution3'),
                                    __('docs.npm.troubleshooting.tarball.solution4'),
                                ]),
                            ]),

                        // Invalid package.json
                        ErrorSection::make()
                            ->searchId('packagejson')
                            ->errorIcon('heroicon-o-document-text')
                            ->errorTitle(__('docs.npm.troubleshooting.packagejson.title'))
                            ->errorDescription(__('docs.npm.troubleshooting.packagejson.description'))
                            ->errorMessage(__('docs.npm.troubleshooting.packagejson.message'))
                            ->solutionSchema([
                                TextContent::make(__('docs.npm.troubleshooting.packagejson.solution_intro')),
                                CodeBlock::make('{
  "name": "@myorg/package-name",
  "version": "1.0.0",
  "main": "index.js"
}')
                                    ->copyLabel('package.json'),
                                BulletList::make([
                                    __('docs.npm.troubleshooting.packagejson.solution1'),
                                    __('docs.npm.troubleshooting.packagejson.solution2'),
                                    __('docs.npm.troubleshooting.packagejson.solution3'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.troubleshooting-content');
    }
}
