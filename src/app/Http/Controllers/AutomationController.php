<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\Tag;
use App\Models\Message;
use App\Models\Funnel;
use App\Models\SubscriptionForm;
use App\Models\CustomField;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AutomationController extends Controller
{
    /**
     * Display a listing of automation rules.
     */
    public function index(Request $request)
    {
        $query = AutomationRule::forUser(Auth::id())
            ->withCount('logs')
            ->latest();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by trigger event
        if ($request->filled('trigger')) {
            $query->where('trigger_event', $request->trigger);
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $rules = $query->paginate(15)->through(function ($rule) {
            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'trigger_event' => $rule->trigger_event,
                'trigger_event_label' => $rule->trigger_event_label,
                'actions_count' => $rule->actions_count,
                'is_active' => $rule->is_active,
                'execution_count' => $rule->execution_count,
                'last_executed_at' => $rule->last_executed_at?->format('Y-m-d H:i'),
                'logs_count' => $rule->logs_count,
                'created_at' => $rule->created_at->format('Y-m-d H:i'),
            ];
        });

        return Inertia::render('Automations/Index', [
            'rules' => $rules,
            'filters' => $request->only(['status', 'trigger', 'search']),
            'triggerEvents' => AutomationRule::getTriggerEvents(),
        ]);
    }

    /**
     * Show the form for creating a new automation rule.
     */
    public function create()
    {
        return Inertia::render('Automations/Builder', [
            'rule' => null,
            'triggerEvents' => AutomationRule::getTriggerEvents(),
            'actionTypes' => AutomationRule::getActionTypes(),
            'conditionTypes' => AutomationRule::getConditionTypes(),
            'lists' => Auth::user()->accessibleLists()->select('id', 'name')->get(),
            'tags' => Tag::orderBy('name')->select('id', 'name')->get(),
            'messages' => Message::where('user_id', Auth::id())
                ->where('status', '!=', 'draft')
                ->select('id', 'subject')
                ->get(),
            'funnels' => Funnel::forUser(Auth::id())->select('id', 'name')->get(),
            'forms' => SubscriptionForm::where('user_id', Auth::id())->select('id', 'name')->get(),
            'customFields' => CustomField::where('user_id', Auth::id())->select('id', 'name', 'label')->get(),
            // CRM resources
            'pipelines' => CrmPipeline::where('user_id', Auth::id())->select('id', 'name')->get(),
            'stages' => CrmStage::whereHas('pipeline', fn($q) => $q->where('user_id', Auth::id()))->select('id', 'name', 'crm_pipeline_id')->get(),
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    /**
     * Store a newly created automation rule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'trigger_event' => 'required|string|in:' . implode(',', array_keys(AutomationRule::TRIGGER_EVENTS)),
            'trigger_config' => 'nullable|array',
            'conditions' => 'nullable|array',
            'condition_logic' => 'nullable|in:all,any',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.config' => 'nullable|array',
            'is_active' => 'boolean',
            'limit_per_subscriber' => 'boolean',
            'limit_count' => 'nullable|integer|min:1',
            'limit_period' => 'nullable|in:hour,day,week,month,ever',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['condition_logic'] = $validated['condition_logic'] ?? 'all';

        $rule = AutomationRule::create($validated);

        return redirect()->route('automations.index')
            ->with('success', __('Automatyzacja została utworzona.'));
    }

    /**
     * Show the form for editing an automation rule.
     */
    public function edit(AutomationRule $automation)
    {
        $this->authorize('update', $automation);

        return Inertia::render('Automations/Builder', [
            'rule' => [
                'id' => $automation->id,
                'name' => $automation->name,
                'description' => $automation->description,
                'trigger_event' => $automation->trigger_event,
                'trigger_config' => $automation->trigger_config ?? [],
                'conditions' => $automation->conditions ?? [],
                'condition_logic' => $automation->condition_logic,
                'actions' => $automation->actions ?? [],
                'is_active' => $automation->is_active,
                'limit_per_subscriber' => $automation->limit_per_subscriber,
                'limit_count' => $automation->limit_count,
                'limit_period' => $automation->limit_period,
            ],
            'triggerEvents' => AutomationRule::getTriggerEvents(),
            'actionTypes' => AutomationRule::getActionTypes(),
            'conditionTypes' => AutomationRule::getConditionTypes(),
            'lists' => Auth::user()->accessibleLists()->select('id', 'name')->get(),
            'tags' => Tag::orderBy('name')->select('id', 'name')->get(),
            'messages' => Message::where('user_id', Auth::id())
                ->where('status', '!=', 'draft')
                ->select('id', 'subject')
                ->get(),
            'funnels' => Funnel::forUser(Auth::id())->select('id', 'name')->get(),
            'forms' => SubscriptionForm::where('user_id', Auth::id())->select('id', 'name')->get(),
            'customFields' => CustomField::where('user_id', Auth::id())->select('id', 'name', 'label')->get(),
            // CRM resources
            'pipelines' => CrmPipeline::where('user_id', Auth::id())->select('id', 'name')->get(),
            'stages' => CrmStage::whereHas('pipeline', fn($q) => $q->where('user_id', Auth::id()))->select('id', 'name', 'crm_pipeline_id')->get(),
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    /**
     * Update the specified automation rule.
     */
    public function update(Request $request, AutomationRule $automation)
    {
        $this->authorize('update', $automation);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'trigger_event' => 'required|string|in:' . implode(',', array_keys(AutomationRule::TRIGGER_EVENTS)),
            'trigger_config' => 'nullable|array',
            'conditions' => 'nullable|array',
            'condition_logic' => 'nullable|in:all,any',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.config' => 'nullable|array',
            'is_active' => 'boolean',
            'limit_per_subscriber' => 'boolean',
            'limit_count' => 'nullable|integer|min:1',
            'limit_period' => 'nullable|in:hour,day,week,month,ever',
        ]);

        $automation->update($validated);

        return redirect()->route('automations.index')
            ->with('success', __('Automatyzacja została zaktualizowana.'));
    }

    /**
     * Remove the specified automation rule.
     */
    public function destroy(AutomationRule $automation)
    {
        $this->authorize('delete', $automation);

        $automation->delete();

        return redirect()->route('automations.index')
            ->with('success', __('Automatyzacja została usunięta.'));
    }

    /**
     * Duplicate an automation rule.
     */
    public function duplicate(AutomationRule $automation)
    {
        $this->authorize('update', $automation);

        $newRule = $automation->duplicate();

        return redirect()->route('automations.edit', $newRule)
            ->with('success', __('Automatyzacja została zduplikowana.'));
    }

    /**
     * Toggle automation rule status.
     */
    public function toggleStatus(AutomationRule $automation)
    {
        $this->authorize('update', $automation);

        $automation->update(['is_active' => !$automation->is_active]);

        return back()->with('success',
            $automation->is_active
                ? __('Automatyzacja została włączona.')
                : __('Automatyzacja została wyłączona.')
        );
    }

    /**
     * Show execution logs for an automation rule.
     */
    public function logs(Request $request, AutomationRule $automation)
    {
        $this->authorize('view', $automation);

        $logs = $automation->logs()
            ->with('subscriber:id,email,first_name,last_name')
            ->latest('executed_at')
            ->paginate(20)
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'subscriber_email' => $log->subscriber?->email ?? '-',
                    'subscriber_name' => $log->subscriber
                        ? trim($log->subscriber->first_name . ' ' . $log->subscriber->last_name) ?: '-'
                        : '-',
                    'trigger_event' => $log->trigger_event,
                    'actions_summary' => $log->actions_summary,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'status_color' => $log->status_color,
                    'error_message' => $log->error_message,
                    'execution_time_ms' => $log->execution_time_ms,
                    'executed_at' => $log->executed_at?->format('Y-m-d H:i:s'),
                ];
            });

        $stats = [
            'total' => $automation->logs()->count(),
            'success' => $automation->logs()->where('status', 'success')->count(),
            'failed' => $automation->logs()->where('status', 'failed')->count(),
            'skipped' => $automation->logs()->where('status', 'skipped')->count(),
        ];

        return Inertia::render('Automations/Logs', [
            'automation' => [
                'id' => $automation->id,
                'name' => $automation->name,
            ],
            'logs' => $logs,
            'stats' => $stats,
        ]);
    }

    /**
     * Get stats for automations dashboard widget.
     */
    public function stats()
    {
        $userId = Auth::id();

        $stats = [
            'total_rules' => AutomationRule::forUser($userId)->count(),
            'active_rules' => AutomationRule::forUser($userId)->active()->count(),
            'executions_24h' => AutomationRuleLog::whereHas('automationRule', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('executed_at', '>=', now()->subDay())->count(),
            'errors_24h' => AutomationRuleLog::whereHas('automationRule', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('executed_at', '>=', now()->subDay())->where('status', 'failed')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Restore default automations for the current user.
     */
    public function restoreDefaults()
    {
        $user = Auth::user();

        $seeder = new \Database\Seeders\DefaultAutomationsSeeder();
        $count = $seeder->restoreForUser($user);

        if ($count === 0) {
            return back()->with('info', __('Wszystkie domyślne automatyzacje są już aktywne.'));
        }

        return back()->with('success', __('Dodano :count brakujących automatyzacji.', ['count' => $count]));
    }

    /**
     * Check if user has any system automations.
     */
    public function hasSystemAutomations(): bool
    {
        return AutomationRule::forUser(Auth::id())
            ->where('is_system', true)
            ->exists();
    }
}
