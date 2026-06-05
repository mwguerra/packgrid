<?php

namespace App\Providers;

use App\Models\Token;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Throttle the registry routes per authenticated token (or per IP for
     * anonymous/fallback access) to mitigate scraping, brute-force and DoS,
     * and to keep request floods from exhausting the GitHub credential's
     * upstream rate limit.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('packgrid-registry', function (Request $request) {
            $perMinute = (int) config('packgrid.rate_limit.per_minute', 600);

            if ($perMinute <= 0) {
                return Limit::none();
            }

            $token = $request->attributes->get('packgrid_token');
            $key = $token instanceof Token ? 'token:'.$token->id : 'ip:'.$request->ip();

            return Limit::perMinute($perMinute)->by($key);
        });
    }
}
