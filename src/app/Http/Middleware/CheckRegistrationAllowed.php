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
     * Block registration if an admin user already exists.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if any user exists in the database
        if (User::exists()) {
            // Admin already registered, block access to registration
            return redirect()->route('login')
                ->with('error', 'Rejestracja jest zamknięta.');
        }

        return $next($request);
    }
}
