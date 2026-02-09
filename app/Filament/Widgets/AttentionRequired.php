<?php

namespace App\Filament\Widgets;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Models\Repository;
use App\Models\Token;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class AttentionRequired extends Widget
{
    protected string $view = 'filament.widgets.attention-required';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::hasIssues();
    }

    protected static function hasIssues(): bool
    {
        return self::getFailedRepositoriesCount() > 0
            || self::getExpiringTokensCount() > 0
            || self::getInvalidCredentialsCount() > 0;
    }

    protected static function getFailedRepositoriesCount(): int
    {
        return Repository::whereNotNull('last_error')->count();
    }

    protected static function getExpiringTokensCount(): int
    {
        return Token::where('enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();
    }

    protected static function getInvalidCredentialsCount(): int
    {
        return Credential::where('status', '!=', CredentialStatus::Ok)->count();
    }

    public function getFailedRepositories(): Collection
    {
        return Repository::whereNotNull('last_error')
            ->select(['id', 'name', 'last_error', 'last_sync_at'])
            ->limit(5)
            ->get();
    }

    public function getExpiringTokens(): Collection
    {
        return Token::where('enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->select(['id', 'name', 'expires_at'])
            ->orderBy('expires_at')
            ->limit(5)
            ->get();
    }

    public function getInvalidCredentials(): Collection
    {
        return Credential::where('status', '!=', CredentialStatus::Ok)
            ->select(['id', 'name', 'last_error', 'last_checked_at'])
            ->limit(5)
            ->get();
    }

    public function getHeading(): string
    {
        return __('widget.attention.title');
    }
}
