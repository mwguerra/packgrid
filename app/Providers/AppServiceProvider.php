<?php

namespace App\Providers;

use App\Contracts\GitProviderClientInterface;
use App\Services\GitHubClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default provider client for contexts where no credential is available
        $this->app->bind(GitProviderClientInterface::class, GitHubClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
