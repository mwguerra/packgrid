<?php

namespace App\Filament\Resources\Tokens\Pages;

use App\Filament\Resources\Tokens\TokenResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    public function getSubheading(): ?string
    {
        return __('token.subheading');
    }

    protected function getHeaderActions(): array
    {
        $repositoriesJson = json_encode([
            'repositories' => [
                [
                    'type' => 'composer',
                    'url' => config('app.url'),
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            Action::make('copyComposerSetup')
                ->label(__('token.action.copy_composer_setup'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->action(function () use ($repositoriesJson): void {
                    Notification::make()
                        ->title(__('token.notification.composer_setup_copied'))
                        ->body(new HtmlString('<pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs font-mono overflow-x-auto"><code>'.e($repositoriesJson).'</code></pre>'))
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'x-on:click' => 'navigator.clipboard.writeText('.json_encode($repositoriesJson).')',
                ]),
            CreateAction::make(),
        ];
    }
}
