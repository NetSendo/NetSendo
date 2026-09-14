<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Subscriber;
use App\Services\Funnels\ABTestService;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 * - PUT    /api/v1/funnels/{id}/steps/{stepId} - Update a step
 * - DELETE /api/v1/funnels/{id}/steps/{stepId} - Delete a step
 * - POST   /api/v1/funnels/{id}/subscribers - Enroll a subscriber
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
     * Add a step to a funnel.
     *
     * The step is connected after `after_step_id`, else after the last step,
     * and continues where that step did; after a condition it goes on the
     * `yes` path unless `branch` is `no`, after a split (A/B) step on the path of
     * `variant` (its key or name), else on the split's default path.
     */
    public function addStep(Request $request, int $id): JsonResponse
    {
        [$funnel, $error] = $this->writableFunnel($request, $id);
        if ($error) {
            return $error;
        }

        $validated = $request->validate($this->stepRules($request, $funnel, creating: true));

        $after = isset($validated['after_step_id'])
            ? $funnel->steps()->find($validated['after_step_id'])
            : null;
        $previous = $after ?? $funnel->steps()->reorder()->orderByDesc('order')->orderByDesc('id')->first();

        $variant = null;

        if ($previous?->isSplit() && isset($validated['variant'])) {
            $variant = $previous->findSplitVariant($validated['variant']);

            if (!$variant) {
                return response()->json([
                    'error' => 'Unprocessable Entity',
                    'message' => "Step {$previous->id} has no variant \"{$validated['variant']}\". Its variants: "
                        . collect($previous->getSplitVariants())->map(fn (array $v) => "{$v['key']} ({$v['name']})")->implode(', '),
                ], 422);
            }
        }

        // The engine follows the steps' connections, not their order: a step
        // added without being connected was never reached, so a funnel built over
        // the API completed right after its start step
        $step = DB::transaction(function () use ($funnel, $validated, $after, $previous, $variant) {
            if ($after) {
                $order = $after->order + 1;
                // Shift subsequent steps
                $funnel->steps()
                    ->where('order', '>=', $order)
                    ->increment('order');
            } else {
                $order = ($previous?->order ?? -1) + 1;
            }

            $link = match (true) {
                !$previous => null,
                $previous->isCondition() => ($validated['branch'] ?? 'yes') === 'no' ? 'next_step_no_id' : 'next_step_yes_id',
                $variant !== null => 'variant',
                default => 'next_step_id',
            };

            $continues = match ($link) {
                null => null,
                // Where the variant went: its own path, else the split's default one
                'variant' => $previous->getNextStepForVariant($variant['key'])?->id,
                default => $previous->{$link},
            };

            $step = $funnel->steps()->create($this->stepAttributes($validated) + $this->splitVariantAttributes($validated) + [
                'order' => $order,
                // Inserted into the chain: it continues where the previous step did
                'next_step_id' => $continues,
            ]);

            if ($link === 'variant') {
                $previous->setSplitVariantTarget($variant['key'], $step->id);
                $previous->save();
            } elseif ($link) {
                $previous->update([$link => $step->id]);
            }

            return $step;
        });

        return response()->json([
            'data' => $step->fresh(),
            'message' => 'Step added successfully',
        ], 201);
    }

    /**
     * Update a step: its settings and, with `next_step_id`, `next_step_yes_id`
     * or `next_step_no_id`, its connections (null disconnects). A split step's
     * `split_variants` replace its variants; a variant keeps its path unless it
     * is given a `next_step_id` of its own (null: the default path).
     */
    public function updateStep(Request $request, int $id, int $stepId): JsonResponse
    {
        [$funnel, $error] = $this->writableFunnel($request, $id);
        if ($error) {
            return $error;
        }

        $step = $funnel->steps()->find($stepId);
        if (!$step) {
            return response()->json(['error' => 'Not Found', 'message' => 'Step not found'], 404);
        }

        $validated = $request->validate($this->stepRules($request, $funnel, creating: false, step: $step));

        if ($step->type === FunnelStep::TYPE_START && isset($validated['type']) && $validated['type'] !== FunnelStep::TYPE_START) {
            return response()->json(['error' => 'Unprocessable Entity', 'message' => 'The start step cannot change its type'], 422);
        }

        $step->update(
            $this->stepAttributes($validated)
            + $this->splitVariantAttributes($validated, $step)
            + array_intersect_key($validated, array_flip(['next_step_id', 'next_step_yes_id', 'next_step_no_id']))
        );

        // A test already running for the step follows its variants
        if ($step->isSplit()) {
            app(ABTestService::class)->syncTestForStep($step);
        }

        return response()->json([
            'data' => $step->fresh(),
            'message' => 'Step updated successfully',
        ]);
    }

    /**
     * Delete a step. Steps that led to it lead to its next step instead, and
     * enrollments on it move there too, so nobody drops out of the funnel.
     */
    public function destroyStep(Request $request, int $id, int $stepId): JsonResponse
    {
        [$funnel, $error] = $this->writableFunnel($request, $id);
        if ($error) {
            return $error;
        }

        $step = $funnel->steps()->find($stepId);
        if (!$step) {
            return response()->json(['error' => 'Not Found', 'message' => 'Step not found'], 404);
        }

        if ($step->type === FunnelStep::TYPE_START) {
            return response()->json(['error' => 'Unprocessable Entity', 'message' => 'The start step cannot be deleted'], 422);
        }

        DB::transaction(function () use ($funnel, $step) {
            $next = $step->next_step_id;

            foreach (['next_step_id', 'next_step_yes_id', 'next_step_no_id'] as $column) {
                $funnel->steps()->where($column, $step->id)->update([$column => $next]);
            }

            // A/B variant paths are kept in JSON, no foreign key clears them
            $funnel->steps()->where('type', FunnelStep::TYPE_SPLIT)->get()->each(function (FunnelStep $split) use ($step, $next) {
                $leadHere = array_filter($split->getSplitVariants(), fn (array $variant) => $variant['next_step_id'] === $step->id);

                foreach ($leadHere as $variant) {
                    $split->setSplitVariantTarget($variant['key'], $next);
                }

                if ($leadHere) {
                    $split->save();
                }
            });

            // Waiting for this step's condition, or left active on it: the
            // scheduled processor picks them up and runs the next step
            FunnelSubscriber::where('current_step_id', $step->id)
                ->whereIn('status', [FunnelSubscriber::STATUS_ACTIVE, FunnelSubscriber::STATUS_WAITING_CONDITION])
                ->update(['current_step_id' => $next, 'status' => FunnelSubscriber::STATUS_WAITING, 'next_action_at' => now()]);

            FunnelSubscriber::where('current_step_id', $step->id)->update(['current_step_id' => $next]);

            $step->delete();
        });

        return response()->json(['message' => 'Step deleted successfully']);
    }

    /**
     * Enroll a subscriber in an active funnel — the way to start a funnel with
     * the `manual` trigger. The funnel runs its steps up to the first wait at once.
     */
    public function enrollSubscriber(Request $request, int $id): JsonResponse
    {
        [$funnel, $error] = $this->writableFunnel($request, $id);
        if ($error) {
            return $error;
        }

        $user = $request->user();

        $validated = $request->validate([
            'subscriber_id' => ['required_without:email', 'nullable', 'integer', Rule::exists('subscribers', 'id')->where('user_id', $user->id)->whereNull('deleted_at')],
            'email' => ['required_without:subscriber_id', 'nullable', 'email'],
        ]);

        $subscriber = !empty($validated['subscriber_id'])
            ? Subscriber::find($validated['subscriber_id'])
            : Subscriber::where('user_id', $user->id)->where('email', $validated['email'])->first();

        if (!$subscriber) {
            return response()->json(['error' => 'Not Found', 'message' => 'Subscriber not found'], 404);
        }

        if (!$funnel->isActive()) {
            return response()->json(['error' => 'Conflict', 'message' => 'The funnel is not active. Activate it first.'], 409);
        }

        $existing = FunnelSubscriber::where('funnel_id', $funnel->id)->where('subscriber_id', $subscriber->id)->first();
        if ($existing) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'The subscriber is already enrolled in this funnel',
                'data' => $this->enrollmentData($existing),
            ], 409);
        }

        $enrollment = app(FunnelExecutionService::class)->enrollSubscriber($funnel, $subscriber);

        return response()->json([
            'data' => $this->enrollmentData($enrollment->fresh()),
            'message' => 'Subscriber enrolled',
        ], 201);
    }

    /**
     * The funnel of the API key's account, if the key may write funnels.
     *
     * @return array{0: ?Funnel, 1: ?JsonResponse}
     */
    private function writableFunnel(Request $request, int $id): array
    {
        if (!$request->get('api_key')->hasPermission('funnels:write')) {
            return [null, response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have funnels:write permission',
            ], 403)];
        }

        $funnel = Funnel::where('user_id', $request->user()->id)->find($id);

        if (!$funnel) {
            return [null, response()->json([
                'error' => 'Not Found',
                'message' => 'Funnel not found',
            ], 404)];
        }

        return [$funnel, null];
    }

    /**
     * Validation of a step's settings, shared by adding and updating one.
     */
    private function stepRules(Request $request, Funnel $funnel, bool $creating, ?FunnelStep $step = null): array
    {
        $userId = $request->user()->id;
        $types = $creating
            ? ['email', 'sms', 'delay', 'wait_until', 'condition', 'action', 'split', 'goal', 'end']
            : ['start', 'email', 'sms', 'delay', 'wait_until', 'condition', 'action', 'split', 'goal', 'end'];
        $ownMessage = Rule::exists('messages', 'id')->where('user_id', $userId);

        $rules = [
            'type' => [$creating ? 'required' : 'sometimes', Rule::in($types)],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            // Email steps
            'message_id' => ['nullable', 'integer', $ownMessage],
            // SMS steps
            'sms_content' => 'nullable|string|max:1600',
            // Delay steps
            'delay_value' => 'nullable|integer|min:1',
            'delay_unit' => ['nullable', Rule::in(array_keys(FunnelStep::getDelayUnits()))],
            // Wait-until steps
            'wait_until_type' => ['nullable', Rule::in(array_keys(FunnelStep::getWaitUntilTypes()))],
            'wait_until_date' => 'nullable|date_format:Y-m-d',
            'wait_until_time' => 'nullable|date_format:H:i',
            'wait_until_day' => 'nullable|integer|between:1,7',
            'wait_until_timezone' => 'nullable|timezone:all',
            // Condition steps
            'condition_type' => ['nullable', Rule::in(array_keys(FunnelStep::getConditionTypes()))],
            'condition_config' => 'nullable|array',
            'wait_for_condition' => 'sometimes|boolean',
            'retry_enabled' => 'sometimes|boolean',
            'retry_max_attempts' => 'sometimes|integer|between:1,10',
            'retry_interval_value' => 'sometimes|integer|min:1',
            'retry_interval_unit' => ['sometimes', Rule::in(array_keys(FunnelStep::getRetryIntervalUnits()))],
            'retry_message_id' => ['nullable', 'integer', $ownMessage],
            'retry_exhausted_action' => ['sometimes', Rule::in(array_keys(FunnelStep::getRetryExhaustedActions()))],
            // Action steps
            'action_type' => ['nullable', Rule::in(array_keys(FunnelStep::getActionTypes()))],
            'action_config' => 'nullable|array',
            // Goal steps
            'goal_name' => 'nullable|string|max:255',
            'goal_type' => ['nullable', Rule::in(array_keys(FunnelStep::getGoalTypes()))],
            'goal_value' => 'nullable|numeric|min:0',
            'goal_config' => 'nullable|array',
            // Split (A/B) steps: 2-5 variants (default when adding: A and B, 50/50).
            // A weight is a relative share; without weights the split is even
            'split_variants' => 'nullable|array|min:2|max:5',
            'split_variants.*' => 'array',
            'split_variants.*.key' => ['nullable', 'string', 'distinct', 'regex:' . FunnelStep::SPLIT_VARIANT_KEY_PATTERN],
            'split_variants.*.name' => 'nullable|string|max:255',
            'split_variants.*.weight' => 'nullable|integer|min:0|max:100',
        ];

        if ($creating) {
            return $rules + [
                'after_step_id' => 'nullable|integer',
                // After a condition step: the path the new step goes on (default: yes)
                'branch' => 'nullable|in:yes,no',
                // After a split step: the variant whose path the new step goes on,
                // by key or name (default: the split's default path)
                'variant' => 'nullable|string|max:255',
            ];
        }

        $sibling = Rule::exists('funnel_steps', 'id')
            ->where('funnel_id', $funnel->id)
            ->whereNot('id', $step?->id);

        return $rules + [
            'next_step_id' => ['nullable', 'integer', $sibling],
            'next_step_yes_id' => ['nullable', 'integer', $sibling],
            'next_step_no_id' => ['nullable', 'integer', $sibling],
            'split_variants.*.next_step_id' => ['nullable', 'integer', $sibling],
        ];
    }

    /**
     * The step columns a validated request sets.
     */
    private function stepAttributes(array $validated): array
    {
        return array_intersect_key($validated, array_flip([
            'type', 'name', 'message_id', 'sms_content',
            'delay_value', 'delay_unit',
            'wait_until_type', 'wait_until_date', 'wait_until_time', 'wait_until_day', 'wait_until_timezone',
            'condition_type', 'condition_config',
            'wait_for_condition', 'retry_enabled', 'retry_max_attempts', 'retry_interval_value',
            'retry_interval_unit', 'retry_message_id', 'retry_exhausted_action',
            'action_type', 'action_config',
            'goal_name', 'goal_type', 'goal_value', 'goal_config',
        ]));
    }

    /**
     * The `split_variants` a validated request sets on a split step, normalized
     * (FunnelStep::normalizeSplitVariants()). A new split step (added, or
     * another step turned into one) without variants gets A and B. Paths are
     * set on an update only: a variant naming no `next_step_id` keeps the path
     * of the variant with its key.
     */
    private function splitVariantAttributes(array $validated, ?FunnelStep $step = null): array
    {
        if (($validated['type'] ?? $step?->type) !== FunnelStep::TYPE_SPLIT) {
            return [];
        }

        if (!isset($validated['split_variants'])) {
            return $step?->isSplit() ? [] : ['split_variants' => FunnelStep::normalizeSplitVariants([['name' => 'Wariant A'], ['name' => 'Wariant B']])];
        }

        // Validated data lists a variant first when it has more fields validated: back in order
        $given = collect($validated['split_variants'])->sortKeys()->values()->all();
        $paths = collect($step?->getSplitVariants() ?? [])->pluck('next_step_id', 'key');

        return ['split_variants' => array_map(
            fn (array $variant, array $input) => array_merge($variant, [
                'next_step_id' => $step && array_key_exists('next_step_id', $input)
                    ? $variant['next_step_id']
                    : $paths->get($variant['key']),
            ]),
            FunnelStep::normalizeSplitVariants($given),
            $given
        )];
    }

    private function enrollmentData(FunnelSubscriber $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'funnel_id' => $enrollment->funnel_id,
            'subscriber_id' => $enrollment->subscriber_id,
            'status' => $enrollment->status,
            'current_step_id' => $enrollment->current_step_id,
            'next_action_at' => $enrollment->next_action_at?->toIso8601String(),
            'entered_at' => $enrollment->entered_at?->toIso8601String(),
            'completed_at' => $enrollment->completed_at?->toIso8601String(),
        ];
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
