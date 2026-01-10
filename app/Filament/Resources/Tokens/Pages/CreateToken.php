<?php

namespace App\Filament\Resources\Tokens\Pages;

use App\Filament\Resources\Tokens\TokenResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateToken extends CreateRecord
{
    protected static string $resource = TokenResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('token.action.create_title');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $token = $this->record->token;

        Notification::make()
            ->title(__('token.notification.created'))
            ->body(__('token.notification.created_body'))
            ->warning()
            ->persistent()
            ->actions([
                Action::make('copy')
                    ->label(__('token.action.copy'))
                    ->icon('heroicon-o-clipboard-document')
                    ->url("javascript:navigator.clipboard.writeText('{$token}')")
                    ->close(),
            ])
            ->send();
    }
}
