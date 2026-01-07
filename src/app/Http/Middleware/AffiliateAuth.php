<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AffiliateAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('affiliate')->check()) {
            // Try to extract program slug from URL or session
            $programSlug = $request->route('program') ?? session('affiliate_program_slug', 'default');

            return redirect()->route('partner.login', ['program' => $programSlug]);
        }

        $affiliate = Auth::guard('affiliate')->user();

        // Verify affiliate is still approved
        if ($affiliate->status !== 'approved') {
            Auth::guard('affiliate')->logout();
            return redirect()->route('partner.login', ['program' => $affiliate->program->slug])
                ->withErrors(['email' => __('affiliate.account_not_approved')]);
        }

        // Share affiliate data with Inertia
        \Inertia\Inertia::share('affiliate', fn() => [
            'id' => $affiliate->id,
            'name' => $affiliate->name,
            'email' => $affiliate->email,
            'referral_code' => $affiliate->referral_code,
            'program_id' => $affiliate->program_id,
            'program_name' => $affiliate->program->name,
        ]);

        return $next($request);
    }
}
