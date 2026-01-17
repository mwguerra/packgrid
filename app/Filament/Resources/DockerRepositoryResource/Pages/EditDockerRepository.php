<?php

namespace App\Filament\Resources\DockerRepositoryResource\Pages;

use App\Filament\Resources\DockerRepositoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDockerRepository extends EditRecord
{
    protected static string $resource = DockerRepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('docker_repository.action.remove'))
                ->requiresConfirmation()
                ->modalHeading(__('docker_repository.modal.remove_heading'))
                ->modalDescription(__('docker_repository.modal.remove_description'))
                ->modalSubmitActionLabel(__('docker_repository.action.remove')),
        ];
    }
}
