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

    public function sendNewPassword(User $user): bool
    {
        try {
            $response = Http::post(self::URL, [
                'email' => $user->email,
                'origin_url' => config('app.url'),
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
