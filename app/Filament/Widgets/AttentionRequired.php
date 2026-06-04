<?php

namespace App\Filament\Widgets;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Models\DockerUpload;
use App\Models\Repository;
use App\Models\Token;
use App\Support\PackgridSettings;
use Filament\Widgets\Widget;

class AttentionRequired extends Widget
{
    protected string $view = 'filament.widgets.attention-required';

    protected int|string|array $columnSpan = 'full';

    /** Show at most this many items per category before linking to "view all". */
    protected const ITEM_LIMIT = 5;

    public static function canView(): bool
    {
        return self::hasIssues();
    }

    protected static function hasIssues(): bool
    {
        return self::failedRepositoriesQuery()->exists()
            || self::staleRepositoriesQuery()->exists()
            || self::problematicTokensQuery()->exists()
            || self::invalidCredentialsQuery()->exists()
            || self::staleUploadsCount() > 0
            || self::operationalAlerts() !== [];
    }

    protected function getViewData(): array
    {
        return [
            'failedRepositories' => self::failedRepositoriesQuery()
                ->select(['id', 'name', 'last_error', 'last_sync_at'])
                ->limit(self::ITEM_LIMIT)->get(),
            'failedRepositoriesCount' => self::failedRepositoriesQuery()->count(),

            'staleRepositories' => self::staleRepositoriesQuery()
                ->select(['id', 'name', 'last_sync_at'])
                ->orderBy('last_sync_at')
                ->limit(self::ITEM_LIMIT)->get(),
            'staleRepositoriesCount' => self::staleRepositoriesQuery()->count(),

            'problematicTokens' => self::problematicTokensQuery()
                ->select(['id', 'name', 'expires_at'])
                ->orderBy('expires_at')
                ->limit(self::ITEM_LIMIT)->get(),
            'problematicTokensCount' => self::problematicTokensQuery()->count(),

            'invalidCredentials' => self::invalidCredentialsQuery()
                ->select(['id', 'name', 'last_error', 'last_checked_at'])
                ->limit(self::ITEM_LIMIT)->get(),
            'invalidCredentialsCount' => self::invalidCredentialsQuery()->count(),

            'staleUploadsCount' => self::staleUploadsCount(),
            'operationalAlerts' => self::operationalAlerts(),
            'itemLimit' => self::ITEM_LIMIT,
        ];
    }

    protected static function failedRepositoriesQuery()
    {
        return Repository::query()->whereNotNull('last_error');
    }

    protected static function staleRepositoriesQuery()
    {
        return Repository::query()->stale();
    }

    /** Enabled tokens that are already expired or expiring within 7 days. */
    protected static function problematicTokensQuery()
    {
        return Token::query()
            ->where('enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7));
    }

    protected static function invalidCredentialsQuery()
    {
        return Credential::query()->where('status', '!=', CredentialStatus::Ok);
    }

    protected static function staleUploadsCount(): int
    {
        if (! PackgridSettings::dockerEnabled()) {
            return 0;
        }

        $staleHours = (int) config('packgrid.docker.gc_stale_upload_hours', 24);

        return DockerUpload::whereIn('status', ['pending', 'uploading'])
            ->where(function ($query) use ($staleHours) {
                $query->where('expires_at', '<', now())
                    ->orWhere('created_at', '<', now()->subHours($staleHours));
            })
            ->count();
    }

    /**
     * Operational, instance-wide problems (no per-record list): disabled Docker
     * garbage collection. (Backup status lives on the Backup & Restore page.)
     *
     * @return array<int, array{key: string, color: string}>
     */
    protected static function operationalAlerts(): array
    {
        $alerts = [];

        if (PackgridSettings::dockerEnabled() && ! (bool) config('packgrid.docker.gc_enabled', true)) {
            $alerts[] = ['key' => 'gc_disabled', 'color' => 'warning'];
        }

        return $alerts;
    }

    public function getHeading(): string
    {
        return __('widget.attention.title');
    }
}
