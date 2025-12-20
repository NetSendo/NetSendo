<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Get license status directly from database
        $licenseActive = false;
        $licensePlan = null;
        
        try {
            $licenseKey = Setting::where('key', 'license_key')->first();
            $licensePlanSetting = Setting::where('key', 'license_plan')->first();
            
            $licenseActive = $licenseKey !== null;
            $licensePlan = $licensePlanSetting?->value;
        } catch (\Exception $e) {
            // Database not ready
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => [
                'current' => App::getLocale(),
                'supported' => Config::get('localization.supported_locales', []),
            ],
            'license' => [
                'active' => $licenseActive,
                'plan' => $licensePlan,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'appVersion' => config('netsendo.version', '1.0.0'),
        ];
    }
}

