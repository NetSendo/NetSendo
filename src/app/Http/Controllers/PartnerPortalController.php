<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateCoupon;
use App\Models\AffiliateLink;
use App\Models\AffiliateOffer;
use App\Models\AffiliateProgram;
use App\Services\AffiliateCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PartnerPortalController extends Controller
{
    public function __construct(
        private AffiliateCommissionService $commissionService
    ) {}

    /**
     * Partner dashboard.
     */
    public function dashboard()
    {
        $affiliate = Auth::guard('affiliate')->user();

        $stats = [
            'total_clicks' => $affiliate->clicks()->count(),
            'today_clicks' => $affiliate->clicks()->whereDate('created_at', today())->count(),
            'total_leads' => $affiliate->conversions()->where('type', 'lead')->count(),
            'total_sales' => $affiliate->conversions()->where('type', 'purchase')->count(),
            'total_revenue' => $affiliate->conversions()->where('type', 'purchase')->sum('amount'),
            'total_earned' => $affiliate->commissions()->sum('commission_amount'),
            'pending_earned' => $affiliate->commissions()->pending()->sum('commission_amount'),
            'payable_earned' => $affiliate->commissions()->payable()->sum('commission_amount'),
            'paid_earned' => $affiliate->commissions()->paid()->sum('commission_amount'),
        ];

        // Recent activity
        $recentConversions = $affiliate->conversions()
            ->with('offer')
            ->latest()
            ->limit(5)
            ->get();

        // Click chart (last 30 days)
        $clickChart = $affiliate->clicks()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        return Inertia::render('Partner/Dashboard', [
            'affiliate' => $affiliate,
            'program' => $affiliate->program,
            'stats' => $stats,
            'recentConversions' => $recentConversions,
            'clickChart' => $clickChart,
        ]);
    }

    /**
     * Available offers marketplace.
     */
    public function offers()
    {
        $affiliate = Auth::guard('affiliate')->user();
        $program = $affiliate->program;

        $offers = AffiliateOffer::where('program_id', $program->id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->get()
            ->map(function ($offer) use ($affiliate) {
                $link = $affiliate->links()->where('offer_id', $offer->id)->first();
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'image_url' => $offer->image_url,
                    'commission_type' => $offer->commission_type,
                    'commission_value' => $offer->commission_value,
                    'has_link' => $link !== null,
                    'link_code' => $link?->code,
                ];
            });

        return Inertia::render('Partner/Offers/Index', [
            'offers' => $offers,
            'program' => $program,
        ]);
    }

    /**
     * Single offer details.
     */
    public function offerShow(AffiliateOffer $offer)
    {
        $affiliate = Auth::guard('affiliate')->user();

        // Verify offer belongs to affiliate's program
        if ($offer->program_id !== $affiliate->program_id) {
            abort(403);
        }

        // Get or create link
        $link = $affiliate->getLinkForOffer($offer);

        return Inertia::render('Partner/Offers/Show', [
            'offer' => $offer,
            'link' => [
                'id' => $link->id,
                'code' => $link->code,
                'tracking_url' => $link->tracking_url,
                'redirect_url' => $link->redirect_url,
                'clicks_count' => $link->clicks_count,
            ],
        ]);
    }

    /**
     * Affiliate's tracking links.
     */
    public function links()
    {
        $affiliate = Auth::guard('affiliate')->user();

        $links = $affiliate->links()
            ->with('offer')
            ->withCount('clicks')
            ->get()
            ->map(fn($link) => [
                'id' => $link->id,
                'code' => $link->code,
                'offer_name' => $link->offer->name,
                'tracking_url' => $link->tracking_url,
                'redirect_url' => $link->redirect_url,
                'clicks_count' => $link->clicks_count,
                'created_at' => $link->created_at,
            ]);

        return Inertia::render('Partner/Links/Index', [
            'links' => $links,
        ]);
    }

    /**
     * Generate a new tracking link for an offer.
     */
    public function generateLink(Request $request)
    {
        $validated = $request->validate([
            'offer_id' => 'required|integer|exists:affiliate_offers,id',
        ]);

        $affiliate = Auth::guard('affiliate')->user();
        $offer = AffiliateOffer::find($validated['offer_id']);

        if ($offer->program_id !== $affiliate->program_id) {
            return back()->withErrors(['error' => 'Invalid offer']);
        }

        $link = $affiliate->getLinkForOffer($offer);

        return back()->with('success', __('affiliate.link_generated'));
    }

    /**
     * Affiliate's coupon codes.
     */
    public function coupons()
    {
        $affiliate = Auth::guard('affiliate')->user();

        $coupons = $affiliate->coupons()
            ->with('offer')
            ->get()
            ->map(fn($coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'offer_name' => $coupon->offer?->name ?? 'All Offers',
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'formatted_discount' => $coupon->formatted_discount,
                'is_valid' => $coupon->is_valid,
                'usage_count' => $coupon->usage_count,
                'usage_limit' => $coupon->usage_limit,
                'ends_at' => $coupon->ends_at,
            ]);

        return Inertia::render('Partner/Coupons/Index', [
            'coupons' => $coupons,
        ]);
    }

    /**
     * Commission history.
     */
    public function commissions(Request $request)
    {
        $affiliate = Auth::guard('affiliate')->user();

        $query = $affiliate->commissions()
            ->with(['offer', 'conversion']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $commissions = $query->orderByDesc('created_at')->paginate(20);

        $summary = $this->commissionService->getAffiliateSummary($affiliate->id);

        return Inertia::render('Partner/Commissions/Index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Payout settings and history.
     */
    public function payouts()
    {
        $affiliate = Auth::guard('affiliate')->user();

        $payouts = $affiliate->payouts()
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Partner/Payouts/Index', [
            'affiliate' => $affiliate->only(['payout_method', 'payout_details']),
            'payouts' => $payouts,
        ]);
    }

    /**
     * Update payout settings.
     */
    public function updatePayoutSettings(Request $request)
    {
        $validated = $request->validate([
            'payout_method' => 'required|in:manual,paypal,bank,stripe',
            'payout_details' => 'array',
            'payout_details.paypal_email' => 'nullable|email',
            'payout_details.bank_name' => 'nullable|string|max:255',
            'payout_details.bank_account' => 'nullable|string|max:255',
            'payout_details.bank_swift' => 'nullable|string|max:20',
        ]);

        $affiliate = Auth::guard('affiliate')->user();
        $affiliate->update($validated);

        return back()->with('success', __('affiliate.payout_settings_updated'));
    }

    /**
     * Marketing assets.
     */
    public function assets()
    {
        $affiliate = Auth::guard('affiliate')->user();
        $program = $affiliate->program;

        // Get program assets from settings
        $assets = $program->settings['assets'] ?? [];

        return Inertia::render('Partner/Assets/Index', [
            'assets' => $assets,
            'program' => $program,
        ]);
    }

    /**
     * Update affiliate profile.
     */
    public function updateProfile(Request $request)
    {
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
        ]);

        $affiliate->update($validated);

        return back()->with('success', __('affiliate.profile_updated'));
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $affiliate = Auth::guard('affiliate')->user();

        if (!Hash::check($validated['current_password'], $affiliate->password)) {
            return back()->withErrors(['current_password' => 'Invalid current password']);
        }

        $affiliate->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', __('affiliate.password_updated'));
    }
}
