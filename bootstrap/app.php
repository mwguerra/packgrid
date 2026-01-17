<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->alias([
            'packgrid.token' => \App\Http\Middleware\ValidatePackgridToken::class,
            'docker.auth' => \App\Http\Middleware\DockerRegistryAuth::class,
            'feature' => \App\Http\Middleware\CheckFeatureEnabled::class,
        ]);

        // Exclude API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'packages.json',
            'p/*',
            'dist/*',
            'npm/*',
            'v2/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
