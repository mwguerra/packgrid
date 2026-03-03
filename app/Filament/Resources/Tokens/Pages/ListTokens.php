<?php

namespace App\Filament\Resources\Tokens\Pages;

use App\Filament\Resources\Tokens\TokenResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Js;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    public function getSubheading(): ?string
    {
        return __('token.subheading');
    }

    protected function getHeaderActions(): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $command = "composer config repositories.packgrid composer {$baseUrl}";
        $commandJs = Js::from($command);
        $tooltipJs = Js::from(__('token.notification.copied'));
        $titleJs = Js::from(__('token.notification.composer_setup_copied'));
        $bodyHtml = '<pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs font-mono overflow-x-auto whitespace-pre-wrap">'.e($command).'</pre>';
        $bodyJs = Js::from($bodyHtml);

        return [
            Action::make('copyComposerSetup')
                ->label(__('token.action.copy_composer_setup'))
                ->tooltip(__('token.action.copy_composer_setup_tooltip'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->alpineClickHandler(<<<JS
                    window.navigator.clipboard.writeText({$commandJs})
                    \$tooltip({$tooltipJs}, {
                        theme: \$store.theme,
                        timeout: 2000,
                    })
                    new FilamentNotification()
                        .title({$titleJs})
                        .body({$bodyJs})
                        .success()
                        .seconds(10)
                        .send()
                    JS),
            CreateAction::make(),
        ];
    }
}
