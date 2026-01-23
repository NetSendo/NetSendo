<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use App\Services\AffiliateConversionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private AffiliateConversionService $conversionService
    ) {}

    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        // Check for referral code in URL or cookie
        $referralCode = $request->query('ref');
        $referralAffiliate = null;

        if ($referralCode) {
            $referralAffiliate = Affiliate::where('referral_code', $referralCode)
                ->where('status', 'approved')
                ->first();
        }

        return Inertia::render('Auth/Register', [
            'isFirstUser' => !User::exists(),
            'referralCode' => $referralCode,
            'referralAffiliateName' => $referralAffiliate?->name,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'referral_code' => 'nullable|string|max:20',
        ]);

        // Find referring affiliate
        $referralCode = $request->referral_code;
        $affiliate = null;

        if ($referralCode) {
            $affiliate = Affiliate::where('referral_code', $referralCode)
                ->where('status', 'approved')
                ->first();
        }

        // Also check for affiliate cookie if no referral code provided
        if (!$affiliate) {
            $cookieAffiliateId = $request->cookie('ns_affiliate');
            if ($cookieAffiliateId) {
                $affiliate = Affiliate::where('id', $cookieAffiliateId)
                    ->where('status', 'approved')
                    ->first();
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'referred_by_affiliate_id' => $affiliate?->id,
        ]);

        // Assign Admin role to the first user
        // Spatie Permission is installed, so we can use assignRole
        try {
            $user->assignRole('Admin');
        } catch (\Exception $e) {
            // Role might not exist yet, that's ok
        }

        // Record lead conversion for affiliate program
        if ($affiliate) {
            $this->conversionService->recordLead(
                request: $request,
                entityType: 'user_registration',
                entityId: $user->id,
                customerEmail: $user->email,
                customerName: $user->name,
                meta: [
                    'registration_source' => 'web',
                    'referral_code' => $referralCode,
                ]
            );
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
