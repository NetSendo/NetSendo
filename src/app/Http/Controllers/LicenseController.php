<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\LicenseVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class LicenseController extends Controller
{
    /**
     * Show the license activation page.
     */
    public function index()
    {
        $licenseKey = Setting::where('key', 'license_key')->first();
        $licensePlan = Setting::where('key', 'license_plan')->first();
        $licenseExpiresAt = Setting::where('key', 'license_expires_at')->first();
        $licenseEmail = Setting::where('key', 'license_email')->first();
        $licenseDomain = Setting::where('key', 'license_domain')->first();

        return Inertia::render('License/Activate', [
            'licenseActive' => $licenseKey !== null,
            'licensePlan' => $licensePlan?->value,
            'licenseKey' => $licenseKey?->value,
            'licenseExpiresAt' => $licenseExpiresAt?->value,
            'licenseEmail' => $licenseEmail?->value,
            'licenseDomain' => $licenseDomain?->value,
            'appVersion' => config('netsendo.version'),
            'plans' => config('netsendo.plans'),
            'stripeGoldPaymentLink' => config('netsendo.stripe_gold_payment_link'),
        ]);
    }

    /**
     * Request SILVER license via webhook.
     */
    public function requestSilverLicense(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $domain = $request->getHost();
        $webhookUrl = config('netsendo.license_webhook_url');

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'action' => 'request_license',
                'email' => $request->email,
                'domain' => $domain,
                'package' => 'SILVER',
                'version' => config('netsendo.version'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check if license key is returned directly (support both formats)
                $licenseKey = $data['license_key'] ?? $data['licenseKey'] ?? null;

                if (!empty($licenseKey)) {
                    // Auto-activate the license
                    $this->saveLicense($licenseKey, 'SILVER', null);

                    return response()->json([
                        'success' => true,
                        'auto_activated' => true,
                        'message' => 'Licencja SILVER została automatycznie aktywowana!',
                    ]);
                }

                // License will be sent later (e.g., via email)
                return response()->json([
                    'success' => true,
                    'auto_activated' => false,
                    'message' => 'Prośba o licencję została wysłana! Sprawdź swoją skrzynkę email lub wpisz klucz poniżej.',
                ]);
            }

            \Illuminate\Support\Facades\Log::error('License request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $webhookUrl
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się wysłać prośby. Spróbuj ponownie później.',
            ], 422);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('License request exception', [
                'message' => $e->getMessage(),
                'url' => $webhookUrl
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Błąd połączenia z serwerem licencji. Spróbuj ponownie później.',
            ], 500);
        }
    }

    /**
     * Validate license key via webhook.
     */
    public function validateLicense(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string|min:20',
        ]);

        $webhookUrl = config('netsendo.license_validate_webhook_url');
        $domain = $request->getHost();

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'action' => 'validate_license',
                'license_key' => $request->license_key,
                'domain' => $domain,
                'version' => config('netsendo.version'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'valid' => $data['status'] === 'VALID_LIFETIME' || $data['status'] === 'VALID_GOLD',
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'package' => $data['package'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                ]);
            }

            return response()->json([
                'valid' => false,
                'status' => 'INVALID',
                'message' => 'Nie udało się zweryfikować licencji.',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'status' => 'ERROR',
                'message' => 'Błąd połączenia z serwerem licencji.',
            ], 500);
        }
    }

    /**
     * Activate license with provided key.
     * Now validates license with external webhook BEFORE activation.
     */
    public function activate(Request $request, LicenseVerificationService $verificationService)
    {
        $request->validate([
            'license_key' => 'required|string|min:10',
        ]);

        $key = trim($request->license_key);
        $parts = explode('-', $key);

        if (count($parts) < 2) {
            return back()->withErrors(['license_key' => 'Nieprawidłowy format klucza licencji.']);
        }

        // Decode plan from license key (first part is base64 encoded plan)
        $decodedPlan = @base64_decode($parts[0]);
        if (!$decodedPlan || !in_array(strtoupper($decodedPlan), ['SILVER', 'GOLD'])) {
            return back()->withErrors(['license_key' => 'Nieprawidłowy klucz licencji.']);
        }

        $domain = $request->getHost();

        // WERYFIKACJA LICENCJI PRZEZ WEBHOOK PRZED AKTYWACJĄ
        // Użytkownik nie może wpisać dowolnego klucza - musi być zwalidowany
        $verificationResult = $verificationService->verifyLicense($key, $domain);

        if (!$verificationResult['valid']) {
            return back()->withErrors([
                'license_key' => $verificationResult['message'] ?? 'Nieprawidłowy klucz licencji lub licencja nieaktywna.'
            ]);
        }

        // Używamy planu z odpowiedzi webhooka (bardziej wiarygodne)
        $plan = $verificationResult['plan'] ?? strtoupper($decodedPlan);
        $expiresAt = $verificationResult['expires_at'] ?? null;

        // Zapisz licencję - teraz wiemy że jest zwalidowana
        $this->saveLicense($key, $plan, $expiresAt);

        // Zapisz domenę
        Setting::updateOrCreate(
            ['key' => 'license_domain'],
            ['value' => $domain]
        );

        return Redirect::route('dashboard')->with('success', 'Licencja ' . $plan . ' została aktywowana!');
    }

    /**
     * Save license to database.
     */
    private function saveLicense(string $key, string $plan, ?string $expiresAt): void
    {
        Setting::updateOrCreate(
            ['key' => 'license_key'],
            ['value' => $key]
        );

        Setting::updateOrCreate(
            ['key' => 'license_plan'],
            ['value' => $plan]
        );

        if ($expiresAt) {
            Setting::updateOrCreate(
                ['key' => 'license_expires_at'],
                ['value' => $expiresAt]
            );
        } else {
            Setting::where('key', 'license_expires_at')->delete();
        }
    }

    /**
     * Get license status (API endpoint).
     */
    public function status()
    {
        $licenseKey = Setting::where('key', 'license_key')->first();
        $licensePlan = Setting::where('key', 'license_plan')->first();
        $licenseExpiresAt = Setting::where('key', 'license_expires_at')->first();

        $isActive = $licenseKey !== null;
        $isExpired = false;

        if ($licensePlan?->value === 'GOLD' && $licenseExpiresAt?->value) {
            $isExpired = now()->isAfter($licenseExpiresAt->value);
        }

        return response()->json([
            'active' => $isActive && !$isExpired,
            'plan' => $licensePlan?->value,
            'expires_at' => $licenseExpiresAt?->value,
            'is_expired' => $isExpired,
        ]);
    }

    /**
     * Manual license status check (same as cron verification).
     * Returns license status from external webhook.
     */
    public function checkLicenseStatus(LicenseVerificationService $verificationService)
    {
        $result = $verificationService->checkLicenseStatus();

        // If license should be deactivated, do it
        if (isset($result['should_deactivate']) && $result['should_deactivate']) {
            $verificationService->deactivateLicense();
            $result['deactivated'] = true;
        }

        return response()->json($result);
    }

    /**
     * Webhook endpoint for automatic license activation from external system.
     * Expects JSON array with license data:
     * [{"package": "SILVER", "licenseKey": "...", "domain": "...", "contact_email": "...", "is_active": true}]
     */
    public function webhookActivate(Request $request)
    {
        Log::info('Webhook activation received', $request->all());

        $licenseData = $request->all();

        // Check for license key in camelCase (n8n) or snake_case
        $licenseKey = $licenseData['licenseKey'] ?? $licenseData['license_key'] ?? null;
        $plan = $licenseData['package'] ?? $licenseData['plan'] ?? 'SILVER';
        $expiresAt = $licenseData['expires_at'] ?? null;
        $contactEmail = $licenseData['contact_email'] ?? $licenseData['email'] ?? null;
        $domain = $licenseData['domain'] ?? null;

        if (!$licenseKey) {
            Log::warning('Webhook activation failed: No license key found', $licenseData);
            return response()->json(['success' => false, 'message' => 'No license key provided'], 400);
        }

        try {
            $this->saveLicense($licenseKey, $plan, $expiresAt);

            // Optionally save contact email
            if ($contactEmail) {
                Setting::updateOrCreate(
                    ['key' => 'license_email'],
                    ['value' => $contactEmail]
                );
            }

            Log::info("License activated via webhook: $licenseKey ($plan)");

            return response()->json([
                'success' => true,
                'message' => 'License activated',
                'package' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook activation exception', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}
