<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Filament\Resources\RepositoryResource;
use App\Services\RepositorySyncService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRepository extends ViewRecord
{
    protected static string $resource = RepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(fn (): string => $this->record->last_error
                    ? __('repository.action.retry_sync')
                    : __('repository.action.sync'))
                ->icon('heroicon-o-arrow-path')
                ->tooltip(__('repository.action.sync_tooltip'))
                ->color(fn (): string => $this->record->last_error ? 'danger' : 'primary')
                ->action(function (): void {
                    try {
                        app(RepositorySyncService::class)->sync($this->record);
                        $this->record->refresh();

                        Notification::make()
                            ->title(__('repository.notification.synced'))
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        $this->record->refresh();

                        Notification::make()
                            ->title(__('repository.notification.sync_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            EditAction::make(),
        ];
    }
}
