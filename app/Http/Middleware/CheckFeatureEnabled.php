<?php

namespace App\Http\Middleware;

use App\Support\PackgridSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! PackgridSettings::isFeatureEnabled($feature)) {
            return response()->json([
                'error' => 'This feature is disabled. Contact administrator.',
                'feature' => $feature,
            ], 503);
        }

        return $next($request);
    }
}
