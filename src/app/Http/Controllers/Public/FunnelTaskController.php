<?php

namespace App\Http\Controllers\Public;

use App\Models\FunnelTask;
use App\Models\Funnel;
use App\Models\Subscriber;
use App\Models\FunnelSubscriber;
use App\Services\Funnels\FunnelRetryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class FunnelTaskController extends Controller
{
    public function __construct(
        protected FunnelRetryService $retryService
    ) {}

    /**
     * Mark a task as completed for a subscriber.
     *
     * This endpoint is used by external systems (quiz platforms, forms, etc.)
     * to notify NetSendo that a subscriber has completed a task.
     *
     * POST /funnel/task/complete
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => 'required|string|max:255',
            'subscriber_email' => 'required|email',
            'funnel_id' => 'nullable|integer',
            'funnel_slug' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        // Find subscriber by email
        $subscriber = Subscriber::where('email', $validated['subscriber_email'])->first();

        if (!$subscriber) {
            Log::warning("Task completion attempted for unknown subscriber: {$validated['subscriber_email']}");
            return response()->json([
                'success' => false,
                'error' => 'subscriber_not_found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        // Find funnel - either by ID, slug, or get all active funnels
        $funnels = collect();

        if (!empty($validated['funnel_id'])) {
            $funnel = Funnel::find($validated['funnel_id']);
            if ($funnel) {
                $funnels->push($funnel);
            }
        } elseif (!empty($validated['funnel_slug'])) {
            $funnel = Funnel::where('slug', $validated['funnel_slug'])->first();
            if ($funnel) {
                $funnels->push($funnel);
            }
        } else {
            // Mark task for all active funnels the subscriber is enrolled in
            $funnels = FunnelSubscriber::where('subscriber_id', $subscriber->id)
                ->whereIn('status', [
                    FunnelSubscriber::STATUS_ACTIVE,
                    FunnelSubscriber::STATUS_WAITING,
                    FunnelSubscriber::STATUS_WAITING_CONDITION,
                ])
                ->with('funnel')
                ->get()
                ->pluck('funnel')
                ->filter();
        }

        if ($funnels->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'no_active_funnels',
                'message' => 'No active funnels found for this subscriber',
            ], 404);
        }

        $completedCount = 0;

        foreach ($funnels as $funnel) {
            // Mark task as completed
            FunnelTask::markCompleted(
                $funnel->id,
                $subscriber->id,
                $validated['task_id'],
                $validated['metadata'] ?? []
            );

            $completedCount++;

            Log::info("Task '{$validated['task_id']}' marked as completed for subscriber {$subscriber->id} in funnel {$funnel->id}");
        }

        return response()->json([
            'success' => true,
            'message' => "Task marked as completed in {$completedCount} funnel(s)",
            'data' => [
                'task_id' => $validated['task_id'],
                'subscriber_email' => $validated['subscriber_email'],
                'funnels_affected' => $completedCount,
            ],
        ]);
    }

    /**
     * Check task completion status.
     *
     * GET /funnel/task/status
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => 'required|string',
            'subscriber_email' => 'required|email',
            'funnel_id' => 'nullable|integer',
        ]);

        $subscriber = Subscriber::where('email', $validated['subscriber_email'])->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'error' => 'subscriber_not_found',
            ], 404);
        }

        $query = FunnelTask::forSubscriber($subscriber->id)
            ->forTask($validated['task_id']);

        if (!empty($validated['funnel_id'])) {
            $query->forFunnel($validated['funnel_id']);
        }

        $task = $query->first();

        return response()->json([
            'success' => true,
            'data' => [
                'completed' => $task !== null,
                'completed_at' => $task?->completed_at?->toIso8601String(),
                'metadata' => $task?->metadata,
            ],
        ]);
    }
}
