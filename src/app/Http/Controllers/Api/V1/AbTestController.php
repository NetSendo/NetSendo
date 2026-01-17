<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API Controller for managing A/B Tests
 *
 * Endpoints:
 * - GET    /api/v1/ab-tests              - List all A/B tests
 * - GET    /api/v1/ab-tests/{id}         - Get test details
 * - POST   /api/v1/ab-tests              - Create new test
 * - PUT    /api/v1/ab-tests/{id}         - Update test
 * - DELETE /api/v1/ab-tests/{id}         - Delete test
 * - POST   /api/v1/ab-tests/{id}/variants - Add variant
 * - POST   /api/v1/ab-tests/{id}/start   - Start test
 * - POST   /api/v1/ab-tests/{id}/end     - End test
 * - GET    /api/v1/ab-tests/{id}/results - Get test results
 */
class AbTestController extends Controller
{
    /**
     * List all A/B tests with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AbTest::where('user_id', $user->id)
            ->with(['message', 'variants', 'winnerVariant']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by message
        if ($request->has('message_id')) {
            $query->where('message_id', $request->message_id);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);
        $tests = $query->paginate($perPage);

        return response()->json([
            'data' => $tests->items(),
            'meta' => [
                'current_page' => $tests->currentPage(),
                'last_page' => $tests->lastPage(),
                'per_page' => $tests->perPage(),
                'total' => $tests->total(),
            ],
        ]);
    }

    /**
     * Get a single A/B test's details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $test = AbTest::where('user_id', $user->id)
            ->with(['message', 'variants', 'winnerVariant'])
            ->find($id);

        if (!$test) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'A/B test not found',
            ], 404);
        }

        return response()->json([
            'data' => $test,
        ]);
    }

    /**
     * Create a new A/B test for a campaign
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'message_id' => [
                'required',
                'integer',
                Rule::exists('messages', 'id')->where('user_id', $user->id),
            ],
            'name' => 'required|string|max:255',
            'test_type' => 'required|in:subject,content,sender,send_time,full',
            'winning_metric' => 'required|in:open_rate,click_rate,conversion_rate',
            'sample_percentage' => 'required|integer|min:10|max:50',
            'test_duration_hours' => 'required|integer|min:1|max:168', // 1h to 7 days
            'auto_select_winner' => 'sometimes|boolean',
            'confidence_threshold' => 'sometimes|integer|min:80|max:99',
        ]);

        // Check if message already has an A/B test
        $existingTest = AbTest::where('message_id', $validated['message_id'])->first();
        if ($existingTest) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'This campaign already has an A/B test',
                'existing_test_id' => $existingTest->id,
            ], 409);
        }

        $test = AbTest::create([
            'user_id' => $user->id,
            'message_id' => $validated['message_id'],
            'name' => $validated['name'],
            'test_type' => $validated['test_type'],
            'winning_metric' => $validated['winning_metric'],
            'sample_percentage' => $validated['sample_percentage'],
            'test_duration_hours' => $validated['test_duration_hours'],
            'auto_select_winner' => $validated['auto_select_winner'] ?? true,
            'confidence_threshold' => $validated['confidence_threshold'] ?? 95,
            'status' => AbTest::STATUS_DRAFT,
        ]);

        $test->load(['message', 'variants']);

        return response()->json([
            'data' => $test,
            'message' => 'A/B test created successfully',
        ], 201);
    }

    /**
     * Add a variant to an A/B test
     */
    public function addVariant(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $test = AbTest::where('user_id', $user->id)->find($id);

        if (!$test) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'A/B test not found',
            ], 404);
        }

        if ($test->status !== AbTest::STATUS_DRAFT) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Cannot add variants to a test that has started',
            ], 409);
        }

        $validated = $request->validate([
            'variant_letter' => 'required|string|size:1|regex:/^[A-Z]$/',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'mailbox_id' => [
                'nullable',
                'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'is_control' => 'sometimes|boolean',
            'weight' => 'sometimes|integer|min:1|max:100',
        ]);

        // Check if variant letter already exists
        $existingVariant = $test->variants()->where('variant_letter', $validated['variant_letter'])->first();
        if ($existingVariant) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Variant ' . $validated['variant_letter'] . ' already exists',
            ], 409);
        }

        $variant = $test->variants()->create([
            'variant_letter' => $validated['variant_letter'],
            'subject' => $validated['subject'] ?? null,
            'content' => $validated['content'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'is_control' => $validated['is_control'] ?? ($validated['variant_letter'] === 'A'),
            'weight' => $validated['weight'] ?? 50,
        ]);

        return response()->json([
            'data' => $variant,
            'message' => 'Variant added successfully',
        ], 201);
    }

    /**
     * Start an A/B test
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $test = AbTest::where('user_id', $user->id)->find($id);

        if (!$test) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'A/B test not found',
            ], 404);
        }

        if ($test->status !== AbTest::STATUS_DRAFT) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Test has already been started or completed',
            ], 409);
        }

        // Validate test has at least 2 variants
        if ($test->variants()->count() < 2) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Test must have at least 2 variants',
            ], 422);
        }

        $test->update([
            'status' => AbTest::STATUS_RUNNING,
            'test_started_at' => now(),
        ]);

        return response()->json([
            'data' => $test->fresh(['message', 'variants']),
            'message' => 'A/B test started',
            'ends_at' => $test->test_started_at->addHours($test->test_duration_hours)->toIso8601String(),
        ]);
    }

    /**
     * End an A/B test and optionally select winner
     */
    public function end(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $test = AbTest::where('user_id', $user->id)->find($id);

        if (!$test) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'A/B test not found',
            ], 404);
        }

        if ($test->status !== AbTest::STATUS_RUNNING) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Test is not running',
            ], 409);
        }

        $validated = $request->validate([
            'winner_variant_id' => 'nullable|integer',
        ]);

        // Determine winner
        $winner = null;
        if (isset($validated['winner_variant_id'])) {
            $winner = $test->variants()->find($validated['winner_variant_id']);
        } else {
            $winner = $test->determineWinner();
        }

        // Calculate final results
        $results = $test->calculateResults();

        $test->update([
            'status' => AbTest::STATUS_COMPLETED,
            'test_ended_at' => now(),
            'winner_variant_id' => $winner?->id,
            'final_results' => $results,
        ]);

        return response()->json([
            'data' => $test->fresh(['message', 'variants', 'winnerVariant']),
            'message' => 'A/B test completed',
            'winner' => $winner ? [
                'variant_letter' => $winner->variant_letter,
                'id' => $winner->id,
            ] : null,
        ]);
    }

    /**
     * Get A/B test results
     */
    public function results(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $test = AbTest::where('user_id', $user->id)
            ->with(['variants', 'winnerVariant'])
            ->find($id);

        if (!$test) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'A/B test not found',
            ], 404);
        }

        $results = $test->calculateResults();

        return response()->json([
            'data' => [
                'test_id' => $test->id,
                'name' => $test->name,
                'status' => $test->status,
                'test_type' => $test->test_type,
                'winning_metric' => $test->winning_metric,
                'test_started_at' => $test->test_started_at?->toIso8601String(),
                'test_ended_at' => $test->test_ended_at?->toIso8601String(),
                'winner' => $test->winnerVariant ? [
                    'variant_letter' => $test->winnerVariant->variant_letter,
                    'id' => $test->winnerVariant->id,
                ] : null,
                'results' => $results,
            ],
        ]);
    }

    /**
     * Delete an A/B test
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $test = AbTest::where('user_id', $user->id)->find($id);

        if (!$test) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'A/B test not found',
            ], 404);
        }

        if ($test->status === AbTest::STATUS_RUNNING) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Cannot delete a running test',
            ], 409);
        }

        $test->variants()->delete();
        $test->delete();

        return response()->json([
            'message' => 'A/B test deleted successfully',
        ]);
    }
}
