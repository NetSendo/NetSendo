<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API Controller for managing Funnels (Automation Sequences)
 *
 * Endpoints:
 * - GET    /api/v1/funnels              - List all funnels
 * - GET    /api/v1/funnels/{id}         - Get funnel details
 * - POST   /api/v1/funnels              - Create new funnel
 * - PUT    /api/v1/funnels/{id}         - Update funnel
 * - DELETE /api/v1/funnels/{id}         - Delete funnel
 * - POST   /api/v1/funnels/{id}/steps   - Add step to funnel
 * - POST   /api/v1/funnels/{id}/activate - Activate funnel
 * - POST   /api/v1/funnels/{id}/pause   - Pause funnel
 * - GET    /api/v1/funnels/{id}/stats   - Get funnel statistics
 */
class FunnelController extends Controller
{
    /**
     * List all funnels with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Funnel::where('user_id', $user->id)
            ->with(['triggerList', 'triggerForm']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by trigger type
        if ($request->has('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);
        $funnels = $query->paginate($perPage);

        return response()->json([
            'data' => $funnels->items(),
            'meta' => [
                'current_page' => $funnels->currentPage(),
                'last_page' => $funnels->lastPage(),
                'per_page' => $funnels->perPage(),
                'total' => $funnels->total(),
            ],
        ]);
    }

    /**
     * Get a single funnel's details with steps
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $funnel = Funnel::where('user_id', $user->id)
            ->with(['steps', 'triggerList', 'triggerForm'])
            ->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        return response()->json([
            'data' => array_merge($funnel->toArray(), [
                'stats' => $funnel->getStats(),
            ]),
        ]);
    }

    /**
     * Create a new funnel
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('funnels:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:list_signup,tag_added,form_submit,manual',
            'trigger_list_id' => [
                'nullable',
                'required_if:trigger_type,list_signup',
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'trigger_form_id' => [
                'nullable',
                'required_if:trigger_type,form_submit',
                'integer',
                Rule::exists('subscription_forms', 'id')->where('user_id', $user->id),
            ],
            'trigger_tag' => 'nullable|required_if:trigger_type,tag_added|string|max:255',
            'settings' => 'nullable|array',
        ]);

        $funnel = Funnel::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'trigger_type' => $validated['trigger_type'],
            'trigger_list_id' => $validated['trigger_list_id'] ?? null,
            'trigger_form_id' => $validated['trigger_form_id'] ?? null,
            'trigger_tag' => $validated['trigger_tag'] ?? null,
            'settings' => $validated['settings'] ?? [],
            'status' => Funnel::STATUS_DRAFT,
        ]);

        // Create default start step
        $funnel->steps()->create([
            'type' => FunnelStep::TYPE_START,
            'name' => 'Start',
            'order' => 0,
        ]);

        $funnel->load(['steps', 'triggerList', 'triggerForm']);

        return response()->json([
            'data' => $funnel,
            'message' => 'Funnel created successfully',
        ], 201);
    }

    /**
     * Update a funnel
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('funnels:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403);
        }

        $funnel = Funnel::where('user_id', $user->id)->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'trigger_type' => 'sometimes|in:list_signup,tag_added,form_submit,manual',
            'trigger_list_id' => [
                'nullable',
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'trigger_form_id' => [
                'nullable',
                'integer',
                Rule::exists('subscription_forms', 'id')->where('user_id', $user->id),
            ],
            'trigger_tag' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        $funnel->update($validated);
        $funnel->load(['steps', 'triggerList', 'triggerForm']);

        return response()->json([
            'data' => $funnel,
            'message' => 'Funnel updated successfully',
        ]);
    }

    /**
     * Add a step to a funnel
     */
    public function addStep(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('funnels:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403);
        }

        $funnel = Funnel::where('user_id', $user->id)->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:email,sms,delay,condition,action,end',
            'name' => 'required|string|max:255',
            'after_step_id' => 'nullable|integer', // Insert after this step
            'config' => 'nullable|array',
            // For email/sms steps
            'message_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('user_id', $user->id),
            ],
            // For delay steps
            'delay_value' => 'nullable|integer|min:1',
            'delay_unit' => 'nullable|in:minutes,hours,days',
            // For condition steps
            'condition_type' => 'nullable|string',
            'condition_config' => 'nullable|array',
        ]);

        // Calculate order
        $order = $funnel->steps()->max('order') + 1;
        if (isset($validated['after_step_id'])) {
            $afterStep = $funnel->steps()->find($validated['after_step_id']);
            if ($afterStep) {
                $order = $afterStep->order + 1;
                // Shift subsequent steps
                $funnel->steps()
                    ->where('order', '>=', $order)
                    ->increment('order');
            }
        }

        $step = $funnel->steps()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'order' => $order,
            'config' => $validated['config'] ?? [],
            'message_id' => $validated['message_id'] ?? null,
            'delay_value' => $validated['delay_value'] ?? null,
            'delay_unit' => $validated['delay_unit'] ?? null,
            'condition_type' => $validated['condition_type'] ?? null,
            'condition_config' => $validated['condition_config'] ?? null,
        ]);

        return response()->json([
            'data' => $step,
            'message' => 'Step added successfully',
        ], 201);
    }

    /**
     * Activate a funnel
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('funnels:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403);
        }

        $funnel = Funnel::where('user_id', $user->id)->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        // Validate funnel has steps
        if ($funnel->steps()->count() < 2) { // At least start + 1 action
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Funnel must have at least one action step',
            ], 422);
        }

        // Validate trigger is configured
        if ($funnel->trigger_type === 'list_signup' && !$funnel->trigger_list_id) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Funnel must have a trigger list configured',
            ], 422);
        }

        $success = $funnel->activate();

        if (!$success) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Could not activate funnel. Check configuration.',
            ], 422);
        }

        return response()->json([
            'data' => $funnel->fresh(['steps', 'triggerList', 'triggerForm']),
            'message' => 'Funnel activated',
        ]);
    }

    /**
     * Pause a funnel
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('funnels:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403);
        }

        $funnel = Funnel::where('user_id', $user->id)->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        $funnel->pause();

        return response()->json([
            'data' => $funnel->fresh(['steps', 'triggerList', 'triggerForm']),
            'message' => 'Funnel paused',
        ]);
    }

    /**
     * Get funnel statistics
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $funnel = Funnel::where('user_id', $user->id)->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'status' => $funnel->status,
                'stats' => $funnel->getStats(),
                'trigger' => [
                    'type' => $funnel->trigger_type,
                    'list' => $funnel->triggerList?->name,
                    'form' => $funnel->triggerForm?->name,
                    'tag' => $funnel->trigger_tag,
                ],
            ],
        ]);
    }

    /**
     * Delete a funnel
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('funnels:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403);
        }

        $funnel = Funnel::where('user_id', $user->id)->find($id);

        if (!$funnel) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404);
        }

        if ($funnel->isActive()) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Cannot delete an active funnel. Pause it first.',
            ], 409);
        }

        $funnel->steps()->delete();
        $funnel->delete();

        return response()->json([
            'message' => 'Funnel deleted successfully',
        ]);
    }
}
