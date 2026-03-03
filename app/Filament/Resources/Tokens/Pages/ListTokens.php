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
        $messageJs = Js::from(__('token.notification.copied'));

        return [
            Action::make('copyComposerSetup')
                ->label(__('token.action.copy_composer_setup'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->alpineClickHandler(<<<JS
                    window.navigator.clipboard.writeText({$commandJs})
                    \$tooltip({$messageJs}, {
                        theme: \$store.theme,
                        timeout: 2000,
                    })
                    JS),
            CreateAction::make(),
        ];
    }
}
