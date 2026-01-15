<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelGoalConversion;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FunnelGoalController extends Controller
{
    /**
     * Get goal statistics for a funnel.
     */
    public function stats(Funnel $funnel): JsonResponse
    {
        $this->authorize('view', $funnel);

        $stats = FunnelGoalConversion::getStats($funnel->id);

        // Get recent conversions
        $recent = FunnelGoalConversion::forFunnel($funnel->id)
            ->with(['subscriber:id,email,first_name,last_name', 'step:id,name,goal_type'])
            ->orderByDesc('converted_at')
            ->limit(20)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'goal_name' => $c->goal_name,
                'goal_type' => $c->goal_type,
                'value' => $c->value,
                'source' => $c->source,
                'email' => $c->subscriber?->email,
                'name' => $c->subscriber?->full_name ?? $c->subscriber?->first_name,
                'step_name' => $c->step?->name,
                'converted_at' => $c->converted_at->toISOString(),
            ]);

        // Get goals breakdown by step
        $byStep = FunnelGoalConversion::forFunnel($funnel->id)
            ->with('step:id,name,goal_type,goal_value')
            ->get()
            ->groupBy('funnel_step_id')
            ->map(fn($group) => [
                'step_id' => $group->first()->funnel_step_id,
                'step_name' => $group->first()->step?->name,
                'goal_type' => $group->first()->goal_type,
                'count' => $group->count(),
                'revenue' => $group->sum('value'),
            ])
            ->values();

        return response()->json([
            'stats' => $stats,
            'recent' => $recent,
            'by_step' => $byStep,
        ]);
    }

    /**
     * Webhook endpoint for external conversion tracking.
     *
     * POST /api/funnel/goal/convert
     * {
     *   "funnel_id": 1,
     *   "subscriber_email": "user@example.com",
     *   "goal_type": "purchase",
     *   "goal_name": "Product Purchase",
     *   "value": 99.00,
     *   "metadata": { "order_id": "12345" }
     * }
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'funnel_id' => 'required|integer|exists:funnels,id',
            'subscriber_email' => 'required|email|exists:subscribers,email',
            'goal_type' => 'required|string|max:50',
            'goal_name' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ]);

        $funnel = Funnel::findOrFail($validated['funnel_id']);
        $subscriber = Subscriber::where('email', $validated['subscriber_email'])->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'error' => 'Subscriber not found',
            ], 404);
        }

        $conversion = FunnelGoalConversion::recordExternalConversion(
            $funnel,
            $subscriber,
            $validated['goal_type'],
            $validated['goal_name'],
            $validated['value'] ?? 0,
            $validated['metadata'] ?? []
        );

        if (!$conversion) {
            return response()->json([
                'success' => false,
                'error' => 'Subscriber not enrolled in funnel or already converted',
            ], 422);
        }

        Log::info("External goal conversion recorded", [
            'funnel_id' => $funnel->id,
            'subscriber_email' => $subscriber->email,
            'goal_type' => $validated['goal_type'],
            'value' => $validated['value'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'conversion_id' => $conversion->id,
            'message' => 'Goal conversion recorded successfully',
        ]);
    }

    /**
     * List all conversions for a funnel (API).
     */
    public function list(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorize('view', $funnel);

        $query = FunnelGoalConversion::forFunnel($funnel->id)
            ->with(['subscriber:id,email,first_name,last_name', 'step:id,name']);

        // Filter by goal type
        if ($request->filled('goal_type')) {
            $query->byGoalType($request->get('goal_type'));
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->fromSource($request->get('source'));
        }

        // Date range
        if ($request->filled('from') && $request->filled('to')) {
            $query->convertedBetween($request->get('from'), $request->get('to'));
        }

        $conversions = $query->orderByDesc('converted_at')->paginate(15);

        return response()->json([
            'conversions' => $conversions->items(),
            'pagination' => [
                'current_page' => $conversions->currentPage(),
                'last_page' => $conversions->lastPage(),
                'total' => $conversions->total(),
            ],
        ]);
    }
}
