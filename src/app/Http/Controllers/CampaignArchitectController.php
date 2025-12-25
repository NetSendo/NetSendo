<?php

namespace App\Http\Controllers;

use App\Models\CampaignPlan;
use App\Models\CampaignPlanStep;
use App\Models\CampaignBenchmark;
use App\Models\ContactList;
use App\Services\CampaignArchitectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CampaignArchitectController extends Controller
{
    public function __construct(
        protected CampaignArchitectService $architectService
    ) {}

    /**
     * Display the main Campaign Architect page
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get existing plans
        $plans = CampaignPlan::forUser($user->id)
            ->with('steps')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'status' => $plan->status,
                    'industry' => $plan->industry,
                    'campaign_goal' => $plan->campaign_goal,
                    'total_messages' => $plan->total_messages,
                    'timeline_days' => $plan->timeline_days,
                    'created_at' => $plan->created_at->diffForHumans(),
                    'updated_at' => $plan->updated_at->diffForHumans(),
                ];
            });

        // Get available lists for audience selection
        $lists = ContactList::forUser($user->id)
            ->select('id', 'name', 'type')
            ->withCount(['subscribers' => function ($query) {
                $query->where('contact_list_subscriber.status', 'active');
            }])
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'type' => $list->type,
                    'subscribers_count' => $list->subscribers_count,
                ];
            });

        // Get current license plan
        $licensePlan = \App\Models\Setting::where('key', 'license_plan')->first();
        $currentPlan = $licensePlan ? $licensePlan->value : 'SILVER';

        return Inertia::render('CampaignArchitect/Index', [
            'plans' => $plans,
            'lists' => $lists,
            'industries' => CampaignPlan::getIndustries(),
            'businessModels' => CampaignPlan::getBusinessModels(),
            'campaignGoals' => CampaignPlan::getCampaignGoals(),
            'languages' => CampaignPlan::getLanguages(),
            'messageTypes' => CampaignPlanStep::getMessageTypes(),
            'licensePlan' => $currentPlan,
        ]);
    }

    /**
     * Store a new campaign plan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'required|string',
            'business_model' => 'required|string',
            'campaign_goal' => 'required|string',
            'campaign_language' => 'required|string|in:en,pl,de,es,fr,it,pt,nl,sv,cs,uk,ru',
            'average_order_value' => 'nullable|numeric|min:0',
            'margin_percent' => 'nullable|numeric|min:0|max:100',
            'decision_cycle_days' => 'nullable|integer|min:1',
            'selected_lists' => 'required|array|min:1',
            'selected_lists.*' => 'exists:contact_lists,id',
        ]);

        // Get audience snapshot
        $audienceSnapshot = $this->architectService->getAudienceData($validated['selected_lists']);

        $plan = CampaignPlan::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'industry' => $validated['industry'],
            'business_model' => $validated['business_model'],
            'campaign_goal' => $validated['campaign_goal'],
            'campaign_language' => $validated['campaign_language'],
            'average_order_value' => $validated['average_order_value'] ?? 100,
            'margin_percent' => $validated['margin_percent'] ?? 30,
            'decision_cycle_days' => $validated['decision_cycle_days'] ?? 7,
            'selected_lists' => $validated['selected_lists'],
            'audience_snapshot' => $audienceSnapshot,
            'status' => 'draft',
        ]);

        return response()->json([
            'success' => true,
            'plan' => $plan,
        ]);
    }

    /**
     * Show a specific plan
     */
    public function show(CampaignPlan $plan)
    {
        $this->authorize('view', $plan);

        $plan->load('steps');

        // Get current license plan
        $licensePlan = \App\Models\Setting::where('key', 'license_plan')->first();
        $currentPlan = $licensePlan ? $licensePlan->value : 'SILVER';

        return Inertia::render('CampaignArchitect/Show', [
            'plan' => $plan,
            'messageTypes' => CampaignPlanStep::getMessageTypes(),
            'conditionTriggers' => CampaignPlanStep::getConditionTriggers(),
            'licensePlan' => $currentPlan,
        ]);
    }

    /**
     * Update a campaign plan
     */
    public function update(Request $request, CampaignPlan $plan)
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'industry' => 'sometimes|string',
            'business_model' => 'sometimes|string',
            'campaign_goal' => 'sometimes|string',
            'average_order_value' => 'sometimes|numeric|min:0',
            'margin_percent' => 'sometimes|numeric|min:0|max:100',
            'decision_cycle_days' => 'sometimes|integer|min:1',
            'selected_lists' => 'sometimes|array|min:1',
        ]);

        $plan->update($validated);

        // Regenerate audience snapshot if lists changed
        if (isset($validated['selected_lists'])) {
            $audienceSnapshot = $this->architectService->getAudienceData($validated['selected_lists']);
            $plan->update(['audience_snapshot' => $audienceSnapshot]);
        }

        return response()->json([
            'success' => true,
            'plan' => $plan->fresh(),
        ]);
    }

    /**
     * Delete a campaign plan
     */
    public function destroy(CampaignPlan $plan)
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get audience data for selected lists
     */
    public function getAudienceData(Request $request)
    {
        $validated = $request->validate([
            'list_ids' => 'required|array|min:1',
            'list_ids.*' => 'exists:contact_lists,id',
        ]);

        $audienceData = $this->architectService->getAudienceData($validated['list_ids']);

        return response()->json($audienceData);
    }

    /**
     * Generate AI strategy for a plan
     */
    public function generateStrategy(Request $request, CampaignPlan $plan)
    {
        $this->authorize('update', $plan);

        try {
            $strategy = $this->architectService->generateStrategy($plan);

            return response()->json([
                'success' => true,
                'strategy' => $strategy,
                'plan' => $plan->fresh()->load('steps'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update forecast with slider adjustments
     */
    public function updateForecast(Request $request, CampaignPlan $plan)
    {
        $this->authorize('view', $plan);

        $validated = $request->validate([
            'message_count' => 'nullable|numeric|min:0.5|max:2',
            'timeline' => 'nullable|numeric|min:0.5|max:2',
            'audience_size' => 'nullable|numeric|min:0.1|max:2',
        ]);

        $forecast = $this->architectService->calculateForecast($plan, $validated);

        return response()->json([
            'success' => true,
            'forecast' => $forecast,
        ]);
    }

    /**
     * Export plan to NetSendo campaigns/automations
     */
    public function export(Request $request, CampaignPlan $plan)
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'mode' => 'required|in:draft,scheduled',
        ]);

        try {
            $exportedItems = $this->architectService->exportToCampaigns($plan, $validated['mode']);

            return response()->json([
                'success' => true,
                'exported_items' => $exportedItems,
                'message' => 'Campaign exported successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get benchmarks for an industry
     */
    public function getBenchmarks(Request $request)
    {
        $validated = $request->validate([
            'industry' => 'required|string',
            'campaign_type' => 'nullable|string',
        ]);

        $benchmark = CampaignBenchmark::getBenchmark(
            $validated['industry'],
            $validated['campaign_type'] ?? null
        );

        return response()->json([
            'success' => true,
            'benchmark' => $benchmark,
        ]);
    }
}
