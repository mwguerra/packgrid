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
    private static function getHost(): string
    {
        $url = config('app.url');
        $host = parse_url($url, PHP_URL_HOST) ?: 'packgrid.mwguerra.com';
        $port = parse_url($url, PHP_URL_PORT);

        return $host.($port ? ':'.$port : '');
    }

    private static function getAuthJsonContent(string $token): string
    {
        return json_encode([
            'http-basic' => [
                self::getHost() => [
                    'username' => 'composer',
                    'password' => $token,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function getNpmrcContent(string $token): string
    {
        return '//'.self::getHost().'/:_authToken='.$token;
    }

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

                        $hasRepositories = $record->repositories()->count() > 0;
                        if ($hasRepositories) {
                            $repoCount = $record->repositories()->count();
                            $repoLabel = __('token.table.restrictions_repos', ['count' => $repoCount]);
                            $repoIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>';
                            $parts[] = "<span title=\"{$repoLabel}\" style=\"display:inline-flex;align-items:center;color:#3b82f6;cursor:help\">{$repoIcon}</span>";
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
                        if ($record->repositories()->count() > 0) {
                            $restrictions[] = __('token.table.restrictions_repos', ['count' => $record->repositories()->count()]);
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
                        $token = $record->token;

                        Notification::make()
                            ->title(__('token.notification.copied'))
                            ->body(new HtmlString('<pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs font-mono overflow-x-auto"><code>'.e($token).'</code></pre>'))
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn (Token $record): array => [
                        'x-on:click' => 'navigator.clipboard.writeText('.json_encode($record->token).')',
                    ]),
                Action::make('copyAuthJson')
                    ->label(__('token.action.copy_auth_json'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->action(function (Token $record): void {
                        $authJson = self::getAuthJsonContent($record->token);

                        Notification::make()
                            ->title(__('token.notification.auth_json_copied'))
                            ->body(new HtmlString('<pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs font-mono overflow-x-auto"><code>'.e($authJson).'</code></pre>'))
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn (Token $record): array => [
                        'x-on:click' => 'navigator.clipboard.writeText('.json_encode(self::getAuthJsonContent($record->token)).')',
                    ]),
                Action::make('copyNpmrc')
                    ->label(__('token.action.copy_npmrc'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->action(function (Token $record): void {
                        $npmrc = self::getNpmrcContent($record->token);

                        Notification::make()
                            ->title(__('token.notification.npmrc_copied'))
                            ->body(new HtmlString('<pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs font-mono overflow-x-auto"><code>'.e($npmrc).'</code></pre>'))
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn (Token $record): array => [
                        'x-on:click' => 'navigator.clipboard.writeText('.json_encode(self::getNpmrcContent($record->token)).')',
                    ]),
                ActionGroup::make([
                    ActionGroup::make([
                        ViewAction::make(),
                        EditAction::make(),
                    ])->dropdown(false),
                    DeleteAction::make(),
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
