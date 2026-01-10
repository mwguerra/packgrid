<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RedirectToSetupIfNoUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasUsers() && ! $this->isRegisterRoute($request)) {
            return redirect()->to(Filament::getRegistrationUrl() ?? '/admin/register');
        }

        return $next($request);
    }

    protected function hasUsers(): bool
    {
        try {
            return DB::table('users')->count() > 0;
        } catch (\Exception) {
            return true;
        }
    }

    protected function isRegisterRoute(Request $request): bool
    {
        return str_contains($request->path(), 'register');
    }
}
