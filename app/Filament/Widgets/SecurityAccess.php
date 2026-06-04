<?php

namespace App\Filament\Widgets;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Models\Token;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Route;

class SecurityAccess extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    /** Tokens unused for this many days are considered idle. */
    protected const TOKEN_IDLE_DAYS = 90;

    protected function getHeading(): ?string
    {
        return __('widget.security.heading');
    }

    protected function getStats(): array
    {
        return [
            $this->activeTokensStat(),
            $this->idleTokensStat(),
            $this->credentialsStat(),
            $this->twoFactorStat(),
        ];
    }

    protected function activeTokensStat(): Stat
    {
        $active = Token::where('enabled', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        $expired = Token::where('enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();
        $disabled = Token::where('enabled', false)->count();

        return Stat::make(__('widget.security.active_tokens'), $active)
            ->description(($expired + $disabled) > 0
                ? __('widget.security.active_tokens_desc', ['expired' => $expired, 'disabled' => $disabled])
                : __('widget.security.active_tokens_ok'))
            ->color($expired > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-key')
            ->url($this->tokensUrl());
    }

    protected function idleTokensStat(): Stat
    {
        $neverUsed = Token::where('enabled', true)->whereNull('last_used_at')->count();
        $idle = Token::where('enabled', true)
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '<', now()->subDays(self::TOKEN_IDLE_DAYS))
            ->count();
        $total = $neverUsed + $idle;

        return Stat::make(__('widget.security.idle_tokens'), $total)
            ->description(__('widget.security.idle_tokens_desc', ['never' => $neverUsed]))
            ->color($total > 0 ? 'warning' : 'gray')
            ->icon('heroicon-o-clock')
            ->url($this->tokensUrl());
    }

    protected function credentialsStat(): Stat
    {
        $failed = Credential::where('status', CredentialStatus::Fail)->count();
        $unknown = Credential::where('status', CredentialStatus::Unknown)->count();
        $healthy = Credential::where('status', CredentialStatus::Ok)->count();

        return Stat::make(__('widget.security.credentials'), $healthy)
            ->description(__('widget.security.credentials_desc', ['failed' => $failed, 'unknown' => $unknown]))
            ->color($failed > 0 ? 'danger' : ($unknown > 0 ? 'warning' : 'success'))
            ->icon('heroicon-o-shield-check')
            ->url(Route::has('filament.admin.resources.credentials.index')
                ? route('filament.admin.resources.credentials.index')
                : null);
    }

    protected function twoFactorStat(): Stat
    {
        $total = User::count();
        $withMfa = User::whereNotNull('app_authentication_secret')->count();
        $missing = max($total - $withMfa, 0);

        return Stat::make(__('widget.security.two_factor'), "{$withMfa}/{$total}")
            ->description($missing > 0
                ? __('widget.security.two_factor_missing', ['count' => $missing])
                : __('widget.security.two_factor_all'))
            ->color($missing > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-finger-print')
            ->url(Route::has('filament.admin.auth.profile') ? route('filament.admin.auth.profile') : null);
    }

    protected function tokensUrl(): ?string
    {
        return Route::has('filament.admin.resources.tokens.index')
            ? route('filament.admin.resources.tokens.index')
            : null;
    }
}
