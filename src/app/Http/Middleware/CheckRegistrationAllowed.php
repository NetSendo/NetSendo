<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRegistrationAllowed
{
    /**
     * Handle an incoming request.
     * Registration creates the first account (the administrator) only: once any user exists,
     * on a self-hosted install after the first sign-up and on a hosted one right after
     * netsendo:bootstrap, it is closed and further users are added by the administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if any user exists in the database
        if (User::exists()) {
            // Admin already registered, block access to registration
            return redirect()->route('login')
                ->with('error', __('auth.registration_closed'));
        }

        return $next($request);
    }
}
