<?php

namespace App\Filament\Resources\CredentialResource\RelationManagers;

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Models\Repository;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RepositoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'repositories';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('credential.relation.repositories');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable()
                    ->description(function (Repository $record): HtmlString {
                        $parts = [];

                        $isNpm = $record->format === PackageFormat::Npm;
                        $formatLabel = $record->format->label();

                        $nodeIcon = '<svg width="16" height="16" viewBox="0 0 50 50"><rect x="2" y="2" width="46" height="46" rx="8" ry="8" fill="#68a063"/><text x="25" y="34" font-family="Arial,sans-serif" font-size="22" font-weight="bold" fill="#000" text-anchor="middle">JS</text></svg>';
                        $phpIcon = '<svg width="16" height="16" viewBox="0 0 50 50"><rect x="2" y="2" width="46" height="46" rx="8" ry="8" fill="#777bb3"/><text x="25" y="33" font-family="Arial,sans-serif" font-size="22" font-weight="bold" fill="#000" text-anchor="middle">php</text></svg>';

                        $formatIcon = $isNpm ? $nodeIcon : $phpIcon;
                        $parts[] = "<span title=\"{$formatLabel}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$formatIcon}</span>";

                        $isPrivate = $record->visibility === RepositoryVisibility::PrivateRepo;
                        $visibilityColor = $isPrivate ? '#f59e0b' : '#22c55e';
                        $visibilityLabel = $isPrivate ? __('common.private') : __('common.public');

                        $globeIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
                        $lockIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

                        $visibilityIcon = $isPrivate ? $lockIcon : $globeIcon;
                        $parts[] = "<span title=\"{$visibilityLabel}\" style=\"display:inline-flex;align-items:center;color:{$visibilityColor};cursor:help\">{$visibilityIcon}</span>";

                        if ($record->last_sync_at) {
                            $syncTooltip = __('repository.tooltip.last_sync_attempt', ['time' => $record->last_sync_at->diffForHumans()]);
                            $parts[] = "<span title=\"{$syncTooltip}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$record->last_sync_at->diffForHumans()}</span>";
                        }

                        return new HtmlString('<span style="display:inline-flex;align-items:center;vertical-align:middle;gap:8px;flex-wrap:wrap">'.implode('<span style="display:inline-flex;align-items:center;color:#6b7280"> · </span>', $parts).'</span>');
                    }),
                TextColumn::make('sync_status')
                    ->label(__('repository.table.status'))
                    ->badge()
                    ->state(function (Repository $record): string {
                        if ($record->last_error) {
                            return 'error';
                        }
                        if (! $record->last_sync_at) {
                            return 'pending';
                        }
                        if ($record->last_sync_at->lt(now()->subDay())) {
                            return 'stale';
                        }

                        return 'synced';
                    })
                    ->formatStateUsing(fn (string $state): string => __("repository.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'synced' => 'success',
                        'stale' => 'warning',
                        'error' => 'danger',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (Repository $record): ?string => $record->last_error ?: null),
                ToggleColumn::make('enabled')
                    ->label(__('common.enabled')),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Repository $record): string => route('filament.admin.resources.repositories.view', $record)),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }
}
