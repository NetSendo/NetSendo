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

        // 30-day stats for dashboard cards
        $thirtyDaysAgo = now()->subDays(30);
        $stats30d = [
            'clicks' => $affiliate->clicks()->where('created_at', '>=', $thirtyDaysAgo)->count(),
            'leads' => $affiliate->conversions()->where('type', 'lead')->where('created_at', '>=', $thirtyDaysAgo)->count(),
            'sales' => $affiliate->conversions()->where('type', 'purchase')->where('created_at', '>=', $thirtyDaysAgo)->count(),
            'earnings' => $affiliate->commissions()->where('created_at', '>=', $thirtyDaysAgo)->sum('commission_amount'),
            'pending' => $stats['pending_earned'],
            'payable' => $stats['payable_earned'],
            'paid' => $stats['paid_earned'],
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

        // Referral tools
        $referralUrl = route('affiliate.referral', ['code' => $affiliate->referral_code]);
        $referredUsersCount = \App\Models\User::where('referred_by_affiliate_id', $affiliate->id)->count();

        return Inertia::render('Partner/Dashboard', [
            'affiliate' => $affiliate,
            'program' => $affiliate->program,
            'stats' => $stats30d,
            'recentConversions' => $recentConversions,
            'clickChart' => $clickChart,
            'referralUrl' => $referralUrl,
            'referralCode' => $affiliate->referral_code,
            'referredUsersCount' => $referredUsersCount,
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
     * Partner team tree (MLM structure).
     */
    public function team()
    {
        $affiliate = Auth::guard('affiliate')->user();
        $program = $affiliate->program;

        // Get direct children (level 1)
        $directPartners = $affiliate->children()
            ->with(['children' => function ($query) {
                $query->withCount(['conversions', 'clicks'])
                    ->withSum('commissions', 'commission_amount');
            }])
            ->withCount(['conversions', 'clicks'])
            ->withSum('commissions', 'commission_amount')
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'email' => $partner->email,
                    'referral_code' => $partner->referral_code,
                    'joined_at' => $partner->joined_at,
                    'status' => $partner->status,
                    'clicks' => $partner->clicks_count ?? 0,
                    'conversions' => $partner->conversions_count ?? 0,
                    'earnings' => $partner->commissions_sum_commission_amount ?? 0,
                    'children' => $partner->children->map(fn($child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'email' => $child->email,
                        'referral_code' => $child->referral_code,
                        'joined_at' => $child->joined_at,
                        'status' => $child->status,
                        'clicks' => $child->clicks_count ?? 0,
                        'conversions' => $child->conversions_count ?? 0,
                        'earnings' => $child->commissions_sum_commission_amount ?? 0,
                    ]),
                ];
            });

        // Calculate team stats
        $allTeamIds = $this->getAllDescendantIds($affiliate->id);
        $teamStats = [
            'total_partners' => count($allTeamIds),
            'direct_partners' => $affiliate->children()->count(),
            'total_clicks' => \App\Models\AffiliateClick::whereIn('affiliate_id', $allTeamIds)->count(),
            'total_conversions' => \App\Models\AffiliateConversion::whereIn('affiliate_id', $allTeamIds)->count(),
            'total_earnings' => \App\Models\AffiliateCommission::whereIn('affiliate_id', $allTeamIds)->sum('commission_amount'),
        ];

        return Inertia::render('Partner/Team', [
            'affiliate' => $affiliate,
            'program' => $program,
            'directPartners' => $directPartners,
            'teamStats' => $teamStats,
            'referralUrl' => route('partner.register', ['program' => $program->slug, 'ref' => $affiliate->referral_code]),
        ]);
    }

    /**
     * Get all descendant affiliate IDs recursively.
     */
    private function getAllDescendantIds(int $affiliateId): array
    {
        $ids = [];
        $children = Affiliate::where('parent_affiliate_id', $affiliateId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllDescendantIds($childId));
        }

        return $ids;
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
