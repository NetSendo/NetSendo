<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies for HTTPS behind reverse proxy (nginx, Cloudflare, etc.)
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\CheckInstallation::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'api.key' => \App\Http\Middleware\ApiKeyAuth::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'affiliate.auth' => \App\Http\Middleware\AffiliateAuth::class,
        ]);

        // Exclude routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/cron/webhook',
            'webhooks/bounce/*',
            'subscribe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booting(function (Application $app): void {
        // Configure API rate limiting
        RateLimiter::for('api', function (Request $request) {
            $apiKey = $request->get('api_key');
            $identifier = $apiKey?->id ?? $request->ip();

            return Limit::perMinute(600)->by($identifier);
        });
    })
    ->create();

