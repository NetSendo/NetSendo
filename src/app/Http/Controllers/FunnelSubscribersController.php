<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FunnelSubscribersController extends Controller
{
    protected FunnelExecutionService $executionService;

    public function __construct(FunnelExecutionService $executionService)
    {
        $this->executionService = $executionService;
    }

    /**
     * List all subscribers in a funnel with pagination and filters.
     */
    public function index(Request $request, Funnel $funnel)
    {
        $this->authorize('view', $funnel);

        $query = FunnelSubscriber::forFunnel($funnel->id)
            ->with(['subscriber', 'currentStep']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search by email
        if ($request->filled('search')) {
            $query->whereHas('subscriber', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->get('search') . '%');
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'entered_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $subscribers = $query->paginate(15)->withQueryString();

        return Inertia::render('Funnels/Subscribers', [
            'funnel' => $funnel->load('steps'),
            'subscribers' => $subscribers,
            'statuses' => FunnelSubscriber::getStatuses(),
            'filters' => [
                'status' => $request->get('status'),
                'search' => $request->get('search'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    /**
     * API: Get subscribers list for AJAX requests.
     */
    public function apiList(Request $request, Funnel $funnel)
    {
        $this->authorize('view', $funnel);

        $query = FunnelSubscriber::forFunnel($funnel->id)
            ->with(['subscriber', 'currentStep']);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $query->whereHas('subscriber', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->get('search') . '%');
            });
        }

        $sortBy = $request->get('sort_by', 'entered_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $subscribers = $query->paginate(15)->withQueryString();

        return response()->json([
            'subscribers' => $subscribers->items(),
            'pagination' => [
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
                'total' => $subscribers->total(),
                'per_page' => $subscribers->perPage(),
            ],
        ]);
    }

    /**
     * Show subscriber details with history.
     */
    public function show(Funnel $funnel, FunnelSubscriber $subscriber)
    {
        $this->authorize('view', $funnel);

        if ($subscriber->funnel_id !== $funnel->id) {
            abort(404);
        }

        $subscriber->load(['subscriber', 'currentStep', 'retries']);

        return response()->json([
            'subscriber' => [
                'id' => $subscriber->id,
                'email' => $subscriber->subscriber->email,
                'name' => $subscriber->subscriber->name,
                'status' => $subscriber->status,
                'status_label' => FunnelSubscriber::getStatuses()[$subscriber->status] ?? $subscriber->status,
                'current_step' => $subscriber->currentStep ? [
                    'id' => $subscriber->currentStep->id,
                    'name' => $subscriber->currentStep->name,
                    'type' => $subscriber->currentStep->type,
                ] : null,
                'entered_at' => $subscriber->entered_at?->toIso8601String(),
                'completed_at' => $subscriber->completed_at?->toIso8601String(),
                'next_action_at' => $subscriber->next_action_at?->toIso8601String(),
                'steps_completed' => $subscriber->steps_completed,
                'history' => $subscriber->getHistory(),
                'data' => $subscriber->data,
            ],
        ]);
    }

    /**
     * Pause a subscriber.
     */
    public function pause(Funnel $funnel, FunnelSubscriber $subscriber)
    {
        $this->authorize('update', $funnel);

        if ($subscriber->funnel_id !== $funnel->id) {
            abort(404);
        }

        if (!$subscriber->isActive() && !$subscriber->isWaiting()) {
            return response()->json([
                'success' => false,
                'message' => 'Subskrybent nie może być wstrzymany w tym stanie.',
            ], 422);
        }

        $subscriber->pause();
        $subscriber->addToHistory('paused', ['by' => Auth::user()->name]);

        return response()->json([
            'success' => true,
            'message' => 'Subskrybent został wstrzymany.',
            'subscriber' => $this->formatSubscriber($subscriber->fresh(['subscriber', 'currentStep'])),
        ]);
    }

    /**
     * Resume a paused subscriber.
     */
    public function resume(Funnel $funnel, FunnelSubscriber $subscriber)
    {
        $this->authorize('update', $funnel);

        if ($subscriber->funnel_id !== $funnel->id) {
            abort(404);
        }

        if (!$subscriber->isPaused()) {
            return response()->json([
                'success' => false,
                'message' => 'Subskrybent nie jest wstrzymany.',
            ], 422);
        }

        $subscriber->resume();
        $subscriber->addToHistory('resumed', ['by' => Auth::user()->name]);

        return response()->json([
            'success' => true,
            'message' => 'Subskrybent został wznowiony.',
            'subscriber' => $this->formatSubscriber($subscriber->fresh(['subscriber', 'currentStep'])),
        ]);
    }

    /**
     * Advance subscriber to a specific step.
     */
    public function advance(Request $request, Funnel $funnel, FunnelSubscriber $subscriber)
    {
        $this->authorize('update', $funnel);

        if ($subscriber->funnel_id !== $funnel->id) {
            abort(404);
        }

        $validated = $request->validate([
            'step_id' => 'required|exists:funnel_steps,id',
        ]);

        $step = FunnelStep::findOrFail($validated['step_id']);

        if ($step->funnel_id !== $funnel->id) {
            return response()->json([
                'success' => false,
                'message' => 'Wybrany krok nie należy do tego lejka.',
            ], 422);
        }

        $oldStep = $subscriber->currentStep;
        $subscriber->moveToStep($step);
        $subscriber->addToHistory('advanced_manually', [
            'from_step_id' => $oldStep?->id,
            'from_step_name' => $oldStep?->name,
            'to_step_id' => $step->id,
            'to_step_name' => $step->name,
            'by' => Auth::user()->name,
        ]);

        // Process the step immediately if active
        if ($subscriber->isActive()) {
            $this->executionService->processNextStep($subscriber->fresh());
        }

        return response()->json([
            'success' => true,
            'message' => 'Subskrybent został przesunięty do kroku: ' . $step->name,
            'subscriber' => $this->formatSubscriber($subscriber->fresh(['subscriber', 'currentStep'])),
        ]);
    }

    /**
     * Remove subscriber from funnel.
     */
    public function remove(Funnel $funnel, FunnelSubscriber $subscriber)
    {
        $this->authorize('update', $funnel);

        if ($subscriber->funnel_id !== $funnel->id) {
            abort(404);
        }

        $subscriber->markExited('Usunięty ręcznie przez: ' . Auth::user()->name);
        $subscriber->addToHistory('removed', ['by' => Auth::user()->name]);

        return response()->json([
            'success' => true,
            'message' => 'Subskrybent został usunięty z lejka.',
        ]);
    }

    /**
     * Format subscriber for API response.
     */
    protected function formatSubscriber(FunnelSubscriber $subscriber): array
    {
        return [
            'id' => $subscriber->id,
            'subscriber_id' => $subscriber->subscriber_id,
            'email' => $subscriber->subscriber->email,
            'name' => $subscriber->subscriber->name,
            'status' => $subscriber->status,
            'status_label' => FunnelSubscriber::getStatuses()[$subscriber->status] ?? $subscriber->status,
            'current_step' => $subscriber->currentStep ? [
                'id' => $subscriber->currentStep->id,
                'name' => $subscriber->currentStep->name,
                'type' => $subscriber->currentStep->type,
            ] : null,
            'entered_at' => $subscriber->entered_at?->toIso8601String(),
            'next_action_at' => $subscriber->next_action_at?->toIso8601String(),
            'steps_completed' => $subscriber->steps_completed,
        ];
    }
}
