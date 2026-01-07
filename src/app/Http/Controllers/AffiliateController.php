<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateCoupon;
use App\Models\AffiliateLink;
use App\Models\AffiliateOffer;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\ExternalPage;
use App\Models\PolarProduct;
use App\Models\SalesFunnel;
use App\Models\StripeProduct;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliatePayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AffiliateController extends Controller
{
    public function __construct(
        private AffiliateCommissionService $commissionService,
        private AffiliatePayoutService $payoutService
    ) {}

    /**
     * Main affiliate dashboard.
     */
    public function index()
    {
        $user = Auth::user();

        $program = AffiliateProgram::forUser($user->id)->first();

        // If no program exists, show setup page
        if (!$program) {
            return Inertia::render('Profit/Affiliate/Index', [
                'hasProgram' => false,
                'program' => null,
                'stats' => null,
            ]);
        }

        // Get dashboard stats
        $stats = $this->getDashboardStats($program);

        return Inertia::render('Profit/Affiliate/Index', [
            'hasProgram' => true,
            'program' => $program,
            'stats' => $stats,
        ]);
    }

    /**
     * Get dashboard statistics.
     */
    private function getDashboardStats(AffiliateProgram $program): array
    {
        $offersIds = $program->offers()->pluck('id');

        $totalClicks = AffiliateClick::whereIn('offer_id', $offersIds)->count();
        $totalLeads = AffiliateConversion::whereIn('offer_id', $offersIds)->where('type', 'lead')->count();
        $totalPurchases = AffiliateConversion::whereIn('offer_id', $offersIds)->where('type', 'purchase')->count();
        $totalRevenue = AffiliateConversion::whereIn('offer_id', $offersIds)->where('type', 'purchase')->sum('amount');
        $totalCommissions = AffiliateCommission::whereIn('offer_id', $offersIds)->sum('commission_amount');
        $pendingCommissions = AffiliateCommission::whereIn('offer_id', $offersIds)->where('status', 'pending')->sum('commission_amount');
        $paidCommissions = AffiliateCommission::whereIn('offer_id', $offersIds)->where('status', 'paid')->sum('commission_amount');

        // Top affiliates
        $topAffiliates = Affiliate::forProgram($program->id)
            ->approved()
            ->withCount(['conversions' => fn($q) => $q->where('type', 'purchase')])
            ->withSum(['commissions' => fn($q) => $q->where('status', '!=', 'rejected')], 'commission_amount')
            ->orderByDesc('commissions_sum_commission_amount')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
                'conversions' => $a->conversions_count,
                'earnings' => $a->commissions_sum_commission_amount ?? 0,
            ]);

        // Revenue over last 30 days
        $revenueChart = AffiliateConversion::whereIn('offer_id', $offersIds)
            ->where('type', 'purchase')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        return [
            'total_clicks' => $totalClicks,
            'total_leads' => $totalLeads,
            'total_purchases' => $totalPurchases,
            'total_revenue' => $totalRevenue,
            'total_commissions' => $totalCommissions,
            'pending_commissions' => $pendingCommissions,
            'paid_commissions' => $paidCommissions,
            'active_affiliates' => $program->affiliates()->approved()->count(),
            'pending_affiliates' => $program->affiliates()->pending()->count(),
            'conversion_rate' => $totalClicks > 0 ? round(($totalPurchases / $totalClicks) * 100, 2) : 0,
            'epc' => $totalClicks > 0 ? round($totalRevenue / $totalClicks, 2) : 0,
            'top_affiliates' => $topAffiliates,
            'revenue_chart' => $revenueChart,
        ];
    }

    // ==================== PROGRAMS ====================

    public function programsIndex()
    {
        $user = Auth::user();
        $programs = AffiliateProgram::forUser($user->id)
            ->withCount(['affiliates', 'offers'])
            ->get();

        return Inertia::render('Profit/Affiliate/Programs/Index', [
            'programs' => $programs,
        ]);
    }

    public function programsCreate()
    {
        return Inertia::render('Profit/Affiliate/Programs/Create');
    }

    public function programsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'terms_text' => 'nullable|string',
            'cookie_days' => 'required|integer|min:1|max:365',
            'currency' => 'required|string|size:3',
            'default_commission_percent' => 'required|numeric|min:0|max:100',
            'auto_approve_affiliates' => 'boolean',
        ]);

        $program = AffiliateProgram::create([
            'user_id' => Auth::id(),
            ...$validated,
            'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
            'status' => 'active',
        ]);

        return redirect()->route('affiliate.programs.index')
            ->with('success', __('affiliate.program_created'));
    }

    public function programsEdit(AffiliateProgram $program)
    {
        $this->authorize('update', $program);

        return Inertia::render('Profit/Affiliate/Programs/Edit', [
            'program' => $program,
        ]);
    }

    public function programsUpdate(Request $request, AffiliateProgram $program)
    {
        $this->authorize('update', $program);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:active,paused,closed',
            'terms_text' => 'nullable|string',
            'cookie_days' => 'required|integer|min:1|max:365',
            'currency' => 'required|string|size:3',
            'default_commission_percent' => 'required|numeric|min:0|max:100',
            'auto_approve_affiliates' => 'boolean',
        ]);

        $program->update($validated);

        return redirect()->route('affiliate.programs.index')
            ->with('success', __('affiliate.program_updated'));
    }

    public function programsDestroy(AffiliateProgram $program)
    {
        $this->authorize('delete', $program);
        $program->delete();

        return redirect()->route('affiliate.programs.index')
            ->with('success', __('affiliate.program_deleted'));
    }

    // ==================== OFFERS ====================

    public function offersIndex(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        $offers = AffiliateOffer::where('program_id', $program->id)
            ->withCount(['conversions', 'clicks'])
            ->paginate(20);

        return Inertia::render('Profit/Affiliate/Offers/Index', [
            'offers' => $offers,
            'program' => $program,
        ]);
    }

    public function offersCreate()
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        // Get available entities to link
        $funnels = SalesFunnel::forUser($user->id)->active()->get(['id', 'name']);
        $landingPages = ExternalPage::where('user_id', $user->id)->get(['id', 'name']);
        $stripeProducts = StripeProduct::forUser($user->id)->active()->get(['id', 'name', 'price', 'currency']);
        $polarProducts = PolarProduct::where('user_id', $user->id)->get(['id', 'name', 'price', 'currency']);

        return Inertia::render('Profit/Affiliate/Offers/Create', [
            'program' => $program,
            'funnels' => $funnels,
            'landingPages' => $landingPages,
            'stripeProducts' => $stripeProducts,
            'polarProducts' => $polarProducts,
        ]);
    }

    public function offersStore(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:funnel,landing,stripe_product,polar_product,external',
            'entity_id' => 'nullable|integer',
            'external_url' => 'nullable|url',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ]);

        AffiliateOffer::create([
            'program_id' => $program->id,
            ...$validated,
        ]);

        return redirect()->route('affiliate.offers.index')
            ->with('success', __('affiliate.offer_created'));
    }

    public function offersEdit(AffiliateOffer $offer)
    {
        $this->authorize('update', $offer);
        $user = Auth::user();

        $funnels = SalesFunnel::forUser($user->id)->active()->get(['id', 'name']);
        $landingPages = ExternalPage::where('user_id', $user->id)->get(['id', 'name']);
        $stripeProducts = StripeProduct::forUser($user->id)->active()->get(['id', 'name', 'price', 'currency']);
        $polarProducts = PolarProduct::where('user_id', $user->id)->get(['id', 'name', 'price', 'currency']);

        return Inertia::render('Profit/Affiliate/Offers/Edit', [
            'offer' => $offer,
            'funnels' => $funnels,
            'landingPages' => $landingPages,
            'stripeProducts' => $stripeProducts,
            'polarProducts' => $polarProducts,
        ]);
    }

    public function offersUpdate(Request $request, AffiliateOffer $offer)
    {
        $this->authorize('update', $offer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:funnel,landing,stripe_product,polar_product,external',
            'entity_id' => 'nullable|integer',
            'external_url' => 'nullable|url',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $offer->update($validated);

        return redirect()->route('affiliate.offers.index')
            ->with('success', __('affiliate.offer_updated'));
    }

    public function offersDestroy(AffiliateOffer $offer)
    {
        $this->authorize('delete', $offer);
        $offer->delete();

        return redirect()->route('affiliate.offers.index')
            ->with('success', __('affiliate.offer_deleted'));
    }

    // ==================== AFFILIATES ====================

    public function affiliatesIndex(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        $query = Affiliate::forProgram($program->id)
            ->withCount(['conversions', 'clicks'])
            ->withSum(['commissions' => fn($q) => $q->where('status', '!=', 'rejected')], 'commission_amount');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $affiliates = $query->orderByDesc('created_at')->paginate(20);

        return Inertia::render('Profit/Affiliate/Affiliates/Index', [
            'affiliates' => $affiliates,
            'program' => $program,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function affiliatesShow(Affiliate $affiliate)
    {
        $this->authorize('view', $affiliate);

        $affiliate->load(['parent', 'children']);

        $stats = [
            'clicks' => $affiliate->clicks()->count(),
            'leads' => $affiliate->conversions()->where('type', 'lead')->count(),
            'purchases' => $affiliate->conversions()->where('type', 'purchase')->count(),
            'total_revenue' => $affiliate->conversions()->where('type', 'purchase')->sum('amount'),
            'total_earned' => $affiliate->commissions()->sum('commission_amount'),
            'pending' => $affiliate->commissions()->pending()->sum('commission_amount'),
            'paid' => $affiliate->commissions()->paid()->sum('commission_amount'),
        ];

        $recentConversions = $affiliate->conversions()
            ->with('offer')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Profit/Affiliate/Affiliates/Show', [
            'affiliate' => $affiliate,
            'stats' => $stats,
            'recentConversions' => $recentConversions,
        ]);
    }

    public function affiliatesApprove(Affiliate $affiliate)
    {
        $this->authorize('update', $affiliate);
        $affiliate->approve();

        return back()->with('success', __('affiliate.affiliate_approved'));
    }

    public function affiliatesBlock(Affiliate $affiliate)
    {
        $this->authorize('update', $affiliate);
        $affiliate->block();

        return back()->with('success', __('affiliate.affiliate_blocked'));
    }

    // ==================== CONVERSIONS ====================

    public function conversionsIndex(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();
        $offerIds = $program->offers()->pluck('id');

        $query = AffiliateConversion::whereIn('offer_id', $offerIds)
            ->with(['affiliate', 'offer']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $conversions = $query->orderByDesc('created_at')->paginate(30);

        return Inertia::render('Profit/Affiliate/Conversions/Index', [
            'conversions' => $conversions,
            'program' => $program,
            'filters' => $request->only(['type', 'affiliate_id', 'date_from', 'date_to']),
        ]);
    }

    // ==================== COMMISSIONS ====================

    public function commissionsIndex(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();
        $offerIds = $program->offers()->pluck('id');

        $query = AffiliateCommission::whereIn('offer_id', $offerIds)
            ->with(['affiliate', 'offer', 'conversion']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }

        $commissions = $query->orderByDesc('created_at')->paginate(30);

        $summary = [
            'pending' => AffiliateCommission::whereIn('offer_id', $offerIds)->pending()->sum('commission_amount'),
            'approved' => AffiliateCommission::whereIn('offer_id', $offerIds)->approved()->sum('commission_amount'),
            'payable' => AffiliateCommission::whereIn('offer_id', $offerIds)->payable()->sum('commission_amount'),
            'paid' => AffiliateCommission::whereIn('offer_id', $offerIds)->paid()->sum('commission_amount'),
        ];

        return Inertia::render('Profit/Affiliate/Commissions/Index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'program' => $program,
            'filters' => $request->only(['status', 'affiliate_id']),
        ]);
    }

    public function commissionsApprove(AffiliateCommission $commission)
    {
        $this->authorize('update', $commission);
        $this->commissionService->approveCommission($commission->id);

        return back()->with('success', __('affiliate.commission_approved'));
    }

    public function commissionsReject(Request $request, AffiliateCommission $commission)
    {
        $this->authorize('update', $commission);
        $validated = $request->validate(['reason' => 'required|string|max:255']);
        $this->commissionService->rejectCommission($commission->id, $validated['reason']);

        return back()->with('success', __('affiliate.commission_rejected'));
    }

    public function commissionsBulkApprove(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $count = $this->commissionService->bulkApprove($validated['ids']);

        return back()->with('success', __('affiliate.commissions_approved', ['count' => $count]));
    }

    public function commissionsMakePayable(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();
        $count = $this->commissionService->makeCommissionsPayable($program->id);

        return back()->with('success', __('affiliate.commissions_made_payable', ['count' => $count]));
    }

    // ==================== PAYOUTS ====================

    public function payoutsIndex(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        $payouts = AffiliatePayout::where('program_id', $program->id)
            ->with('affiliate')
            ->orderByDesc('created_at')
            ->paginate(20);

        $summary = $this->payoutService->getProgramPayoutSummary($program->id);
        $payableByAffiliate = $this->payoutService->getPayableByAffiliate($program->id);

        return Inertia::render('Profit/Affiliate/Payouts/Index', [
            'payouts' => $payouts,
            'summary' => $summary,
            'payableByAffiliate' => $payableByAffiliate,
            'program' => $program,
        ]);
    }

    public function payoutsCreate()
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        $payableByAffiliate = $this->payoutService->getPayableByAffiliate($program->id);

        return Inertia::render('Profit/Affiliate/Payouts/Create', [
            'program' => $program,
            'payableByAffiliate' => $payableByAffiliate,
        ]);
    }

    public function payoutsStore(Request $request)
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'affiliate_id' => 'nullable|integer|exists:affiliates,id',
        ]);

        try {
            $payout = $this->payoutService->createPayout(
                $program->id,
                $validated['period_start'],
                $validated['period_end'],
                $validated['affiliate_id'] ?? null
            );

            return redirect()->route('affiliate.payouts.index')
                ->with('success', __('affiliate.payout_created'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function payoutsComplete(Request $request, AffiliatePayout $payout)
    {
        $this->authorize('update', $payout);

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $this->payoutService->completePayout($payout->id, $validated['payment_reference'] ?? null);

        return back()->with('success', __('affiliate.payout_completed'));
    }

    public function payoutsExport(AffiliatePayout $payout)
    {
        $this->authorize('view', $payout);
        return $this->payoutService->exportToCsv($payout->id);
    }

    public function payoutsExportPayable()
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->firstOrFail();
        return $this->payoutService->exportPayableCommissions($program->id);
    }

    // ==================== API ENDPOINTS ====================

    public function apiStats()
    {
        $user = Auth::user();
        $program = AffiliateProgram::forUser($user->id)->first();

        if (!$program) {
            return response()->json(['error' => 'No program found'], 404);
        }

        return response()->json($this->getDashboardStats($program));
    }
}
