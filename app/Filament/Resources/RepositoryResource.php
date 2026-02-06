<?php

namespace App\Filament\Resources;

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Enums\SyncStatus;
use App\Filament\Resources\RepositoryResource\Pages;
use App\Models\Repository;
use App\Services\RepositorySyncService;
use App\Support\PackgridSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.packages');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool
    {
        return PackgridSettings::repositoriesEnabled();
    }

    public static function getModelLabel(): string
    {
        return __('repository.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('repository.model_label_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('repository.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Repository::where('enabled', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $hasErrorRepositories = Repository::where('enabled', true)
            ->whereNotNull('last_error')
            ->where('last_error', '!=', '')
            ->exists();

        return $hasErrorRepositories ? 'danger' : 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('repository.section.repository'))
                    ->description(__('repository.section.repository_description'))
                    ->schema([
                        TextInput::make('url')
                            ->label(__('repository.field.url'))
                            ->placeholder(__('repository.field.url_placeholder'))
                            ->required()
                            ->helperText(__('repository.field.url_helper'))
                            ->columnSpanFull(),
                        Select::make('format')
                            ->label(__('repository.field.format'))
                            ->options(PackgridSettings::getEnabledFormats())
                            ->required()
                            ->default(PackgridSettings::getDefaultFormat())
                            ->helperText(__('repository.field.format_helper'))
                            ->hiddenOn('create')
                            ->visible(fn (): bool => PackgridSettings::hasMultipleFormats()),
                    ])
                    ->columnSpanFull(),

                Section::make(__('repository.section.access'))
                    ->description(fn (string $operation): string => $operation === 'create'
                        ? __('repository.section.access_description_create')
                        : __('repository.section.access_description'))
                    ->schema([
                        Select::make('visibility')
                            ->label(__('repository.field.visibility'))
                            ->options([
                                RepositoryVisibility::PublicRepo->value => __('common.public'),
                                RepositoryVisibility::PrivateRepo->value => __('common.private'),
                            ])
                            ->required()
                            ->default(RepositoryVisibility::PublicRepo->value)
                            ->live()
                            ->helperText(__('repository.field.visibility_helper'))
                            ->hiddenOn('create'),
                        Select::make('credential_id')
                            ->label(__('repository.field.credential'))
                            ->relationship('credential', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get, string $operation): bool => $operation === 'edit' && $get('visibility') === RepositoryVisibility::PrivateRepo->value)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' || $get('visibility') === RepositoryVisibility::PrivateRepo->value)
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? __('repository.field.credential_helper_create')
                                : __('repository.field.credential_helper')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('repository.section.sync_options'))
                    ->description(__('repository.section.sync_options_description'))
                    ->schema([
                        TextInput::make('ref_filter')
                            ->label(__('repository.field.refs_filter'))
                            ->placeholder(__('repository.field.refs_filter_placeholder'))
                            ->helperText(__('repository.field.refs_filter_helper'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('enabled')
                            ->label(__('common.enabled'))
                            ->helperText(__('repository.field.enabled_helper'))
                            ->default(true),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->columnSpanFull(),

                Hidden::make('repo_full_name'),
                Hidden::make('name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyableState(fn (Repository $record): string => $record->url)
                    ->copyMessage(__('repository.notification.url_copied'))
                    ->url(null)
                    ->description(function (Repository $record): HtmlString {
                        $parts = [];

                        // Format icon with tooltip
                        $isNpm = $record->format === PackageFormat::Npm;
                        $formatLabel = $record->format->label();

                        // Node.js icon (rounded square with JS text)
                        $nodeIcon = '<svg width="20" height="20" viewBox="0 0 50 50"><rect x="2" y="2" width="46" height="46" rx="8" ry="8" fill="#68a063"/><text x="25" y="34" font-family="Arial,sans-serif" font-size="22" font-weight="bold" fill="#000" text-anchor="middle">JS</text></svg>';

                        // PHP logo (based on official php.net logo)
                        $phpIcon = '<svg width="20" height="20" viewBox="0 0 50 50"><rect x="2" y="2" width="46" height="46" rx="8" ry="8" fill="#777bb3"/><text x="25" y="33" font-family="Arial,sans-serif" font-size="22" font-weight="bold" fill="#000" text-anchor="middle">php</text></svg>';

                        $formatIcon = $isNpm ? $nodeIcon : $phpIcon;
                        $parts[] = "<span title=\"{$formatLabel}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$formatIcon}</span>";

                        // Visibility icon with tooltip
                        $isPrivate = $record->visibility === RepositoryVisibility::PrivateRepo;
                        $visibilityColor = $isPrivate ? '#f59e0b' : '#22c55e';
                        $visibilityLabel = $isPrivate ? __('common.private') : __('common.public');

                        // Globe icon for public
                        $globeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';

                        // Lock icon for private
                        $lockIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

                        $visibilityIcon = $isPrivate ? $lockIcon : $globeIcon;
                        $parts[] = "<span title=\"{$visibilityLabel}\" style=\"display:inline-flex;align-items:center;color:{$visibilityColor};cursor:help\">{$visibilityIcon}</span>";

                        // Last sync time
                        if ($record->last_sync_at) {
                            $syncTooltip = __('repository.tooltip.last_sync_attempt', ['time' => $record->last_sync_at->diffForHumans()]);
                            $parts[] = "<span title=\"{$syncTooltip}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$record->last_sync_at->diffForHumans()}</span>";
                        }

                        return new HtmlString('<span style="display:inline-flex;align-items:center;vertical-align:middle;gap:8px;flex-wrap:wrap">'.implode('<span style="display:inline-flex;align-items:center;color:#6b7280"> · </span>', $parts).'</span>');
                    }),
                TextColumn::make('download_count')
                    ->label(__('repository.table.downloads'))
                    ->sortable()
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->default(0),
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
                TextColumn::make('repo_full_name')
                    ->label(__('repository.section.repository'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('credential.name')
                    ->label(__('repository.field.credential'))
                    ->placeholder(__('common.none'))
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('enabled')
                    ->label(__('common.enabled')),
            ])
            ->filters([
                SelectFilter::make('format')
                    ->label(__('repository.field.format'))
                    ->options(PackgridSettings::getEnabledFormats())
                    ->visible(fn (): bool => PackgridSettings::hasMultipleFormats()),
                SelectFilter::make('visibility')
                    ->label(__('repository.field.visibility'))
                    ->options([
                        RepositoryVisibility::PublicRepo->value => __('common.public'),
                        RepositoryVisibility::PrivateRepo->value => __('common.private'),
                    ]),
                SelectFilter::make('credential')
                    ->label(__('repository.field.credential'))
                    ->relationship('credential', 'name'),
            ])
            ->recordActions([
                Action::make('download_logs')
                    ->label(__('repository.action.download_logs'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->modalHeading(__('repository.modal.download_logs_heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-actions::modal.actions.close.label'))
                    ->infolist(function (Repository $record): array {
                        $logs = $record->downloadLogs()->with('token')->latest()->limit(50)->get();

                        if ($logs->isEmpty()) {
                            return [
                                TextEntry::make('no_logs')
                                    ->label('')
                                    ->state(__('repository.download_log.no_logs'))
                                    ->columnSpanFull(),
                            ];
                        }

                        return [
                            RepeatableEntry::make('downloadLogs')
                                ->label('')
                                ->state($logs)
                                ->schema([
                                    TextEntry::make('package_version')
                                        ->label(__('repository.download_log.version')),
                                    TextEntry::make('format')
                                        ->label(__('repository.download_log.format'))
                                        ->badge()
                                        ->formatStateUsing(fn (PackageFormat $state): string => $state->label())
                                        ->color(fn (PackageFormat $state): string => $state === PackageFormat::Npm ? 'info' : 'success'),
                                    TextEntry::make('token.name')
                                        ->label(__('repository.download_log.token'))
                                        ->placeholder(__('common.none')),
                                    TextEntry::make('client_ip')
                                        ->label(__('repository.download_log.ip')),
                                    TextEntry::make('created_at')
                                        ->label(__('common.date'))
                                        ->dateTime(),
                                ])
                                ->columns(5),
                        ];
                    }),
                Action::make('sync')
                    ->label(fn (Repository $record): string => $record->last_error
                        ? __('repository.action.retry_sync')
                        : __('repository.action.sync'))
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip(__('repository.action.sync_tooltip'))
                    ->color(fn (Repository $record): string => $record->last_error ? 'danger' : 'primary')
                    ->action(function (Repository $record): void {
                        try {
                            app(RepositorySyncService::class)->sync($record);
                            Notification::make()
                                ->title(__('repository.notification.synced'))
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title(__('repository.notification.sync_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make()
                        ->label(__('repository.action.remove'))
                        ->requiresConfirmation()
                        ->modalHeading(__('repository.modal.remove_heading'))
                        ->modalDescription(__('repository.modal.remove_description'))
                        ->modalSubmitActionLabel(__('repository.action.remove')),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepositories::route('/'),
            'create' => Pages\CreateRepository::route('/create'),
            'view' => Pages\ViewRepository::route('/{record}'),
            'edit' => Pages\EditRepository::route('/{record}/edit'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('repository.section.repository'))
                    ->icon('heroicon-o-archive-box')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('common.name'))
                            ->icon('heroicon-o-tag')
                            ->copyable()
                            ->copyMessage(__('repository.notification.name_copied')),
                        TextEntry::make('repo_full_name')
                            ->label(__('repository.infolist.github_repo'))
                            ->icon('heroicon-o-code-bracket')
                            ->copyable()
                            ->url(fn (Repository $record): string => "https://github.com/{$record->repo_full_name}", shouldOpenInNewTab: true),
                        TextEntry::make('format')
                            ->label(__('repository.field.format'))
                            ->icon('heroicon-o-cube')
                            ->badge()
                            ->formatStateUsing(fn (PackageFormat $state): string => $state->label())
                            ->color(fn (PackageFormat $state): string => $state === PackageFormat::Npm ? 'info' : 'success'),
                        TextEntry::make('package_count')
                            ->label(__('repository.infolist.packages'))
                            ->icon('heroicon-o-square-3-stack-3d')
                            ->suffix(fn (Repository $record): string => ' '.trans_choice('repository.infolist.package_plural', $record->package_count ?? 0)),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('repository.section.access'))
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextEntry::make('visibility')
                            ->label(__('repository.field.visibility'))
                            ->icon(fn (Repository $record): string => $record->visibility === RepositoryVisibility::PrivateRepo ? 'heroicon-o-lock-closed' : 'heroicon-o-globe-alt')
                            ->badge()
                            ->formatStateUsing(fn (RepositoryVisibility $state): string => $state === RepositoryVisibility::PrivateRepo ? __('common.private') : __('common.public'))
                            ->color(fn (RepositoryVisibility $state): string => $state === RepositoryVisibility::PrivateRepo ? 'warning' : 'success'),
                        TextEntry::make('credential.name')
                            ->label(__('repository.field.credential'))
                            ->icon('heroicon-o-key')
                            ->placeholder(__('common.none'))
                            ->url(fn (Repository $record): ?string => $record->credential_id ? route('filament.admin.resources.credentials.view', $record->credential_id) : null),
                        TextEntry::make('credential.status')
                            ->label(__('repository.infolist.credential_status'))
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state?->value ?? __('repository.infolist.unknown'))
                            ->color(fn ($state): string => match ($state?->value ?? null) {
                                'ok' => 'success',
                                'fail' => 'danger',
                                default => 'gray',
                            })
                            ->placeholder(__('repository.infolist.unknown')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('repository.section.sync_status'))
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        TextEntry::make('sync_status')
                            ->label(__('common.status'))
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
                            }),
                        TextEntry::make('last_sync_at')
                            ->label(__('repository.table.last_sync'))
                            ->icon('heroicon-o-clock')
                            ->dateTime()
                            ->placeholder(__('common.never')),
                        TextEntry::make('last_error')
                            ->label(__('repository.infolist.last_error'))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->columnSpanFull()
                            ->placeholder(__('common.no_errors'))
                            ->color('danger'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('repository.section.recent_syncs'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        RepeatableEntry::make('syncLogs')
                            ->label('')
                            ->state(fn (Repository $record) => $record->syncLogs()->latest('started_at')->limit(10)->get())
                            ->schema([
                                TextEntry::make('status')
                                    ->label(__('common.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (SyncStatus $state): string => $state->value)
                                    ->color(fn (SyncStatus $state): string => match ($state) {
                                        SyncStatus::Success => 'success',
                                        SyncStatus::Fail => 'danger',
                                    }),
                                TextEntry::make('started_at')
                                    ->label(__('repository.infolist.started'))
                                    ->dateTime(),
                                TextEntry::make('finished_at')
                                    ->label(__('repository.infolist.finished'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('error')
                                    ->label(__('repository.infolist.error'))
                                    ->placeholder(__('common.none'))
                                    ->color('danger'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
