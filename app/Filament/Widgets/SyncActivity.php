<?php

namespace App\Filament\Widgets;

use App\Enums\SyncStatus;
use App\Models\SyncLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SyncActivity extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('widget.sync_activity.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SyncLog::query()
                    ->with(['repository', 'repository.credential'])
                    ->latest('started_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('repository.name')
                    ->label(__('widget.sync_activity.column.repository'))
                    ->searchable()
                    ->url(fn (SyncLog $record): string => route('filament.admin.resources.repositories.view', $record->repository_id)),

                TextColumn::make('result')
                    ->label(__('widget.sync_activity.column.result'))
                    ->state(fn (SyncLog $record): string => $this->getResultMessage($record))
                    ->icon(fn (SyncLog $record): string => $record->status === SyncStatus::Success
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (SyncLog $record): string => $record->status === SyncStatus::Success
                        ? 'success'
                        : 'danger'),

                TextColumn::make('synced_refs')
                    ->label(__('widget.sync_activity.column.refs'))
                    ->state(fn (SyncLog $record): string => $this->getRefsDisplay($record))
                    ->html()
                    ->visible(fn (?SyncLog $record): bool => $record?->status === SyncStatus::Success),

                TextColumn::make('action')
                    ->label(__('widget.sync_activity.column.action'))
                    ->state(fn (SyncLog $record): string => $this->getSuggestedAction($record))
                    ->icon(fn (SyncLog $record): ?string => $this->getActionIcon($record))
                    ->iconColor(fn (SyncLog $record): ?string => $this->getActionIconColor($record))
                    ->color(fn (SyncLog $record): ?string => $record->status === SyncStatus::Fail
                        ? 'warning'
                        : 'gray')
                    ->url(fn (SyncLog $record): ?string => $this->getActionUrl($record))
                    ->openUrlInNewTab(fn (SyncLog $record): bool => $this->isExternalAction($record)),

                TextColumn::make('started_at')
                    ->label(__('widget.sync_activity.column.when'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated(false);
    }

    protected function getResultMessage(SyncLog $record): string
    {
        if ($record->status === SyncStatus::Success) {
            $packageCount = $record->repository?->package_count ?? 0;

            return __('widget.sync_activity.result.success', ['count' => $packageCount]);
        }

        $error = $record->error ?? '';

        return match (true) {
            str_contains($error, 'No composer.json') => __('widget.sync_activity.result.no_composer'),
            str_contains($error, 'No package.json') => __('widget.sync_activity.result.no_package_json'),
            str_contains($error, 'composer.json') || str_contains($error, 'package.json') => __('widget.sync_activity.result.invalid_manifest'),
            str_contains($error, '404') || str_contains($error, 'Not Found') => __('widget.sync_activity.result.not_found'),
            str_contains($error, '401') || str_contains($error, 'Unauthorized') => __('widget.sync_activity.result.unauthorized'),
            str_contains($error, '403') || str_contains($error, 'Forbidden') => __('widget.sync_activity.result.forbidden'),
            str_contains($error, 'rate limit') || str_contains($error, '429') => __('widget.sync_activity.result.rate_limited'),
            str_contains($error, 'timeout') || str_contains($error, 'timed out') => __('widget.sync_activity.result.timeout'),
            str_contains($error, 'Could not resolve host') => __('widget.sync_activity.result.network_error'),
            str_contains($error, 'No tags') || str_contains($error, 'No releases') => __('widget.sync_activity.result.no_releases'),
            default => __('widget.sync_activity.result.failed'),
        };
    }

    protected function getSuggestedAction(SyncLog $record): string
    {
        if ($record->status === SyncStatus::Success) {
            return '-';
        }

        $error = $record->error ?? '';

        return match (true) {
            str_contains($error, 'No composer.json') => __('widget.sync_activity.action.add_composer'),
            str_contains($error, 'No package.json') => __('widget.sync_activity.action.add_package_json'),
            str_contains($error, 'composer.json') || str_contains($error, 'package.json') => __('widget.sync_activity.action.fix_manifest'),
            str_contains($error, '404') || str_contains($error, 'Not Found') => __('widget.sync_activity.action.check_url'),
            str_contains($error, '401') || str_contains($error, 'Unauthorized') => __('widget.sync_activity.action.update_credential'),
            str_contains($error, '403') || str_contains($error, 'Forbidden') => __('widget.sync_activity.action.check_permissions'),
            str_contains($error, 'rate limit') || str_contains($error, '429') => __('widget.sync_activity.action.wait_retry'),
            str_contains($error, 'timeout') || str_contains($error, 'timed out') => __('widget.sync_activity.action.retry_later'),
            str_contains($error, 'Could not resolve host') => __('widget.sync_activity.action.check_network'),
            str_contains($error, 'No tags') || str_contains($error, 'No releases') => __('widget.sync_activity.action.create_release'),
            default => __('widget.sync_activity.action.view_details'),
        };
    }

    protected function getActionUrl(SyncLog $record): ?string
    {
        if ($record->status === SyncStatus::Success) {
            return null;
        }

        $error = $record->error ?? '';
        $repository = $record->repository;

        if (! $repository) {
            return null;
        }

        return match (true) {
            // External GitHub links - open repo to add/fix files
            str_contains($error, 'No composer.json'),
            str_contains($error, 'No package.json'),
            str_contains($error, 'composer.json') && ! str_contains($error, 'No composer.json'),
            str_contains($error, 'package.json') && ! str_contains($error, 'No package.json') => $repository->url,

            // Create release on GitHub
            str_contains($error, 'No tags'),
            str_contains($error, 'No releases') => rtrim($repository->url, '/').'/releases/new',

            // Edit repository settings (check URL)
            str_contains($error, '404'),
            str_contains($error, 'Not Found') => route('filament.admin.resources.repositories.edit', $repository->id),

            // Edit credential
            str_contains($error, '401'),
            str_contains($error, 'Unauthorized') => $repository->credential_id
                ? route('filament.admin.resources.credentials.edit', $repository->credential_id)
                : route('filament.admin.resources.repositories.edit', $repository->id),

            // Check permissions - could be credential or repo access
            str_contains($error, '403'),
            str_contains($error, 'Forbidden') => $repository->credential_id
                ? route('filament.admin.resources.credentials.edit', $repository->credential_id)
                : route('filament.admin.resources.repositories.edit', $repository->id),

            // Rate limit, timeout, network - view repo to retry
            str_contains($error, 'rate limit'),
            str_contains($error, '429'),
            str_contains($error, 'timeout'),
            str_contains($error, 'timed out'),
            str_contains($error, 'Could not resolve host') => route('filament.admin.resources.repositories.view', $repository->id),

            // Default - view repository details
            default => route('filament.admin.resources.repositories.view', $repository->id),
        };
    }

    protected function isExternalAction(SyncLog $record): bool
    {
        if ($record->status === SyncStatus::Success) {
            return false;
        }

        $error = $record->error ?? '';

        return match (true) {
            str_contains($error, 'No composer.json'),
            str_contains($error, 'No package.json'),
            str_contains($error, 'composer.json') && ! str_contains($error, 'No composer.json'),
            str_contains($error, 'package.json') && ! str_contains($error, 'No package.json'),
            str_contains($error, 'No tags'),
            str_contains($error, 'No releases') => true,
            default => false,
        };
    }

    protected function getActionIcon(SyncLog $record): ?string
    {
        if ($record->status === SyncStatus::Success) {
            return null;
        }

        $error = $record->error ?? '';

        return match (true) {
            // External links - arrow pointing out
            str_contains($error, 'No composer.json'),
            str_contains($error, 'No package.json'),
            str_contains($error, 'composer.json') && ! str_contains($error, 'No composer.json'),
            str_contains($error, 'package.json') && ! str_contains($error, 'No package.json'),
            str_contains($error, 'No tags'),
            str_contains($error, 'No releases') => 'heroicon-o-arrow-top-right-on-square',

            // Credential issues - key icon
            str_contains($error, '401'),
            str_contains($error, 'Unauthorized'),
            str_contains($error, '403'),
            str_contains($error, 'Forbidden') => 'heroicon-o-key',

            // URL/not found - link icon
            str_contains($error, '404'),
            str_contains($error, 'Not Found') => 'heroicon-o-link',

            // Rate limit - clock icon
            str_contains($error, 'rate limit'),
            str_contains($error, '429') => 'heroicon-o-clock',

            // Timeout/network - refresh icon
            str_contains($error, 'timeout'),
            str_contains($error, 'timed out'),
            str_contains($error, 'Could not resolve host') => 'heroicon-o-arrow-path',

            // Default - info icon
            default => 'heroicon-o-information-circle',
        };
    }

    protected function getActionIconColor(SyncLog $record): ?string
    {
        if ($record->status === SyncStatus::Success) {
            return null;
        }

        $error = $record->error ?? '';

        return match (true) {
            // External links - primary/blue
            str_contains($error, 'No composer.json'),
            str_contains($error, 'No package.json'),
            str_contains($error, 'composer.json') && ! str_contains($error, 'No composer.json'),
            str_contains($error, 'package.json') && ! str_contains($error, 'No package.json'),
            str_contains($error, 'No tags'),
            str_contains($error, 'No releases') => 'primary',

            // Credential issues - danger/red
            str_contains($error, '401'),
            str_contains($error, 'Unauthorized'),
            str_contains($error, '403'),
            str_contains($error, 'Forbidden') => 'danger',

            // URL/not found - warning/orange
            str_contains($error, '404'),
            str_contains($error, 'Not Found') => 'warning',

            // Rate limit, timeout, network - info/gray
            str_contains($error, 'rate limit'),
            str_contains($error, '429'),
            str_contains($error, 'timeout'),
            str_contains($error, 'timed out'),
            str_contains($error, 'Could not resolve host') => 'gray',

            // Default
            default => 'warning',
        };
    }

    protected function getRefsDisplay(SyncLog $record): string
    {
        $refs = $record->synced_refs;

        if (empty($refs)) {
            return '-';
        }

        $parts = array_map(function (array $ref): string {
            $name = htmlspecialchars($ref['ref'], ENT_QUOTES, 'UTF-8');
            $isNew = $ref['is_new'] ?? false;

            if ($isNew) {
                return '<span style="display:inline-flex;align-items:center;gap:4px">'
                    .$name
                    .'<span style="font-size:0.65rem;font-weight:600;color:#16a34a;background:#dcfce7;border-radius:9999px;padding:1px 6px">new</span>'
                    .'</span>';
            }

            return $name;
        }, $refs);

        return implode('<span style="color:#9ca3af"> · </span>', $parts);
    }
}
