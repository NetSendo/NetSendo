<?php

namespace App\Http\Controllers;

use App\Models\LeadScoringRule;
use App\Models\LeadScoreHistory;
use App\Models\CrmContact;
use App\Services\LeadScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LeadScoringController extends Controller
{
    public function __construct(
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Display scoring rules configuration page.
     */
    public function index()
    {
        $rules = LeadScoringRule::forUser(Auth::id())
            ->orderBy('event_type')
            ->orderByDesc('priority')
            ->get();

        $eventTypes = LeadScoringRule::EVENT_TYPES;
        $operators = LeadScoringRule::CONDITION_OPERATORS;

        return Inertia::render('Crm/Settings/ScoringRules', [
            'rules' => $rules,
            'eventTypes' => $eventTypes,
            'operators' => $operators,
        ]);
    }

    /**
     * Store a new scoring rule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:' . implode(',', array_keys(LeadScoringRule::EVENT_TYPES)),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'points' => 'required|integer|min:-100|max:100',
            'condition_field' => 'nullable|string|max:100',
            'condition_operator' => 'nullable|string|in:' . implode(',', array_keys(LeadScoringRule::CONDITION_OPERATORS)),
            'condition_value' => 'nullable|string|max:255',
            'cooldown_minutes' => 'nullable|integer|min:0|max:43200',
            'max_daily_occurrences' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        $rule = LeadScoringRule::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        // Clear rules cache
        $this->scoringService->clearRulesCache(Auth::id());

        return back()->with('success', 'Reguła scoringu została dodana.');
    }

    /**
     * Update an existing scoring rule.
     */
    public function update(Request $request, LeadScoringRule $rule)
    {
        // Ensure user owns this rule
        if ($rule->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'event_type' => 'required|string|in:' . implode(',', array_keys(LeadScoringRule::EVENT_TYPES)),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'points' => 'required|integer|min:-100|max:100',
            'condition_field' => 'nullable|string|max:100',
            'condition_operator' => 'nullable|string|in:' . implode(',', array_keys(LeadScoringRule::CONDITION_OPERATORS)),
            'condition_value' => 'nullable|string|max:255',
            'cooldown_minutes' => 'nullable|integer|min:0|max:43200',
            'max_daily_occurrences' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        $rule->update($validated);

        // Clear rules cache
        $this->scoringService->clearRulesCache(Auth::id());

        return back()->with('success', 'Reguła scoringu została zaktualizowana.');
    }

    /**
     * Delete a scoring rule.
     */
    public function destroy(LeadScoringRule $rule)
    {
        // Ensure user owns this rule
        if ($rule->user_id !== Auth::id()) {
            abort(403);
        }

        $rule->delete();

        // Clear rules cache
        $this->scoringService->clearRulesCache(Auth::id());

        return back()->with('success', 'Reguła scoringu została usunięta.');
    }

    /**
     * Toggle rule active state.
     */
    public function toggle(LeadScoringRule $rule)
    {
        // Ensure user owns this rule
        if ($rule->user_id !== Auth::id()) {
            abort(403);
        }

        $rule->update(['is_active' => !$rule->is_active]);

        // Clear rules cache
        $this->scoringService->clearRulesCache(Auth::id());

        return back()->with('success', 'Status reguły został zmieniony.');
    }

    /**
     * Reset rules to defaults.
     */
    public function resetDefaults()
    {
        // Delete all existing rules for user
        LeadScoringRule::where('user_id', Auth::id())->delete();

        // Seed default rules
        LeadScoringRule::seedDefaultsForUser(Auth::id());

        // Clear rules cache
        $this->scoringService->clearRulesCache(Auth::id());

        return back()->with('success', 'Reguły scoringu zostały zresetowane do domyślnych.');
    }

    /**
     * Get score history for a specific contact.
     */
    public function contactHistory(CrmContact $contact)
    {
        // Ensure user owns this contact
        if ($contact->user_id !== Auth::id()) {
            abort(403);
        }

        $history = $contact->scoreHistory()
            ->with('rule:id,name,event_type')
            ->take(50)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'event_type' => $item->event_type,
                'event_label' => $item->event_label,
                'points_change' => $item->points_change,
                'formatted_points' => $item->formatted_points,
                'score_before' => $item->score_before,
                'score_after' => $item->score_after,
                'rule_name' => $item->rule?->name,
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'history' => $history,
            'current_score' => $contact->score,
            'trend' => $contact->getScoreTrend(),
        ]);
    }

    /**
     * Get scoring analytics.
     */
    public function analytics(Request $request)
    {
        $days = $request->input('days', 30);
        $analytics = $this->scoringService->getAnalytics(Auth::id(), $days);

        return response()->json($analytics);
    }
}
