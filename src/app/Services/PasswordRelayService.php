<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Emails a user a new password through the NetSendo password relay (n8n + SendGrid) and sets
 * it here. It reaches the user whatever SMTP the installation has, or has not, configured:
 * used by "Forgot password" on the sign-in page and by netsendo:bootstrap.
 */
class PasswordRelayService
{
    private const URL = 'https://a.gregciupek.com/webhook/6b7bebf0-b76f-4717-9d9f-fe8c7831d473';

    /**
     * @param  string  $reason  'reset' ("Forgot password") or 'welcome' (first administrator of a
     *                          hosted installation, netsendo:bootstrap): the relay picks the email
     * @param  string|null  $locale  language of the email (en, pl, de, es); the app locale if null
     */
    public function sendNewPassword(User $user, string $reason = 'reset', ?string $locale = null): bool
    {
        try {
            $response = Http::post(self::URL, [
                'email' => $user->email,
                'name' => $user->name,
                'origin_url' => config('app.url'),
                'login_url' => rtrim((string) config('app.url'), '/') . '/login',
                'reason' => $reason === 'welcome' ? 'welcome' : 'reset',
                'locale' => $locale ?? app()->getLocale(),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Password relay unreachable', ['error' => $e->getMessage()]);

            return false;
        }

        $password = $response->successful() ? $response->json('newPassword') : null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        return true;
    }
}
