<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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

        return Inertia::render('License/Activate', [
            'licenseActive' => $licenseKey !== null,
            'licensePlan' => $licensePlan?->value,
            'licenseKey' => $licenseKey?->value,
            'licenseExpiresAt' => $licenseExpiresAt?->value,
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
                
                // Check if license key is returned directly
                if (isset($data['license_key']) && !empty($data['license_key'])) {
                    // Auto-activate the license
                    $this->saveLicense($data['license_key'], 'SILVER', null);
                    
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

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się wysłać prośby. Spróbuj ponownie później.',
            ], 422);
            
        } catch (\Exception $e) {
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
     */
    public function activate(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string|min:20',
        ]);

        $key = $request->license_key;
        $parts = explode('-', $key);
        
        if (count($parts) < 3) {
            return back()->withErrors(['license_key' => 'Nieprawidłowy format klucza licencji.']);
        }

        // Decode plan from license key
        $decodedPlan = @base64_decode($parts[0]);
        if (!$decodedPlan || !in_array(strtoupper($decodedPlan), ['SILVER', 'GOLD'])) {
            return back()->withErrors(['license_key' => 'Nieprawidłowy klucz licencji.']);
        }

        $plan = strtoupper($decodedPlan);
        
        // Validate license via webhook
        $webhookUrl = config('netsendo.license_validate_webhook_url');
        $domain = $request->getHost();
        
        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'action' => 'validate_license',
                'license_key' => $key,
                'domain' => $domain,
                'version' => config('netsendo.version'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'VALID_LIFETIME' || $data['status'] === 'VALID_GOLD') {
                    // Save license
                    $expiresAt = $data['expires_at'] ?? null;
                    $this->saveLicense($key, $plan, $expiresAt);

                    return Redirect::route('dashboard')->with('success', 'Licencja ' . $plan . ' została aktywowana!');
                } else {
                    $errorMessage = match($data['status']) {
                        'EXPIRED_GOLD' => 'Licencja GOLD wygasła. Odnów subskrypcję.',
                        'INVALID' => 'Nieprawidłowy klucz licencji.',
                        default => 'Nie udało się zweryfikować licencji.',
                    };
                    return back()->withErrors(['license_key' => $errorMessage]);
                }
            }
            
            // If webhook fails, try offline validation (basic format check)
            $this->saveLicense($key, $plan, null);
            return Redirect::route('dashboard')->with('success', 'Licencja ' . $plan . ' została aktywowana (tryb offline).');
            
        } catch (\Exception $e) {
            // Offline activation as fallback
            $this->saveLicense($key, $plan, null);
            return Redirect::route('dashboard')->with('success', 'Licencja ' . $plan . ' została aktywowana (tryb offline).');
        }
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
     * Webhook endpoint for automatic license activation from external system.
     * Expects JSON array with license data:
     * [{"package": "SILVER", "licenseKey": "...", "domain": "...", "contact_email": "...", "is_active": true}]
     */
    public function webhookActivate(Request $request)
    {
        // Get the payload - can be array or object
        $payload = $request->all();
        
        // If it's an array with numeric keys, get the first item
        if (isset($payload[0])) {
            $licenseData = $payload[0];
        } else {
            $licenseData = $payload;
        }

        // Validate required fields
        if (!isset($licenseData['licenseKey']) || empty($licenseData['licenseKey'])) {
            return response()->json([
                'success' => false,
                'message' => 'Missing licenseKey field',
            ], 400);
        }

        if (!isset($licenseData['is_active']) || $licenseData['is_active'] !== true) {
            return response()->json([
                'success' => false,
                'message' => 'License is not active',
            ], 400);
        }

        // Extract license information
        $licenseKey = $licenseData['licenseKey'];
        $package = strtoupper($licenseData['package'] ?? 'SILVER');
        $contactEmail = $licenseData['contact_email'] ?? null;
        $domain = $licenseData['domain'] ?? null;

        // Validate package
        if (!in_array($package, ['SILVER', 'GOLD'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid package type',
            ], 400);
        }

        // Save the license
        $this->saveLicense($licenseKey, $package, null);

        // Optionally save contact email if provided
        if ($contactEmail) {
            Setting::updateOrCreate(
                ['key' => 'license_email'],
                ['value' => $contactEmail]
            );
        }

        // Log the activation
        \Illuminate\Support\Facades\Log::info('License activated via webhook', [
            'package' => $package,
            'domain' => $domain,
            'email' => $contactEmail,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'License activated successfully',
            'package' => $package,
        ]);
    }
}
