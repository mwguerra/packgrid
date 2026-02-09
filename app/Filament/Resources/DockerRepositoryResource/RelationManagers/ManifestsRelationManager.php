<?php

namespace App\Filament\Resources\DockerRepositoryResource\RelationManagers;

use App\Enums\DockerMediaType;
use App\Models\DockerManifest;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManifestsRelationManager extends RelationManager
{
    protected static string $relationship = 'manifests';

    protected static ?string $recordTitleAttribute = 'digest';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('docker_repository.relation.manifests');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('digest')
                    ->label(__('docker_repository.table.digest'))
                    ->formatStateUsing(fn (string $state): string => 'sha256:'.substr(explode(':', $state)[1] ?? '', 0, 12))
                    ->copyable()
                    ->tooltip(fn (DockerManifest $record): string => $record->digest),
                TextColumn::make('media_type')
                    ->label(__('docker_repository.table.type'))
                    ->badge()
                    ->formatStateUsing(fn (DockerMediaType $state): string => $state->label())
                    ->color('gray'),
                TextColumn::make('platform')
                    ->label(__('docker_repository.table.platform'))
                    ->placeholder('-'),
                TextColumn::make('layer_count')
                    ->label(__('docker_repository.table.layers'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('formatted_size')
                    ->label(__('docker_repository.table.size')),
                TextColumn::make('tags')
                    ->label(__('docker_repository.table.tags'))
                    ->state(fn (DockerManifest $record): string => $record->tags->pluck('name')->join(', ') ?: '-'),
                TextColumn::make('created_at')
                    ->label(__('common.created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label(__('docker_repository.action.remove_manifest'))
                    ->requiresConfirmation()
                    ->modalHeading(__('docker_repository.modal.remove_manifest_heading'))
                    ->modalDescription(__('docker_repository.modal.remove_manifest_description')),
            ]);
    }
}
