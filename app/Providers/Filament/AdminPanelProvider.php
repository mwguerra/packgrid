<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\BackupRestore;
use App\Filament\Pages\Settings;
use App\Filament\Widgets\AttentionRequired;
use App\Filament\Widgets\PackgridStats;
use App\Filament\Widgets\SyncActivity;
use App\Http\Middleware\RedirectToSetupIfNoUsers;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->registration(Register::class)
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(8),
            ])
            ->globalSearch(false)
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => __('settings.title'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn () => Settings::getUrl()),
                MenuItem::make()
                    ->label(fn () => __('backup.title'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn () => BackupRestore::getUrl()),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PackgridStats::class,
                AttentionRequired::class,
                SyncActivity::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectToSetupIfNoUsers::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
