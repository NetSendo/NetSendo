<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PartnerAuthController extends Controller
{
    /**
     * Show registration page.
     */
    public function showRegister(string $program, Request $request)
    {
        $programModel = AffiliateProgram::where('slug', $program)
            ->where('status', 'active')
            ->firstOrFail();

        // Check for referral code in URL
        $referralCode = $request->query('ref');
        $referralPartner = null;

        if ($referralCode) {
            $referralPartner = Affiliate::where('referral_code', $referralCode)
                ->where('program_id', $programModel->id)
                ->where('status', 'approved')
                ->first();
        }

        return Inertia::render('Partner/Auth/Register', [
            'program' => $programModel->only(['id', 'name', 'slug', 'terms_text', 'terms_url']),
            'referralCode' => $referralCode,
            'referralPartnerName' => $referralPartner?->name,
        ]);
    }

    /**
     * Handle registration.
     */
    public function register(Request $request, string $program)
    {
        $programModel = AffiliateProgram::where('slug', $program)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:affiliates,email',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'country' => 'nullable|string|size:2',
            'accept_terms' => 'required|accepted',
            'referral_code' => 'nullable|string', // Parent affiliate code
        ]);

        // Find parent affiliate if referral code provided
        $parentAffiliate = null;
        if (!empty($validated['referral_code'])) {
            $parentAffiliate = Affiliate::where('referral_code', $validated['referral_code'])
                ->where('program_id', $programModel->id)
                ->where('status', 'approved')
                ->first();
        }

        $affiliate = Affiliate::create([
            'program_id' => $programModel->id,
            'parent_affiliate_id' => $parentAffiliate?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_name' => $validated['company_name'] ?? null,
            'website' => $validated['website'] ?? null,
            'country' => $validated['country'] ?? null,
            'status' => $programModel->auto_approve_affiliates ? 'approved' : 'pending',
            'referral_code' => strtoupper(Str::random(8)),
            'joined_at' => now(),
            'approved_at' => $programModel->auto_approve_affiliates ? now() : null,
        ]);

        if ($programModel->auto_approve_affiliates) {
            // Auto-login if approved
            Auth::guard('affiliate')->login($affiliate);
            return redirect()->route('partner.dashboard');
        }

        return Inertia::render('Partner/Auth/Pending', [
            'program' => $programModel->only(['name', 'slug']),
        ]);
    }

    /**
     * Show login page.
     */
    public function showLogin(string $program)
    {
        $programModel = AffiliateProgram::where('slug', $program)
            ->where('status', 'active')
            ->firstOrFail();

        return Inertia::render('Partner/Auth/Login', [
            'program' => $programModel->only(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Handle login.
     */
    public function login(Request $request, string $program)
    {
        $programModel = AffiliateProgram::where('slug', $program)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $affiliate = Affiliate::where('email', $validated['email'])
            ->where('program_id', $programModel->id)
            ->first();

        if (!$affiliate || !Hash::check($validated['password'], $affiliate->password)) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        if ($affiliate->status === 'pending') {
            return back()->withErrors([
                'email' => __('affiliate.account_pending'),
            ]);
        }

        if ($affiliate->status === 'blocked') {
            return back()->withErrors([
                'email' => __('affiliate.account_blocked'),
            ]);
        }

        $affiliate->update(['last_login_at' => now()]);

        Auth::guard('affiliate')->login($affiliate, $validated['remember'] ?? false);

        return redirect()->intended(route('partner.dashboard'));
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        $affiliate = Auth::guard('affiliate')->user();
        $programSlug = $affiliate?->program?->slug ?? 'default';

        Auth::guard('affiliate')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner.login', ['program' => $programSlug]);
    }
}
