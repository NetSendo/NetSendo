<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordRelayService;
use Illuminate\Http\Request;
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

        // n8n relay: emails the new password and returns it (App\Services\PasswordRelayService)
        if (app(PasswordRelayService::class)->sendNewPassword($user)) {
            return redirect()->back()->with('status', __('auth.webhook_reset.success'));
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }
}
