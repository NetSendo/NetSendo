<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Services\Automation\AutomationCatalog;
use App\Services\Automation\AutomationRuleValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Automation rules ("when trigger X, if conditions Y, do actions Z") over the
 * API — the same rules the web builder (AutomationController) edits.
 *
 * - GET    /api/v1/automations/options          trigger/condition/action catalogue
 * - GET    /api/v1/automations                  list (trigger_event, is_active, search)
 * - POST   /api/v1/automations                  create
 * - GET    /api/v1/automations/{id}             details with 7-day stats
 * - PUT    /api/v1/automations/{id}             update (partial: unsent keys keep their values)
 * - DELETE /api/v1/automations/{id}             delete
 * - POST   /api/v1/automations/{id}/toggle      activate / deactivate
 * - POST   /api/v1/automations/{id}/duplicate   copy (inactive)
 * - GET    /api/v1/automations/{id}/logs        execution log
 *
 * Rules a message keeps in sync with its trigger (trigger_source = message)
 * are read-only here: saving the message overwrites them.
 */
class AutomationRuleController extends Controller
{
    use ManagesContactLists;

    protected const EDITABLE = [
        'name', 'description', 'trigger_event', 'trigger_config', 'conditions', 'condition_logic',
        'actions', 'is_active', 'limit_per_subscriber', 'limit_count', 'limit_period',
    ];

    public function __construct(protected AutomationRuleValidator $validator)
    {
    }

    public function options(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:read')) {
            return $denied;
        }

        return response()->json(['data' => AutomationCatalog::options()]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:read')) {
            return $denied;
        }

