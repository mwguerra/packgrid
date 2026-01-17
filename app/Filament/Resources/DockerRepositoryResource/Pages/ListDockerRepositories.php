<?php

namespace App\Filament\Resources\DockerRepositoryResource\Pages;

use App\Filament\Resources\DockerRepositoryResource;
use Filament\Resources\Pages\ListRecords;

class ListDockerRepositories extends ListRecords
{
    protected static string $resource = DockerRepositoryResource::class;

    public function getSubheading(): ?string
    {
        return __('docker_repository.subheading');
    }
}
