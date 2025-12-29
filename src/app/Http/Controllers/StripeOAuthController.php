<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeOAuthController extends Controller
{
    /**
     * Redirect user to Stripe for OAuth authorization.
     */
    public function redirectToStripe()
    {
        $clientId = $this->getClientId();

        if (!$clientId) {
            return back()->with('error', __('stripe.oauth_client_id_missing'));
        }

        $redirectUri = route('settings.stripe.oauth.callback');

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'scope' => 'read_write',
            'redirect_uri' => $redirectUri,
        ]);

        return redirect("https://connect.stripe.com/oauth/authorize?{$params}");
    }

    /**
     * Handle OAuth callback from Stripe.
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            Log::error('Stripe OAuth error', [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);

            return redirect()->route('settings.stripe.index')
                ->with('error', $request->input('error_description', __('stripe.oauth_error')));
        }

        $code = $request->input('code');

        if (!$code) {
            return redirect()->route('settings.stripe.index')
                ->with('error', __('stripe.oauth_code_missing'));
        }

        try {
            // Get secret key for token exchange
            $secretKey = $this->getSecretKeyForTokenExchange();

            if (!$secretKey) {
                throw new \Exception(__('stripe.api_key_missing'));
            }

            $response = Http::asForm()->post('https://connect.stripe.com/oauth/token', [
                'client_secret' => $secretKey,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);

            if (!$response->successful()) {
                $error = $response->json('error_description') ?: $response->json('error') ?: 'Unknown error';
                throw new \Exception($error);
            }

            $data = $response->json();

            // Store OAuth tokens and connected account info
            $this->saveSetting('stripe_oauth_access_token', Crypt::encryptString($data['access_token']));

            if (!empty($data['refresh_token'])) {
                $this->saveSetting('stripe_oauth_refresh_token', Crypt::encryptString($data['refresh_token']));
            }

            $this->saveSetting('stripe_oauth_stripe_user_id', $data['stripe_user_id']);
            $this->saveSetting('stripe_oauth_publishable_key', $data['stripe_publishable_key']);
            $this->saveSetting('stripe_connection_mode', 'oauth');

            // Also update main keys for backwards compatibility
            $this->saveSetting('stripe_publishable_key', $data['stripe_publishable_key']);
            $this->saveSetting('stripe_secret_key', Crypt::encryptString($data['access_token']));

            Log::info('Stripe OAuth connected successfully', [
                'stripe_user_id' => $data['stripe_user_id'],
            ]);

            return redirect()->route('settings.stripe.index')
                ->with('success', __('stripe.oauth_connected'));

        } catch (\Exception $e) {
            Log::error('Stripe OAuth token exchange failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('settings.stripe.index')
                ->with('error', __('stripe.oauth_error') . ': ' . $e->getMessage());
        }
    }

    /**
     * Disconnect OAuth and clear stored tokens.
     */
    public function disconnect()
    {
        // Try to deauthorize with Stripe (optional, best effort)
        try {
            $clientId = $this->getClientId();
            $stripeUserId = Setting::where('key', 'stripe_oauth_stripe_user_id')->first()?->value;
            $secretKey = $this->getSecretKeyForTokenExchange();

            if ($clientId && $stripeUserId && $secretKey) {
                Http::asForm()->post('https://connect.stripe.com/oauth/deauthorize', [
                    'client_id' => $clientId,
                    'stripe_user_id' => $stripeUserId,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Stripe OAuth deauthorization failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Delete OAuth-related settings
        Setting::whereIn('key', [
            'stripe_oauth_access_token',
            'stripe_oauth_refresh_token',
            'stripe_oauth_stripe_user_id',
            'stripe_oauth_publishable_key',
            'stripe_connection_mode',
        ])->delete();

        Log::info('Stripe OAuth disconnected');

        return back()->with('success', __('stripe.oauth_disconnected'));
    }

    /**
     * Get Stripe Client ID from config or database.
     */
    private function getClientId(): ?string
    {
        // First check env/config
        $clientId = config('services.stripe.client_id');

        if (!empty($clientId)) {
            return $clientId;
        }

        // Then check database settings
        return Setting::where('key', 'stripe_client_id')->first()?->value;
    }

    /**
     * Get secret key for OAuth token exchange.
     * This needs to be the platform's secret key, not the connected account's.
     */
    private function getSecretKeyForTokenExchange(): ?string
    {
        // First try config
        $secretKey = config('services.stripe.secret');

        if (!empty($secretKey)) {
            return $secretKey;
        }

        // Then try database (encrypted)
        $encrypted = Setting::where('key', 'stripe_secret_key')->first()?->value;

        if ($encrypted) {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt stored Stripe secret key');
            }
        }

        return null;
    }

    /**
     * Save a setting to database.
     */
    private function saveSetting(string $key, ?string $value): void
    {
        if (empty($value)) {
            Setting::where('key', $key)->delete();
            return;
        }

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
