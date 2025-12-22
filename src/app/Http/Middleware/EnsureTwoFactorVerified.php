<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is not logged in or 2FA is not enabled, proceed
        if (! $user || ! $user->two_factor_enabled) {
            return $next($request);
        }

        // If 2FA is already verified in session, proceed
        if ($request->session()->has('2fa_verified')) {
            return $next($request);
        }

        // Allow access to 2FA verification routes to avoid redirect loop
        if ($request->routeIs('2fa.*') || $request->is('two-factor-challenge')) {
            return $next($request);
        }

        // For JSON requests (API), return error
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Two factor authentication required.'], 423);
        }

        // Redirect to 2FA challenge page
        return redirect()->route('2fa.challenge');
    }
}
