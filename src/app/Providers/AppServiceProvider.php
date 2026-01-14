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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Ensure storage link and required directories exist
        $this->ensureStorageSetup();

        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        // Register AbTest policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\AbTest::class,
            \App\Policies\AbTestPolicy::class
        );
    }

    /**
     * Ensure storage symlink and required directories exist.
     * This fixes 404 errors for uploaded images in production.
     */
    protected function ensureStorageSetup(): void
    {
        // Only run in production or when not in console (avoid issues during artisan commands)
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
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
