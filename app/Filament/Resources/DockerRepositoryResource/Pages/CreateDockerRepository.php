<?php

namespace App\Filament\Resources\DockerRepositoryResource\Pages;

use App\Filament\Resources\DockerRepositoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDockerRepository extends CreateRecord
{
    protected static string $resource = DockerRepositoryResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