        $request->validate([
            'trigger_event' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = AutomationRule::forUser($request->user()->id)
            ->withCount('logs')
            ->latest('id');

        if ($request->filled('trigger_event')) {
            $query->where('trigger_event', $request->input('trigger_event'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $rules = $query->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'data' => collect($rules->items())->map(fn (AutomationRule $rule) => $this->present($rule))->all(),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:read')) {
            return $denied;
        }

        $rule = $this->findRule($request, $id);
        if (!$rule) {
            return $this->ruleNotFound();
        }

        return response()->json([
            'data' => $this->present($rule) + ['stats_7d' => $rule->getStats(7)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:write')) {
            return $denied;
        }

        $payload = $request->only(self::EDITABLE);
        $attributes = $this->validator->validate($payload, $request->user());

        $attributes['user_id'] = $request->user()->id;
        $attributes['condition_logic'] = $attributes['condition_logic'] ?? 'all';
        $attributes['conditions'] = $attributes['conditions'] ?? [];
        $attributes['trigger_config'] = $attributes['trigger_config'] ?? [];

        $rule = AutomationRule::create($attributes);

        return response()->json(['data' => $this->present($rule->fresh())], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:write')) {
            return $denied;
        }

        $rule = $this->findRule($request, $id);
        if (!$rule) {
            return $this->ruleNotFound();
        }

        if ($managed = $this->managedByMessage($rule)) {
            return $managed;
        }

        $sent = array_keys($request->only(self::EDITABLE));
        $payload = array_merge($this->storedAttributes($rule), $request->only(self::EDITABLE));

        $attributes = $this->validator->validate($payload, $request->user(), $sent);
        $attributes['condition_logic'] = $attributes['condition_logic'] ?? 'all';

        // A new trigger_event revalidates the stored trigger_config against it
        $write = in_array('trigger_event', $sent, true) ? [...$sent, 'trigger_config'] : $sent;

        $rule->update(array_intersect_key($attributes, array_flip($write)) + ['condition_logic' => $attributes['condition_logic']]);

        return response()->json(['data' => $this->present($rule->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:write')) {
            return $denied;
        }

        $rule = $this->findRule($request, $id);
        if (!$rule) {
            return $this->ruleNotFound();
        }

        if ($managed = $this->managedByMessage($rule)) {
            return $managed;
        }

        if ($rule->is_system && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => "\"{$rule->name}\" is one of NetSendo's default automations. Deactivate it instead, or send confirm=true to delete it (it can be restored in the panel: Automations → Restore defaults).",
            ], 409);
        }

        $rule->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * Set is_active (when sent) or flip it.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:write')) {
            return $denied;
        }

        $request->validate(['is_active' => 'nullable|boolean']);

        $rule = $this->findRule($request, $id);
        if (!$rule) {
            return $this->ruleNotFound();
        }

        if ($managed = $this->managedByMessage($rule)) {
            return $managed;
        }

        $isActive = $request->has('is_active') && $request->input('is_active') !== null
            ? $request->boolean('is_active')
            : !$rule->is_active;

        if ($isActive && !(AutomationCatalog::triggers()[$rule->trigger_event]['available'] ?? false)) {
            return $this->badRequest("The trigger {$rule->trigger_event} is never emitted by NetSendo; change the rule's trigger_event before activating it.");
        }

        $rule->update(['is_active' => $isActive]);

        return response()->json(['data' => $this->present($rule->fresh())]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:write')) {
            return $denied;
        }

        $rule = $this->findRule($request, $id);
        if (!$rule) {
            return $this->ruleNotFound();
        }

        return response()->json(['data' => $this->present($rule->duplicate()->fresh())], 201);
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'funnels:read')) {
            return $denied;
        }

        $request->validate([
            'status' => 'nullable|in:success,partial,failed,skipped',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $rule = $this->findRule($request, $id);
        if (!$rule) {
            return $this->ruleNotFound();
        }

        $logs = $rule->logs()
            ->with('subscriber:id,email,first_name,last_name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        $counts = $rule->logs()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'data' => collect($logs->items())->map(fn (AutomationRuleLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'trigger_event' => $log->trigger_event,
                'subscriber' => $log->subscriber ? [
                    'id' => $log->subscriber->id,
                    'email' => $log->subscriber->email,
                    'name' => trim($log->subscriber->first_name . ' ' . $log->subscriber->last_name) ?: null,
                ] : null,
                'actions_executed' => $log->actions_executed ?? [],
                'error_message' => $log->error_message,
                'trigger_data' => $log->trigger_data ?? [],
                'execution_time_ms' => $log->execution_time_ms,
                'executed_at' => $log->executed_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'totals_by_status' => [
                    'success' => (int) ($counts['success'] ?? 0),
                    'partial' => (int) ($counts['partial'] ?? 0),
                    'failed' => (int) ($counts['failed'] ?? 0),
                    'skipped' => (int) ($counts['skipped'] ?? 0),
                ],
            ],
        ]);
    }

    protected function findRule(Request $request, int $id): ?AutomationRule
    {
        return AutomationRule::forUser($request->user()->id)->find($id);
    }

    protected function ruleNotFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Automation rule not found',
        ], 404);
    }

    protected function managedByMessage(AutomationRule $rule): ?JsonResponse
    {
        if (!$rule->isManagedByMessage()) {
            return null;
        }

        return response()->json([
            'error' => 'Conflict',
            'message' => "This rule is managed by message #{$rule->trigger_source_id} (its autoresponder trigger) and is overwritten whenever the message is saved. Change the message's trigger instead, or duplicate the rule to get an editable copy.",
        ], 409);
    }

    protected function storedAttributes(AutomationRule $rule): array
    {
        return [
            'name' => $rule->name,
            'description' => $rule->description,
            'trigger_event' => $rule->trigger_event,
            'trigger_config' => $rule->trigger_config ?? [],
            'conditions' => $rule->conditions ?? [],
            'condition_logic' => $rule->condition_logic,
            'actions' => $rule->actions ?? [],
            'is_active' => (bool) $rule->is_active,
            'limit_per_subscriber' => (bool) $rule->limit_per_subscriber,
            'limit_count' => $rule->limit_count,
            'limit_period' => $rule->limit_period,
        ];
    }

    protected function present(AutomationRule $rule): array
    {
        $managed = $rule->isManagedByMessage();

        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'trigger_event' => $rule->trigger_event,
            'trigger_event_label' => $rule->trigger_event_label,
            'trigger_config' => $rule->trigger_config ?: new \stdClass(),
            'conditions' => $rule->conditions ?? [],
            'condition_logic' => $rule->condition_logic ?? 'all',
            'actions' => $rule->actions ?? [],
            'is_active' => (bool) $rule->is_active,
            'limit_per_subscriber' => (bool) $rule->limit_per_subscriber,
            'limit_count' => $rule->limit_count,
            'limit_period' => $rule->limit_period,
            'execution_count' => (int) $rule->execution_count,
            'last_executed_at' => $rule->last_executed_at?->toIso8601String(),
            'logs_count' => $rule->logs_count ?? $rule->logs()->count(),
            'is_system' => (bool) $rule->is_system,
            'system_key' => $rule->system_key,
            'managed_by' => $managed ? [
                'type' => 'message',
                'id' => (int) $rule->trigger_source_id,
                'note' => 'Kept in sync with the message trigger; read-only here. Edit the message to change it.',
            ] : null,
            'read_only' => $managed,
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
