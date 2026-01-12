<?php

namespace App\Http\Controllers;

use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\Message;
use App\Services\AbTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AbTestController extends Controller
{
    protected AbTestService $abTestService;

    public function __construct(AbTestService $abTestService)
    {
        $this->abTestService = $abTestService;
    }

    /**
     * Display a listing of A/B tests.
     */
    public function index(Request $request)
    {
        $tests = AbTest::forUser(Auth::id())
            ->with(['message:id,subject,status', 'variants', 'winnerVariant'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('AbTests/Index', [
            'tests' => $tests,
        ]);
    }

    /**
     * Store a newly created A/B test.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message_id' => 'required|exists:messages,id',
            'name' => 'nullable|string|max:255',
            'test_type' => 'nullable|string|in:subject,content,sender,send_time,full',
            'winning_metric' => 'nullable|string|in:open_rate,click_rate,conversion_rate',
            'sample_percentage' => 'nullable|integer|min:5|max:50',
            'test_duration_hours' => 'nullable|integer|min:1|max:168',
            'auto_select_winner' => 'nullable|boolean',
            'confidence_threshold' => 'nullable|integer|min:80|max:99',
            'variants' => 'required|array|min:2|max:5',
            'variants.*.variant_letter' => 'required|string|in:A,B,C,D,E',
            'variants.*.subject' => 'nullable|string|max:255',
            'variants.*.preheader' => 'nullable|string|max:255',
            'variants.*.content' => 'nullable|string',
            'variants.*.from_name' => 'nullable|string|max:255',
            'variants.*.from_email' => 'nullable|email|max:255',
            'variants.*.weight' => 'nullable|integer|min:1|max:100',
        ]);

        $message = Message::where('id', $validated['message_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $test = $this->abTestService->createTest($message, $validated);

        return response()->json([
            'success' => true,
            'test' => $test->load('variants'),
            'message' => __('ab_tests.created'),
        ]);
    }

    /**
     * Display the specified A/B test.
     */
    public function show(AbTest $abTest)
    {
        $this->authorize('view', $abTest);

        $abTest->load(['message', 'variants', 'winnerVariant']);
        $results = $this->abTestService->getResults($abTest);

        return Inertia::render('AbTests/Show', [
            'test' => $abTest,
            'results' => $results,
        ]);
    }

    /**
     * Update the specified A/B test.
     */
    public function update(Request $request, AbTest $abTest)
    {
        $this->authorize('update', $abTest);

        if (!in_array($abTest->status, [AbTest::STATUS_DRAFT, AbTest::STATUS_PAUSED])) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.cannot_update_running'),
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'test_type' => 'nullable|string|in:subject,content,sender,send_time,full',
            'winning_metric' => 'nullable|string|in:open_rate,click_rate,conversion_rate',
            'sample_percentage' => 'nullable|integer|min:5|max:50',
            'test_duration_hours' => 'nullable|integer|min:1|max:168',
            'auto_select_winner' => 'nullable|boolean',
            'confidence_threshold' => 'nullable|integer|min:80|max:99',
        ]);

        $abTest->update($validated);

        return response()->json([
            'success' => true,
            'test' => $abTest->fresh(['variants']),
            'message' => __('ab_tests.updated'),
        ]);
    }

    /**
     * Remove the specified A/B test.
     */
    public function destroy(AbTest $abTest)
    {
        $this->authorize('delete', $abTest);

        if ($abTest->status === AbTest::STATUS_RUNNING) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.cannot_delete_running'),
            ], 422);
        }

        $abTest->delete();

        return response()->json([
            'success' => true,
            'message' => __('ab_tests.deleted'),
        ]);
    }

    /**
     * Start an A/B test.
     */
    public function start(AbTest $abTest)
    {
        $this->authorize('update', $abTest);

        try {
            $this->abTestService->startTest($abTest);

            return response()->json([
                'success' => true,
                'test' => $abTest->fresh(['variants']),
                'message' => __('ab_tests.started'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Pause a running A/B test.
     */
    public function pause(AbTest $abTest)
    {
        $this->authorize('update', $abTest);

        try {
            $this->abTestService->pauseTest($abTest);

            return response()->json([
                'success' => true,
                'test' => $abTest->fresh(),
                'message' => __('ab_tests.paused'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resume a paused A/B test.
     */
    public function resume(AbTest $abTest)
    {
        $this->authorize('update', $abTest);

        try {
            $this->abTestService->resumeTest($abTest);

            return response()->json([
                'success' => true,
                'test' => $abTest->fresh(),
                'message' => __('ab_tests.resumed'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Manually select a winner variant.
     */
    public function selectWinner(Request $request, AbTest $abTest)
    {
        $this->authorize('update', $abTest);

        $validated = $request->validate([
            'variant_id' => 'required|exists:ab_test_variants,id',
            'send_to_remaining' => 'nullable|boolean',
        ]);

        $variant = AbTestVariant::findOrFail($validated['variant_id']);

        try {
            $this->abTestService->selectWinnerManually($abTest, $variant);

            if ($validated['send_to_remaining'] ?? true) {
                $this->abTestService->sendWinnerToRemaining($abTest);
            }

            return response()->json([
                'success' => true,
                'test' => $abTest->fresh(['variants', 'winnerVariant']),
                'message' => __('ab_tests.winner_selected'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get real-time results for an A/B test.
     */
    public function getResults(AbTest $abTest)
    {
        $this->authorize('view', $abTest);

        $results = $this->abTestService->getResults($abTest);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Add a variant to an existing test.
     */
    public function addVariant(Request $request, AbTest $abTest)
    {
        $this->authorize('update', $abTest);

        if ($abTest->status !== AbTest::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.cannot_add_variant_running'),
            ], 422);
        }

        if ($abTest->variants()->count() >= 5) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.max_variants_reached'),
            ], 422);
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'weight' => 'nullable|integer|min:1|max:100',
            'is_ai_generated' => 'nullable|boolean',
        ]);

        $variant = $this->abTestService->createVariant($abTest, $validated);

        return response()->json([
            'success' => true,
            'variant' => $variant,
            'message' => __('ab_tests.variant_added'),
        ]);
    }

    /**
     * Update a variant.
     */
    public function updateVariant(Request $request, AbTest $abTest, AbTestVariant $variant)
    {
        $this->authorize('update', $abTest);

        if ($variant->ab_test_id !== $abTest->id) {
            abort(404);
        }

        if (!in_array($abTest->status, [AbTest::STATUS_DRAFT, AbTest::STATUS_PAUSED])) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.cannot_update_running'),
            ], 422);
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'weight' => 'nullable|integer|min:1|max:100',
        ]);

        $variant->update($validated);

        return response()->json([
            'success' => true,
            'variant' => $variant->fresh(),
            'message' => __('ab_tests.variant_updated'),
        ]);
    }

    /**
     * Delete a variant.
     */
    public function deleteVariant(AbTest $abTest, AbTestVariant $variant)
    {
        $this->authorize('update', $abTest);

        if ($variant->ab_test_id !== $abTest->id) {
            abort(404);
        }

        if ($abTest->status !== AbTest::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.cannot_delete_variant_running'),
            ], 422);
        }

        if ($abTest->variants()->count() <= 2) {
            return response()->json([
                'success' => false,
                'message' => __('ab_tests.min_variants_required'),
            ], 422);
        }

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => __('ab_tests.variant_deleted'),
        ]);
    }

    /**
     * Get recommended sample size for a message.
     */
    public function getRecommendedSampleSize(Request $request, Message $message)
    {
        $this->authorize('view', $message);

        $validated = $request->validate([
            'baseline_rate' => 'nullable|numeric|min:0.01|max:1',
            'minimum_effect' => 'nullable|numeric|min:0.01|max:1',
        ]);

        $baselineRate = $validated['baseline_rate'] ?? 0.2; // 20% default open rate
        $mde = $validated['minimum_effect'] ?? 0.1; // 10% improvement

        $sampleSize = $this->abTestService->getRecommendedSampleSize($baselineRate, $mde);

        return response()->json([
            'success' => true,
            'sample_size' => $sampleSize,
            'baseline_rate' => $baselineRate,
            'minimum_effect' => $mde,
        ]);
    }
}
