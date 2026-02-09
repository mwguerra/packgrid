<?php

namespace App\Filament\Resources;

use App\Enums\DockerActivityType;
use App\Enums\RepositoryVisibility;
use App\Filament\Resources\DockerRepositoryResource\Pages;
use App\Filament\Resources\DockerRepositoryResource\RelationManagers;
use App\Models\DockerRepository;
use App\Support\PackgridSettings;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DockerRepositoryResource extends Resource
{
    protected static ?string $model = DockerRepository::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.packages');
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool
    {
        return PackgridSettings::dockerEnabled();
    }

    public static function getModelLabel(): string
    {
        return __('docker_repository.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('docker_repository.model_label_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('docker_repository.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DockerRepository::where('enabled', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('docker_repository.section.repository'))
                    ->description(__('docker_repository.section.repository_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('docker_repository.field.name'))
                            ->placeholder(__('docker_repository.field.name_placeholder'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+([._\/-][a-z0-9]+)*$/'])
                            ->helperText(__('docker_repository.field.name_helper'))
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('docker_repository.field.description'))
                            ->placeholder(__('docker_repository.field.description_placeholder'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('docker_repository.section.access'))
                    ->description(__('docker_repository.section.access_description'))
                    ->schema([
                        Select::make('visibility')
                            ->label(__('docker_repository.field.visibility'))
                            ->options([
                                RepositoryVisibility::PublicRepo->value => __('common.public'),
                                RepositoryVisibility::PrivateRepo->value => __('common.private'),
                            ])
                            ->required()
                            ->default(RepositoryVisibility::PrivateRepo->value)
                            ->helperText(__('docker_repository.field.visibility_helper')),
                        Toggle::make('enabled')
                            ->label(__('common.enabled'))
                            ->helperText(__('docker_repository.field.enabled_helper'))
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
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
                    ->copyMessage(__('docker_repository.notification.name_copied'))
                    ->description(function (DockerRepository $record): HtmlString {
                        $parts = [];

                        // Docker icon
                        $dockerIcon = '<svg width="20" height="20" viewBox="0 0 50 50"><rect x="2" y="2" width="46" height="46" rx="8" ry="8" fill="#2496ed"/><text x="25" y="34" font-family="Arial,sans-serif" font-size="14" font-weight="bold" fill="#fff" text-anchor="middle">D</text></svg>';
                        $parts[] = "<span title=\"Docker\" style=\"display:inline-flex;align-items:center;cursor:help\">{$dockerIcon}</span>";

                        // Visibility icon
                        $isPrivate = $record->visibility === RepositoryVisibility::PrivateRepo;
                        $visibilityColor = $isPrivate ? '#f59e0b' : '#22c55e';
                        $visibilityLabel = $isPrivate ? __('common.private') : __('common.public');

                        $globeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
                        $lockIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

                        $visibilityIcon = $isPrivate ? $lockIcon : $globeIcon;
                        $parts[] = "<span title=\"{$visibilityLabel}\" style=\"display:inline-flex;align-items:center;color:{$visibilityColor};cursor:help\">{$visibilityIcon}</span>";

                        // Last push time
                        if ($record->last_push_at) {
                            $pushTooltip = __('docker_repository.tooltip.last_push', ['time' => $record->last_push_at->diffForHumans()]);
                            $parts[] = "<span title=\"{$pushTooltip}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$record->last_push_at->diffForHumans()}</span>";
                        }

                        return new HtmlString('<span style="display:inline-flex;align-items:center;vertical-align:middle;gap:8px;flex-wrap:wrap">'.implode('<span style="display:inline-flex;align-items:center;color:#6b7280"> · </span>', $parts).'</span>');
                    }),
                TextColumn::make('download_count')
                    ->label(__('docker_repository.table.downloads'))
                    ->sortable()
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->default(0),
                TextColumn::make('tag_count')
                    ->label(__('docker_repository.table.tags'))
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('formatted_size')
                    ->label(__('docker_repository.table.size'))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('total_size', $direction)),
                TextColumn::make('pull_count')
                    ->label(__('docker_repository.table.pulls'))
                    ->sortable()
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('push_count')
                    ->label(__('docker_repository.table.pushes'))
                    ->sortable()
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_push_at')
                    ->label(__('docker_repository.table.last_push'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('enabled')
                    ->label(__('common.enabled')),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->label(__('docker_repository.field.visibility'))
                    ->options([
                        RepositoryVisibility::PublicRepo->value => __('common.public'),
                        RepositoryVisibility::PrivateRepo->value => __('common.private'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make()
                        ->label(__('docker_repository.action.remove'))
                        ->requiresConfirmation()
                        ->modalHeading(__('docker_repository.modal.remove_heading'))
                        ->modalDescription(__('docker_repository.modal.remove_description'))
                        ->modalSubmitActionLabel(__('docker_repository.action.remove')),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TagsRelationManager::class,
            RelationManagers\ManifestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDockerRepositories::route('/'),
            'view' => Pages\ViewDockerRepository::route('/{record}'),
            'edit' => Pages\EditDockerRepository::route('/{record}/edit'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('docker_repository.section.repository'))
                    ->icon('heroicon-o-cube-transparent')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('common.name'))
                            ->icon('heroicon-o-tag')
                            ->copyable()
                            ->copyMessage(__('docker_repository.notification.name_copied')),
                        TextEntry::make('visibility')
                            ->label(__('docker_repository.field.visibility'))
                            ->icon(fn (DockerRepository $record): string => $record->visibility === RepositoryVisibility::PrivateRepo ? 'heroicon-o-lock-closed' : 'heroicon-o-globe-alt')
                            ->badge()
                            ->formatStateUsing(fn (RepositoryVisibility $state): string => $state === RepositoryVisibility::PrivateRepo ? __('common.private') : __('common.public'))
                            ->color(fn (RepositoryVisibility $state): string => $state === RepositoryVisibility::PrivateRepo ? 'warning' : 'success'),
                        TextEntry::make('description')
                            ->label(__('docker_repository.field.description'))
                            ->placeholder(__('common.none'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('docker_repository.section.statistics'))
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        TextEntry::make('tag_count')
                            ->label(__('docker_repository.infolist.tags'))
                            ->icon('heroicon-o-tag')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('manifest_count')
                            ->label(__('docker_repository.infolist.manifests'))
                            ->icon('heroicon-o-document-text')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('formatted_size')
                            ->label(__('docker_repository.infolist.total_size'))
                            ->icon('heroicon-o-server'),
                        TextEntry::make('pull_count')
                            ->label(__('docker_repository.infolist.pulls'))
                            ->icon('heroicon-o-arrow-down-tray'),
                        TextEntry::make('push_count')
                            ->label(__('docker_repository.infolist.pushes'))
                            ->icon('heroicon-o-arrow-up-tray'),
                        TextEntry::make('last_push_at')
                            ->label(__('docker_repository.infolist.last_push'))
                            ->icon('heroicon-o-clock')
                            ->dateTime()
                            ->placeholder(__('common.never')),
                        TextEntry::make('last_pull_at')
                            ->label(__('docker_repository.infolist.last_pull'))
                            ->icon('heroicon-o-clock')
                            ->dateTime()
                            ->placeholder(__('common.never')),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('docker_repository.section.recent_activity'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->state(fn (DockerRepository $record) => $record->activities()->latest()->limit(10)->get())
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('docker_repository.infolist.type'))
                                    ->badge()
                                    ->formatStateUsing(fn (DockerActivityType $state): string => $state->label())
                                    ->icon(fn (DockerActivityType $state): string => $state->icon())
                                    ->color(fn (DockerActivityType $state): string => $state->color()),
                                TextEntry::make('tag')
                                    ->label(__('docker_repository.infolist.tag'))
                                    ->placeholder('-'),
                                TextEntry::make('short_digest')
                                    ->label(__('docker_repository.infolist.digest'))
                                    ->placeholder('-'),
                                TextEntry::make('created_at')
                                    ->label(__('common.date'))
                                    ->dateTime(),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
