<?php

namespace App\Filament\Resources\DockerRepositoryResource\RelationManagers;

use App\Models\DockerTag;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('docker_repository.relation.tags');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('docker_repository.table.tag'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('manifest.digest')
                    ->label(__('docker_repository.table.digest'))
                    ->formatStateUsing(fn (?string $state): string => $state ? 'sha256:'.substr(explode(':', $state)[1] ?? '', 0, 12) : '-')
                    ->copyable()
                    ->copyableState(fn (DockerTag $record): ?string => $record->manifest?->digest),
                TextColumn::make('manifest.platform')
                    ->label(__('docker_repository.table.platform'))
                    ->placeholder('-'),
                TextColumn::make('manifest.formatted_size')
                    ->label(__('docker_repository.table.size')),
                TextColumn::make('updated_at')
                    ->label(__('common.updated'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                DeleteAction::make()
                    ->label(__('docker_repository.action.remove_tag'))
                    ->requiresConfirmation()
                    ->modalHeading(__('docker_repository.modal.remove_tag_heading'))
                    ->modalDescription(__('docker_repository.modal.remove_tag_description')),
            ]);
    }
}
