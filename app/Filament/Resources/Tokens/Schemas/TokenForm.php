<?php

namespace App\Filament\Resources\Tokens\Schemas;

use App\Models\Token;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('token.section.token'))
                    ->description(__('token.section.token_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->placeholder(__('token.field.name_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('token.field.name_helper')),
                        TextInput::make('token')
                            ->label(__('common.token'))
                            ->default(fn () => Token::generateToken())
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->suffixAction(
                                Action::make('copyToken')
                                    ->icon('heroicon-o-clipboard-document')
                                    ->action(function ($state) {
                                        Notification::make()
                                            ->title(__('token.notification.copied'))
                                            ->success()
                                            ->send();
                                    })
                                    ->extraAttributes([
                                        'x-on:click' => 'navigator.clipboard.writeText($wire.data.token)',
                                    ])
                            )
                            ->helperText(__('token.field.token_helper_create'))
                            ->columnSpanFull()
                            ->visibleOn('create'),
                        Placeholder::make('token_notice')
                            ->label(__('common.token'))
                            ->content(__('token.field.token_helper_edit'))
                            ->columnSpanFull()
                            ->hiddenOn('create'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make(__('token.section.restrictions'))
                    ->description(__('token.section.restrictions_description'))
                    ->schema([
                        TagsInput::make('allowed_ips')
                            ->label(__('token.field.allowed_ips'))
                            ->placeholder(__('token.field.allowed_ips_placeholder'))
                            ->helperText(__('token.field.allowed_ips_helper'))
                            ->columnSpanFull(),
                        TagsInput::make('allowed_domains')
                            ->label(__('token.field.allowed_domains'))
                            ->placeholder(__('token.field.allowed_domains_placeholder'))
                            ->helperText(__('token.field.allowed_domains_helper'))
                            ->columnSpanFull(),
                        Select::make('repositories')
                            ->label(__('token.field.allowed_repositories'))
                            ->relationship('repositories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText(__('token.field.allowed_repositories_helper'))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->columnSpanFull(),

                Section::make(__('common.settings'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('common.enabled'))
                            ->default(true)
                            ->helperText(__('token.field.enabled_helper')),
                        DateTimePicker::make('expires_at')
                            ->label(__('token.field.expires_at'))
                            ->helperText(__('token.field.expires_at_helper')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
