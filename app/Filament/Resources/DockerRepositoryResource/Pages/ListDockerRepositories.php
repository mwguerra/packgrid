<?php

namespace App\Filament\Resources\DockerRepositoryResource\Pages;

use App\Filament\Resources\DockerRepositoryResource;
use App\Filament\Resources\DockerRepositoryResource\Widgets\DockerRepositoryStats;
use Filament\Resources\Pages\ListRecords;

class ListDockerRepositories extends ListRecords
{
    protected static string $resource = DockerRepositoryResource::class;

    public function getSubheading(): ?string
    {
        return __('docker_repository.subheading');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DockerRepositoryStats::class,
        ];
    }
}
