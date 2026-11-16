<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;

class CheckInstallation
{
    /**
     * Handle an incoming request.
     * Instead of blocking, we just check license status and pass it to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check license status and add to request attributes
        try {
            $licenseKey = Setting::where('key', 'license_key')->first();
            $licensePlan = Setting::where('key', 'license_plan')->first();
            
            $request->attributes->set('license_active', $licenseKey !== null);
            $request->attributes->set('license_key', $licenseKey?->value);
            // The plan in effect (an expired GOLD or a missing key works as SILVER)
            $request->attributes->set('license_plan', app(\App\Services\LicenseService::class)->getCurrentPlan());
        } catch (\Exception $e) {
            $request->attributes->set('license_active', false);
            $request->attributes->set('license_key', null);
            $request->attributes->set('license_plan', null);
        }

        return $next($request);
    }
}
