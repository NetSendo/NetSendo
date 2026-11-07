<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GOLD bought from this installation through the NetSendo store (netsendo.com).
 *
 * 1. checkoutUrl() creates a random secret, keeps it (encrypted) and returns the store's order
 *    page URL with claim = SHA-256 of the secret. Only the hash passes through the browser.
 * 2. After payment, claim() sends the secret to the store server-to-server and receives the
 *    GOLD key, verifies it with the licence server and activates it. The licence page polls it
 *    while the customer pays, and `license:claim-gold` runs it every few minutes as a fallback.
 */
class GoldPurchaseService
{
    public const SECRET_KEY = 'license_claim_secret';
    public const STARTED_KEY = 'license_claim_started_at';

    /** How long a started purchase is waited for. */
    public const CLAIM_WINDOW_HOURS = 72;

    /** Languages of the store's order page. */
    private const STORE_LOCALES = ['pl', 'en', 'de', 'es'];

    public function __construct(private LicenseVerificationService $verification)
    {
    }

    public function available(): bool
    {
        return (bool) config('netsendo.store_url');
    }

    public function checkoutUrl(string $email, string $domain, string $locale, string $returnUrl): string
    {
        $secret = Str::random(64);

        Setting::updateOrCreate(['key' => self::SECRET_KEY], ['value' => Crypt::encryptString($secret)]);
        Setting::updateOrCreate(['key' => self::STARTED_KEY], ['value' => now()->toIso8601String()]);

        $language = strtolower(substr($locale, 0, 2));
        $language = in_array($language, self::STORE_LOCALES, true) ? $language : 'en';

        $query = http_build_query([
            'plan' => 'gold',
            'source' => 'app',
            'email' => $email,
            'domain' => strtolower($domain),
            'claim' => hash('sha256', $secret),
            'return' => $returnUrl,
        ]);

        return rtrim(config('netsendo.store_url'), '/') . "/{$language}/checkout?{$query}";
    }

    public function hasPendingPurchase(): bool
    {
        $started = Setting::where('key', self::STARTED_KEY)->value('value');
        $secret = Setting::where('key', self::SECRET_KEY)->value('value');

        if (!$started || !$secret) {
            return false;
        }

        return now()->subHours(self::CLAIM_WINDOW_HOURS)->isBefore($started);
    }

    /**
     * Ask the store whether the started purchase is paid and activate GOLD if so.
     *
     * @return array{status: string, plan?: string, expires_at?: ?string, message?: string}
     *   status: none | pending | activated | inactive | error
     */
    public function claim(string $domain): array
    {
        if (!$this->hasPendingPurchase()) {
            $this->clear();
            return ['status' => 'none'];
        }

        try {
            $secret = Crypt::decryptString(Setting::where('key', self::SECRET_KEY)->value('value'));
        } catch (\Throwable $e) {
            $this->clear();
            return ['status' => 'error', 'message' => 'claim_secret_unreadable'];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post(rtrim(config('netsendo.store_url'), '/') . '/api/billing/claim', ['secret' => $secret]);
        } catch (\Throwable $e) {
            Log::warning('GOLD claim: store unreachable', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'store_unreachable'];
        }

        // 404: the customer has not submitted the order form yet
        if ($response->status() === 404) {
            return ['status' => 'pending'];
        }
        if (!$response->successful()) {
            return ['status' => 'error', 'message' => 'store_status_' . $response->status()];
        }

        $data = $response->json();
        $status = $data['status'] ?? null;

        if ($status === 'pending') {
            return ['status' => 'pending'];
        }
        if ($status === 'inactive') {
            $this->clear();
            return ['status' => 'inactive'];
        }
        if ($status !== 'paid' || empty($data['license_key'])) {
            return ['status' => 'error', 'message' => 'unexpected_store_answer'];
        }

        // The key must also pass the licence server before it is stored
        $verified = $this->verification->verifyLicense($data['license_key'], $domain);
        if (!($verified['valid'] ?? false)) {
            Log::warning('GOLD claim: key from the store failed verification', ['message' => $verified['message'] ?? null]);
            return ['status' => 'error', 'message' => 'verification_failed'];
        }

        $plan = strtoupper($verified['plan'] ?? $data['plan'] ?? 'GOLD');
        $expiresAt = $verified['expires_at'] ?? $data['expires_at'] ?? null;
        $this->verification->storeLicense($data['license_key'], $plan, $expiresAt, $domain);
        $this->clear();

        Log::info('GOLD activated from a store purchase', ['plan' => $plan, 'expires_at' => $expiresAt]);

        return ['status' => 'activated', 'plan' => $plan, 'expires_at' => $expiresAt];
    }

    public function clear(): void
    {
        Setting::whereIn('key', [self::SECRET_KEY, self::STARTED_KEY])->delete();
    }
}
