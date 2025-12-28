<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WebhookPasswordResetController extends Controller
{
    /**
     * Handle an incoming password reset request via webhook.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // For security, do not reveal if the email does not exist.
            // But for this specific internal/custom tool, user might want to know?
            // Standard practice: return success or generic message.
            // Let's return validation error to match Login behavior if needed,
            // strictly following plan: "Validate email (exists:users)"
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Call n8n webhook
        $response = Http::post('https://a.gregciupek.com/webhook/6b7bebf0-b76f-4717-9d9f-fe8c7831d473', [
            'email' => $user->email,
        ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['newPassword'])) {
                $user->forceFill([
                    'password' => Hash::make($data['newPassword']),
                ])->save();

                return redirect()->back()->with('status', __('auth.webhook_reset.success'));
            }
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }
}
