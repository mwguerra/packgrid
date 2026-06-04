<?php

namespace App\Filament\Widgets;

use App\Enums\DockerActivityType;
use App\Models\DockerActivity;
use App\Support\PackgridSettings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentDockerActivity extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PackgridSettings::dockerEnabled();
    }

    public function getHeading(): ?string
    {
        return __('widget.docker_activity.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DockerActivity::query()
                    ->with('repository')
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('repository.name')
                    ->label(__('widget.docker_activity.repository'))
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label(__('widget.docker_activity.type'))
                    ->badge()
                    ->formatStateUsing(fn (DockerActivityType $state): string => $state->label())
                    ->icon(fn (DockerActivityType $state): string => $state->icon())
                    ->color(fn (DockerActivityType $state): string => $state->color()),

                TextColumn::make('tag')
                    ->label(__('widget.docker_activity.reference'))
                    ->formatStateUsing(fn (?string $state, DockerActivity $record): string => $state ?: ($record->short_digest ?? '—')),

                TextColumn::make('size')
                    ->label(__('widget.docker_activity.size'))
                    ->formatStateUsing(fn (?int $state, DockerActivity $record): string => $state ? $record->formatted_size : '—'),

                TextColumn::make('created_at')
                    ->label(__('widget.docker_activity.when'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->paginated(false);
    }
}
