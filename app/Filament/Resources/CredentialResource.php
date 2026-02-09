<?php

namespace App\Filament\Resources;

use App\Enums\CredentialStatus;
use App\Filament\Resources\CredentialResource\Pages;
use App\Filament\Resources\CredentialResource\RelationManagers;
use App\Models\Credential;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CredentialResource extends Resource
{
    protected static ?string $model = Credential::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.access_control');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('credential.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('credential.model_label_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('credential.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Credential::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $hasFailedCredentials = Credential::where('status', CredentialStatus::Fail)->exists();

        return $hasFailedCredentials ? 'danger' : 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('credential.section.credential'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('provider')
                            ->label(__('credential.field.provider'))
                            ->options(['github' => __('credential.field.provider_github')])
                            ->default('github')
                            ->required()
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('token')
                            ->label(__('credential.field.token'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText(new HtmlString(__('credential.field.token_helper')))
                            ->visibleOn('create'),
                        TextEntry::make('token_notice')
                            ->label(__('credential.field.token'))
                            ->state(__('credential.field.token_hidden'))
                            ->columnSpanFull()
                            ->hiddenOn('create'),
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
                    ->copyMessage(__('credential.notification.name_copied'))
                    ->description(function (Credential $record): HtmlString {
                        $parts = [];

                        // GitHub icon with label
                        $providerLabel = __('credential.field.provider_github');
                        $githubIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>';
                        $parts[] = "<span style=\"display:inline-flex;align-items:center;gap:4px\">{$githubIcon} {$providerLabel}</span>";

                        // Status icon with label - handle all three states
                        $statusColor = match ($record->status) {
                            CredentialStatus::Ok => '#22c55e',
                            CredentialStatus::Fail => '#ef4444',
                            CredentialStatus::Unknown => '#f59e0b',
                        };
                        $statusShortLabel = match ($record->status) {
                            CredentialStatus::Ok => __('credential.status.ok_short'),
                            CredentialStatus::Fail => __('credential.status.fail_short'),
                            CredentialStatus::Unknown => __('credential.status.unknown_short'),
                        };
                        $statusTooltip = match ($record->status) {
                            CredentialStatus::Ok => '',
                            CredentialStatus::Fail => $record->last_error ?: '',
                            CredentialStatus::Unknown => __('credential.status.unknown_tooltip'),
                        };

                        // Check icon for OK
                        $checkIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';

                        // X icon for fail
                        $xIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

                        // Question mark icon for unknown
                        $questionIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

                        $statusIcon = match ($record->status) {
                            CredentialStatus::Ok => $checkIcon,
                            CredentialStatus::Fail => $xIcon,
                            CredentialStatus::Unknown => $questionIcon,
                        };
                        $titleAttr = $statusTooltip ? "title=\"{$statusTooltip}\"" : '';
                        $cursorStyle = $statusTooltip ? 'cursor:help' : '';
                        $parts[] = "<span {$titleAttr} style=\"display:inline-flex;align-items:center;gap:4px;color:{$statusColor};{$cursorStyle}\">{$statusIcon} {$statusShortLabel}</span>";

                        // Last checked time with tooltip
                        if ($record->last_checked_at) {
                            $timeTooltip = __('credential.tooltip.last_checked', ['time' => $record->last_checked_at->diffForHumans()]);
                            $parts[] = "<span title=\"{$timeTooltip}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$record->last_checked_at->diffForHumans()}</span>";
                        }

                        return new HtmlString('<span style="display:inline-flex;align-items:center;vertical-align:middle;gap:8px;flex-wrap:wrap">'.implode('<span style="display:inline-flex;align-items:center;color:#6b7280"> · </span>', $parts).'</span>');
                    }),
                TextColumn::make('provider')
                    ->label(__('credential.field.provider'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->badge()
                    ->formatStateUsing(fn (CredentialStatus $state): string => $state->value)
                    ->color(fn (CredentialStatus $state): string => match ($state) {
                        CredentialStatus::Ok => 'success',
                        CredentialStatus::Fail => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_checked_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('test')
                    ->label(__('credential.action.test'))
                    ->icon('heroicon-o-signal')
                    ->action(function (Credential $record): void {
                        $credential = app(\App\Services\CredentialHealthService::class)->test($record);

                        Notification::make()
                            ->title($credential->status === CredentialStatus::Ok ? __('credential.notification.ok') : __('credential.notification.failed'))
                            ->body($credential->last_error)
                            ->color($credential->status === CredentialStatus::Ok ? 'success' : 'danger')
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RepositoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCredentials::route('/'),
            'create' => Pages\CreateCredential::route('/create'),
            'view' => Pages\ViewCredential::route('/{record}'),
            'edit' => Pages\EditCredential::route('/{record}/edit'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('credential.section.credential'))
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('common.name'))
                            ->icon('heroicon-o-tag')
                            ->copyable()
                            ->copyMessage(__('credential.notification.name_copied')),
                        TextEntry::make('provider')
                            ->label(__('credential.field.provider'))
                            ->icon('heroicon-o-cloud')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('repositories_count')
                            ->label(__('credential.infolist.repositories_using'))
                            ->icon('heroicon-o-archive-box')
                            ->state(fn (Credential $record): int => $record->repositories()->count())
                            ->suffix(fn (Credential $record): string => ' '.trans_choice('credential.infolist.repository_plural', $record->repositories()->count())),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('credential.section.health'))
                    ->icon('heroicon-o-heart')
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('common.status'))
                            ->badge()
                            ->formatStateUsing(fn (CredentialStatus $state): string => $state->value)
                            ->color(fn (CredentialStatus $state): string => match ($state) {
                                CredentialStatus::Ok => 'success',
                                CredentialStatus::Fail => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('last_checked_at')
                            ->label(__('credential.infolist.last_checked'))
                            ->icon('heroicon-o-clock')
                            ->dateTime()
                            ->placeholder(__('common.never')),
                        TextEntry::make('last_error')
                            ->label(__('credential.infolist.last_error'))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->columnSpanFull()
                            ->placeholder(__('common.no_errors'))
                            ->color('danger'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
