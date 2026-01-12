<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        // Register AbTest policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AbTest::class,
            \App\Policies\AbTestPolicy::class
        );
    }
}
