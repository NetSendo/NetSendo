<?php

namespace App\Http\Middleware;

use App\Licensing\Feature;
use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the licence in effect includes the feature,
 * e.g. `->middleware('licensed:ab_tests')`. Otherwise FeatureRequiresGold answers.
 */
class RequireLicensedFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        app(LicenseService::class)->ensureAllows(Feature::from($feature));

        return $next($request);
    }
}
