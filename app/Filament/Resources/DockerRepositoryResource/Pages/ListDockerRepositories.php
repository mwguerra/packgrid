<?php

namespace App\Filament\Resources\DockerRepositoryResource\Pages;

use App\Filament\Resources\DockerRepositoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDockerRepositories extends ListRecords
{
    protected static string $resource = DockerRepositoryResource::class;

    public function getSubheading(): ?string
    {
        return __('docker_repository.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('docker_repository.action.create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
