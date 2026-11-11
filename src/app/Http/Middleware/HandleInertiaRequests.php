<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Vite;
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
        // A signed-in user gets the scripts of the other pages fetched in the background
        // after the page has loaded, so moving around the panel does not wait for them.
        // Guests do not: the sign-in page and the public pages would download the whole
        // panel (several megabytes) for a visitor who may never open it.
        if ($request->user()) {
            Vite::prefetch(concurrency: 3);
        }

        // Get license status directly from database
        $licenseActive = false;
        $licensePlan = null;
        $licenseFeatures = [];

        try {
            $licenses = app(LicenseService::class);
            $licenseActive = Setting::where('key', 'license_key')->exists();
            // The plan in effect: an expired GOLD or a missing key works as SILVER
            $licensePlan = $licenses->getCurrentPlan();
            // GOLD features with whether this installation may use them (useLicense().can())
            $licenseFeatures = $licenses->features();
        } catch (\Exception $e) {
            // Database not ready
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => $request->user()?->isAdmin() ?? false,
            ],
            'locale' => [
                'current' => App::getLocale(),
                'supported' => Config::get('localization.supported_locales', []),
            ],
            'license' => [
                'active' => $licenseActive,
                'plan' => $licensePlan,
                'features' => $licenseFeatures,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // "Nothing to do" outcomes, e.g. restoring defaults that are all in place
                'info' => fn () => $request->session()->get('info'),
                // A GOLD feature was refused (App\Licensing\FeatureRequiresGold): opens the upgrade dialog
                'gold_required' => fn () => $request->session()->get('gold_required'),
            ],
            'appVersion' => config('netsendo.version', '1.0.0'),
            // Ask the administrator once whether to switch the opt-in telemetry on (dashboard card)
            'telemetry' => fn () => [
                'ask' => (bool) $request->user()?->isAdmin() && rescue(
                    fn () => app(\App\Services\Telemetry\TelemetryService::class)->shouldAsk(),
                    false,
                    report: false
                ),
            ],
        ];
    }
}

