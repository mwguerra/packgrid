<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Filament\Resources\RepositoryResource;
use App\Models\Repository;
use App\Services\RepositorySyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRepositories extends ListRecords
{
    protected static string $resource = RepositoryResource::class;

    public function getSubheading(): ?string
    {
        return __('repository.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAll')
                ->label(__('repository.action.sync_all'))
                ->icon('heroicon-o-arrow-path')
                ->tooltip(__('repository.action.sync_all_tooltip'))
                ->action(function (): void {
                    $syncService = app(RepositorySyncService::class);
                    $repositories = Repository::query()->where('enabled', true)->get();
                    $successCount = 0;
                    $failCount = 0;

                    foreach ($repositories as $repository) {
                        try {
                            $syncService->sync($repository);
                            $successCount++;
                        } catch (\Throwable) {
                            $failCount++;
                        }
                    }

                    if ($failCount === 0) {
                        Notification::make()
                            ->title(__('repository.notification.all_synced', ['count' => $successCount]))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('repository.notification.partial_sync', ['success' => $successCount, 'failed' => $failCount]))
                            ->warning()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading(__('repository.modal.sync_all_heading'))
                ->modalDescription(__('repository.modal.sync_all_description'))
                ->modalSubmitActionLabel(__('repository.action.sync_all')),
            CreateAction::make()
                ->label(__('repository.action.add'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
