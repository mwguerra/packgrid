<?php

namespace App\Filament\Resources\DockerRepositoryResource\Pages;

use App\Filament\Resources\DockerRepositoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDockerRepository extends ViewRecord
{
    protected static string $resource = DockerRepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
