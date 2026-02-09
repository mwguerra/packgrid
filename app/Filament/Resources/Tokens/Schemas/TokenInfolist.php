<?php

namespace App\Filament\Resources\Tokens\Schemas;

use App\Models\Token;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TokenInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('token.section.token'))
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('common.name'))
                            ->icon('heroicon-o-tag')
                            ->copyable()
                            ->copyMessage(__('token.notification.name_copied')),
                        TextEntry::make('status')
                            ->label(__('common.status'))
                            ->badge()
                            ->state(function (Token $record): string {
                                if (! $record->enabled) {
                                    return __('common.disabled');
                                }
                                if ($record->expires_at?->isPast()) {
                                    return __('common.expired');
                                }

                                return __('common.active');
                            })
                            ->color(fn (string $state): string => match ($state) {
                                __('common.active') => 'success',
                                __('common.disabled') => 'gray',
                                __('common.expired') => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('token')
                            ->label(__('common.token'))
                            ->icon('heroicon-o-clipboard-document')
                            ->copyable()
                            ->copyMessage(__('token.notification.copied'))
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('token.section.lifecycle'))
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('common.created'))
                            ->icon('heroicon-o-calendar')
                            ->dateTime(),
                        TextEntry::make('expires_at')
                            ->label(__('common.expires'))
                            ->icon('heroicon-o-calendar-days')
                            ->dateTime()
                            ->placeholder(__('common.never')),
                        TextEntry::make('last_used_at')
                            ->label(__('common.last_used'))
                            ->icon('heroicon-o-arrow-path')
                            ->dateTime()
                            ->placeholder(__('common.never')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('token.section.restrictions'))
                    ->icon('heroicon-o-shield-check')
                    ->description(__('token.section.restrictions_description'))
                    ->schema([
                        TextEntry::make('allowed_ips')
                            ->label(__('token.field.allowed_ips'))
                            ->icon('heroicon-o-server')
                            ->badge()
                            ->separator(',')
                            ->color('warning')
                            ->placeholder(__('token.field.allowed_ips_empty')),
                        TextEntry::make('allowed_domains')
                            ->label(__('token.field.allowed_domains'))
                            ->icon('heroicon-o-globe-alt')
                            ->badge()
                            ->separator(',')
                            ->color('warning')
                            ->placeholder(__('token.field.allowed_domains_empty')),
                        TextEntry::make('repositories.name')
                            ->label(__('token.field.allowed_repositories'))
                            ->icon('heroicon-o-rectangle-stack')
                            ->badge()
                            ->color('info')
                            ->placeholder(__('token.field.allowed_repositories_empty')),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
