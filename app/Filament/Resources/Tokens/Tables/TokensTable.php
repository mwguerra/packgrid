<?php

namespace App\Filament\Resources\Tokens\Tables;

use App\Models\Token;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyableState(fn (Token $record): string => $record->token)
                    ->copyMessage(__('token.notification.copied'))
                    ->description(function (Token $record): HtmlString {
                        $parts = [];

                        // Status icon with tooltip
                        $isActive = $record->enabled && ! $record->expires_at?->isPast();
                        $isExpired = $record->expires_at?->isPast();
                        $isDisabled = ! $record->enabled;

                        if ($isDisabled) {
                            $statusColor = '#6b7280';
                            $statusLabel = __('common.disabled');
                            // Pause icon for disabled
                            $statusIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>';
                        } elseif ($isExpired) {
                            $statusColor = '#ef4444';
                            $statusLabel = __('common.expired');
                            // Clock icon for expired
                            $statusIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                        } else {
                            $statusColor = '#22c55e';
                            $statusLabel = __('common.active');
                            // Check icon for active
                            $statusIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                        }
                        $parts[] = "<span title=\"{$statusLabel}\" style=\"display:inline-flex;align-items:center;color:{$statusColor};cursor:help\">{$statusIcon}</span>";

                        // Restrictions icons
                        $hasIps = ! empty($record->allowed_ips);
                        $hasDomains = ! empty($record->allowed_domains);

                        if ($hasIps) {
                            $ipCount = count($record->allowed_ips);
                            $ipLabel = __('token.table.restrictions_ips', ['count' => $ipCount]);
                            // Server/IP icon
                            $ipIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>';
                            $parts[] = "<span title=\"{$ipLabel}\" style=\"display:inline-flex;align-items:center;color:#f59e0b;cursor:help\">{$ipIcon}</span>";
                        }

                        if ($hasDomains) {
                            $domainCount = count($record->allowed_domains);
                            $domainLabel = __('token.table.restrictions_domains', ['count' => $domainCount]);
                            // Globe icon for domains
                            $domainIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
                            $parts[] = "<span title=\"{$domainLabel}\" style=\"display:inline-flex;align-items:center;color:#f59e0b;cursor:help\">{$domainIcon}</span>";
                        }

                        // Last used time
                        if ($record->last_used_at) {
                            $lastUsedTooltip = __('token.tooltip.last_used', ['time' => $record->last_used_at->diffForHumans()]);
                            $parts[] = "<span title=\"{$lastUsedTooltip}\" style=\"display:inline-flex;align-items:center;cursor:help\">{$record->last_used_at->diffForHumans()}</span>";
                        }

                        return new HtmlString('<span style="display:inline-flex;align-items:center;vertical-align:middle;gap:8px;flex-wrap:wrap">'.implode('<span style="display:inline-flex;align-items:center;color:#6b7280"> · </span>', $parts).'</span>');
                    }),
                TextColumn::make('status')
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
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('restrictions')
                    ->label(__('token.table.restrictions'))
                    ->state(function (Token $record): string {
                        $restrictions = [];
                        if (! empty($record->allowed_ips)) {
                            $restrictions[] = __('token.table.restrictions_ips', ['count' => count($record->allowed_ips)]);
                        }
                        if (! empty($record->allowed_domains)) {
                            $restrictions[] = __('token.table.restrictions_domains', ['count' => count($record->allowed_domains)]);
                        }

                        return $restrictions ? implode(', ', $restrictions) : __('common.none');
                    })
                    ->color(fn (string $state): string => $state === __('common.none') ? 'gray' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_used_at')
                    ->label(__('common.last_used'))
                    ->since()
                    ->sortable()
                    ->placeholder(__('common.never'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')
                    ->label(__('common.expires'))
                    ->since()
                    ->sortable()
                    ->placeholder(__('common.never'))
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('enabled')
                    ->label(__('common.enabled')),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->label(__('common.status'))
                    ->trueLabel(__('common.enabled'))
                    ->falseLabel(__('common.disabled')),
            ])
            ->recordActions([
                Action::make('copyToken')
                    ->label(__('token.action.copy'))
                    ->icon('heroicon-o-clipboard-document')
                    ->action(function (Token $record): void {
                        Notification::make()
                            ->title(__('token.notification.copied'))
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn (Token $record): array => [
                        'x-on:click' => 'navigator.clipboard.writeText('.json_encode($record->token).')',
                    ]),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->separator(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
