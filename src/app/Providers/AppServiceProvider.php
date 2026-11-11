<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Console\MigrationLock::class);

        // NetSendo connector: tools are run by the @netsendo/mcp-client package (mcp/)
        $this->app->singleton(\App\Services\Connector\ToolEngine::class, \App\Services\Connector\NodeToolEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // `migrate` and `db:seed` started by several containers at once wait for each other
        // instead of racing (App\Console\MigrationLock)
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandStarting::class,
            fn ($event) => $this->app->make(\App\Console\MigrationLock::class)->commandStarting($event)
        );
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandFinished::class,
            fn ($event) => $this->app->make(\App\Console\MigrationLock::class)->commandFinished($event)
        );

        // A sleeping Cloud instance (NETSENDO_DORMANT) sends nothing: a queue worker that is
        // still running pauses instead of taking jobs (App\Http\Middleware\DormantMode)
        if (config('netsendo.dormant')) {
            \Illuminate\Support\Facades\Queue::looping(fn () => false);
        }

        // Ensure storage link and required directories exist
        $this->ensureStorageSetup();

        // API reference (/docs/api, /docs/api.json) only for signed-in panel users:
        // it was public on every instance, exposing the full API surface and the
        // docs generator (dedoc/scramble) to anonymous requests.
        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
            return $user !== null;
        });

        // Register AbTest policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AbTest::class,
            \App\Policies\AbTestPolicy::class
        );

        // Register Affiliate policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Affiliate::class,
            \App\Policies\AffiliatePolicy::class
        );

        // Register AffiliateProgram policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AffiliateProgram::class,
            \App\Policies\AffiliateProgramPolicy::class
        );

        // Register AffiliateOffer policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AffiliateOffer::class,
            \App\Policies\AffiliateOfferPolicy::class
        );

        // Register AffiliateCommission policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AffiliateCommission::class,
            \App\Policies\AffiliateCommissionPolicy::class
        );

        // Register AffiliatePayout policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AffiliatePayout::class,
            \App\Policies\AffiliatePayoutPolicy::class
        );

        // Register DomainConfiguration policy (Deliverability Shield)
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\DomainConfiguration::class,
            \App\Policies\DomainConfigurationPolicy::class
        );

        // Register InboxSimulation policy (Deliverability Shield)
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\InboxSimulation::class,
            \App\Policies\InboxSimulationPolicy::class
        );
    }

    /**
     * Ensure storage symlink and required directories exist.
     * This fixes 404 errors for uploaded images in production.
     */
    protected function ensureStorageSetup(): void
    {
        // Skip during console commands (migrations, etc.) but allow during web requests
        // This ensures symlinks are created when users access the app
        if (app()->runningInConsole()) {
            return;
        }

        $publicStoragePath = public_path('storage');
        $storagePath = storage_path('app/public');

        // Create storage symlink if it doesn't exist
        if (!File::exists($publicStoragePath)) {
            try {
                // Check if target directory exists
                if (!File::isDirectory($storagePath)) {
                    File::makeDirectory($storagePath, 0755, true);
                }

                // Create symlink
                File::link($storagePath, $publicStoragePath);
            } catch (\Exception $e) {
                // Log error but don't break the application
                \Log::warning('Failed to create storage symlink: ' . $e->getMessage());
            }
        }

        // Ensure templates/images directory exists
        $templatesImagesPath = storage_path('app/public/templates/images');
        if (!File::isDirectory($templatesImagesPath)) {
            try {
                File::makeDirectory($templatesImagesPath, 0755, true);
            } catch (\Exception $e) {
                \Log::warning('Failed to create templates/images directory: ' . $e->getMessage());
            }
        }

        // Ensure templates/thumbnails directory exists
        $templatesThumbnailsPath = storage_path('app/public/templates/thumbnails');
        if (!File::isDirectory($templatesThumbnailsPath)) {
            try {
                File::makeDirectory($templatesThumbnailsPath, 0755, true);
            } catch (\Exception $e) {
                \Log::warning('Failed to create templates/thumbnails directory: ' . $e->getMessage());
            }
        }
    }
}
