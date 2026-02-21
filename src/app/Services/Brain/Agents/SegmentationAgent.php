<?php

namespace App\Services\Brain\Agents;

use App\Models\AbTest;
use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\CrmContact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SegmentationAgent extends BaseAgent
{
    public function getName(): string { return 'segmentation'; }
    public function getLabel(): string { return __('brain.segmentation.label'); }

    public function getCapabilities(): array
    {
        return [
            'create_segment', 'analyze_segments', 'suggest_segmentation',
            'manage_tags', 'automation_stats', 'create_automation',
            'update_automation', 'toggle_automation', 'delete_automation',
            'list_automations',
        ];
    }

    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $langInstruction = $this->getLanguageInstruction($user);

        // Gather available trigger events and action types for AI context
        $triggerEvents = collect(AutomationRule::TRIGGER_EVENTS)
            ->map(fn($label, $key) => "  {$key}: {$label}")
            ->join("\n");

        $actionTypes = collect(AutomationRule::ACTION_TYPES)
            ->map(fn($label, $key) => "  {$key}: {$label}")
            ->join("\n");

        $conditionTypes = collect(AutomationRule::CONDITION_TYPES)
            ->map(fn($label, $key) => "  {$key}: {$label}")
            ->join("\n");

        // Current automations for context
        $existingAutomations = AutomationRule::forUser($user->id)
            ->select('id', 'name', 'trigger_event', 'is_active')
            ->limit(10)
            ->get()
            ->map(fn($r) => "  #{$r->id}: {$r->name} (trigger: {$r->trigger_event}, active: " . ($r->is_active ? 'yes' : 'no') . ")")
            ->join("\n");

        $prompt = <<<PROMPT
You are a marketing segmentation and automation expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}
{$knowledgeContext}

{$langInstruction}

EXISTING AUTOMATIONS:
{$existingAutomations}

AVAILABLE TRIGGER EVENTS:
{$triggerEvents}

AVAILABLE ACTION TYPES:
{$actionTypes}

AVAILABLE CONDITION TYPES:
{$conditionTypes}

Create a plan in JSON:
{"title":"","description":"","steps":[{"action_type":"","title":"","description":"","config":{}}]}

Available step action_types:
- analyze_tag_distribution: show tag distribution (config: {limit: 15})
- analyze_score_distribution: show scoring segments (config: {})
- create_tag: create tag (config: {name: "", color: "#hex"})
- apply_tag: apply tag to subscribers (config: {tag_name: "", criteria: {status: "", min_score: N}})
- suggest_segments: AI segmentation recommendations (config: {})
- automation_stats: automation statistics (config: {days: 7})
- create_automation: create automation rule (config: {name: "", trigger_event: "", trigger_config: {}, conditions: [{type: "", config: {}}], condition_logic: "and"|"or", actions: [{type: "", config: {}}], is_active: true|false, limit_per_subscriber: true|false, limit_count: N, limit_period: "hour"|"day"|"week"|"month"|"ever"})
- update_automation: update existing automation (config: {automation_id: N, name: "", is_active: true|false, trigger_event: "", actions: [...]})
- toggle_automation: enable/disable automation (config: {automation_id: N})
- delete_automation: delete automation (config: {automation_id: N})
- list_automations: list all automations with stats (config: {})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.3], $user, 'segmentation');
            $data = $this->parseJson($response);
            if (!$data || empty($data['steps'])) return null;

            return $this->createPlan($user, $intent['intent'] ?? 'segmentation',
                $data['title'] ?? __('brain.segmentation.plan_title'), $data['description'] ?? null, $data['steps']);
        } catch (\Exception $e) {
            Log::error('SegmentationAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $messages = [];

        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($step, $user);
                if (!empty($result['message'])) $messages[] = $result['message'];
            } catch (\Exception $e) {
                $messages[] = "⚠️ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => implode("\n\n---\n\n", $messages) ?: __('brain.segmentation.done'),
        ];
    }

    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'analyze_tag_distribution' => $this->analyzeTagDistribution($step, $user),
            'analyze_score_distribution' => $this->analyzeScoreDistribution($step, $user),
            'create_tag' => $this->createTag($step, $user),
            'apply_tag' => $this->applyTag($step, $user),
            'suggest_segments' => $this->suggestSegments($step, $user),
            'automation_stats' => $this->automationStats($step, $user),
            'create_automation' => $this->createAutomation($step, $user),
            'update_automation' => $this->updateAutomation($step, $user),
            'toggle_automation' => $this->toggleAutomation($step, $user),
            'delete_automation' => $this->deleteAutomation($step, $user),
            'list_automations' => $this->listAutomations($step, $user),
            default => ['status' => 'completed', 'message' => "Action noted"],
        };
    }

    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $tagCount = Tag::count();
        $contactCount = CrmContact::forUser($user->id)->count();
        $rulesCount = AutomationRule::forUser($user->id)->count();

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are a segmentation expert. Current state:
- Tags: {$tagCount}, CRM Contacts: {$contactCount}, Automations: {$rulesCount}

Question: {$intent['intent']}
{$knowledgeContext}

{$langInstruction}

Provide segmentation advice with specific steps. Use emoji.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.5], $user, 'segmentation');
        return ['type' => 'advice', 'message' => $response];
    }

    // === Tag & Segment Executors ===

    protected function analyzeTagDistribution(AiActionPlanStep $step, User $user): array
    {
        $limit = $step->config['limit'] ?? 15;

        $tags = Tag::withCount(['subscribers' => function ($q) use ($user) {
            $q->where('subscribers.user_id', $user->id);
        }])->orderByDesc('subscribers_count')->take($limit)->get();

        if ($tags->isEmpty()) {
            return ['status' => 'completed', 'message' => __('brain.segmentation.no_tags')];
        }

        $totalSubs = Subscriber::where('user_id', $user->id)->active()->count();
        $msg = __('brain.segmentation.tag_distribution', ['limit' => $limit]) . "\n\n";

        foreach ($tags as $tag) {
            $pct = $totalSubs > 0 ? round(($tag->subscribers_count / $totalSubs) * 100, 1) : 0;
            $bar = str_repeat('█', min(20, (int)($pct / 5))) . str_repeat('░', max(0, 20 - (int)($pct / 5)));
            $msg .= "**{$tag->name}**: {$tag->subscribers_count} ({$pct}%) {$bar}\n";
        }

        return ['status' => 'completed', 'message' => $msg];
    }

    protected function analyzeScoreDistribution(AiActionPlanStep $step, User $user): array
    {
        $contacts = CrmContact::forUser($user->id)->get();

        $segments = [
            'cold' => ['min' => 0, 'max' => 25, 'label' => __('brain.segmentation.cold'), 'count' => 0],
            'warm' => ['min' => 26, 'max' => 50, 'label' => __('brain.segmentation.warm'), 'count' => 0],
            'hot' => ['min' => 51, 'max' => 75, 'label' => __('brain.segmentation.hot'), 'count' => 0],
            'super_hot' => ['min' => 76, 'max' => PHP_INT_MAX, 'label' => __('brain.segmentation.super_hot'), 'count' => 0],
        ];

        foreach ($contacts as $c) {
            foreach ($segments as &$seg) {
                if ($c->score >= $seg['min'] && $c->score <= $seg['max']) { $seg['count']++; break; }
            }
        }

        $total = $contacts->count();
        $msg = __('brain.segmentation.score_segments', ['total' => $total]) . "\n\n";
        foreach ($segments as $seg) {
            $pct = $total > 0 ? round(($seg['count'] / $total) * 100, 1) : 0;
            $msg .= "{$seg['label']}: {$seg['count']} ({$pct}%)\n";
        }

        return ['status' => 'completed', 'message' => $msg];
    }

    protected function createTag(AiActionPlanStep $step, User $user): array
    {
        $name = $step->config['name'] ?? null;
        if (!$name) return ['status' => 'failed', 'message' => __('brain.segmentation.tag_name_missing')];

        $existing = Tag::where('name', $name)->where('user_id', $user->id)->first();
        if ($existing) return ['status' => 'completed', 'tag_id' => $existing->id, 'message' => __('brain.segmentation.tag_exists', ['name' => $name, 'id' => $existing->id])];

        $tag = Tag::create([
            'name' => $name,
            'color' => $step->config['color'] ?? '#6366f1',
        ]);

        return ['status' => 'completed', 'tag_id' => $tag->id, 'message' => __('brain.segmentation.tag_created', ['name' => $name, 'id' => $tag->id])];
    }

    protected function applyTag(AiActionPlanStep $step, User $user): array
    {
        $tagName = $step->config['tag_name'] ?? null;
        if (!$tagName) return ['status' => 'failed', 'message' => __('brain.segmentation.tag_name_missing')];

        $tag = Tag::where('name', $tagName)->where('user_id', $user->id)->first();
        if (!$tag) $tag = Tag::create(['name' => $tagName, 'user_id' => $user->id, 'color' => '#6366f1']);

        $query = Subscriber::where('user_id', $user->id)->active();
        $criteria = $step->config['criteria'] ?? [];

        if (!empty($criteria['min_score'])) {
            $contactIds = CrmContact::forUser($user->id)
                ->where('score', '>=', $criteria['min_score'])->pluck('subscriber_id');
            $query->whereIn('id', $contactIds);
        }

        $subscribers = $query->limit(1000)->get();
        $attached = 0;

        foreach ($subscribers as $sub) {
            if (!$sub->tags()->where('tag_id', $tag->id)->exists()) {
                $sub->tags()->attach($tag->id);
                $attached++;
            }
        }

        return ['status' => 'completed', 'message' => __('brain.segmentation.tag_applied', ['name' => $tagName, 'count' => $attached])];
    }

    protected function suggestSegments(AiActionPlanStep $step, User $user): array
    {
        $totalSubs = Subscriber::where('user_id', $user->id)->count();
        $activeSubs = Subscriber::where('user_id', $user->id)->active()->count();
        $tagCount = Tag::withCount(['subscribers' => fn($q) => $q->where('subscribers.user_id', $user->id)])->get();
        $crmCount = CrmContact::forUser($user->id)->count();
        $hotLeads = CrmContact::forUser($user->id)->hotLeads()->count();

        $tagSummary = $tagCount->take(10)->map(fn($t) => "{$t->name}: {$t->subscribers_count}")->join(', ');

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are a marketing segmentation expert. Based on the user's data, suggest segments:

Subscribers: {$totalSubs} (active: {$activeSubs})
CRM Contacts: {$crmCount} (hot leads: {$hotLeads})
Existing tags: {$tagSummary}

{$langInstruction}

Suggest 3-5 new segments with criteria and recommended actions.
Use emoji. Be specific.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.6]);
        return ['status' => 'completed', 'message' => $response];
    }

    // === Automation Executors ===

    protected function automationStats(AiActionPlanStep $step, User $user): array
    {
        $days = $step->config['days'] ?? 7;
        $since = Carbon::now()->subDays($days);

        $totalRules = AutomationRule::forUser($user->id)->count();
        $activeRules = AutomationRule::forUser($user->id)->active()->count();
        $ruleIds = AutomationRule::forUser($user->id)->pluck('id');

        $execs = AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
            ->where('executed_at', '>=', $since)->count();
        $successes = AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
            ->where('executed_at', '>=', $since)->where('status', 'success')->count();
        $successRate = $execs > 0 ? round(($successes / $execs) * 100, 1) : 100;

        $msg = __('brain.segmentation.automation_header', ['days' => $days]) . "\n\n";
        $msg .= __('brain.segmentation.automation_rules', ['active' => $activeRules, 'total' => $totalRules]) . "\n";
        $msg .= __('brain.segmentation.automation_execs', ['count' => $execs]) . "\n";
        $msg .= __('brain.segmentation.automation_success', ['rate' => $successRate]);

        return ['status' => 'completed', 'message' => $msg];
    }

    protected function createAutomation(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $name = $config['name'] ?? null;
        $triggerEvent = $config['trigger_event'] ?? null;

        if (!$name || !$triggerEvent) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_missing_fields')];
        }

        // Validate trigger event
        if (!array_key_exists($triggerEvent, AutomationRule::TRIGGER_EVENTS)) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_invalid_trigger', ['trigger' => $triggerEvent])];
        }

        // Validate actions
        $actions = $config['actions'] ?? [];
        if (empty($actions)) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_no_actions')];
        }

        $rule = AutomationRule::create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => $config['description'] ?? null,
            'trigger_event' => $triggerEvent,
            'trigger_config' => $config['trigger_config'] ?? [],
            'conditions' => $config['conditions'] ?? [],
            'condition_logic' => $config['condition_logic'] ?? 'and',
            'actions' => $actions,
            'is_active' => $config['is_active'] ?? false, // Default inactive for safety
            'limit_per_subscriber' => $config['limit_per_subscriber'] ?? true,
            'limit_count' => $config['limit_count'] ?? 1,
            'limit_period' => $config['limit_period'] ?? 'ever',
        ]);

        $statusLabel = $rule->is_active
            ? '✅ ' . __('brain.segmentation.automation_active')
            : '⏸️ ' . __('brain.segmentation.automation_inactive');

        $triggerLabel = AutomationRule::TRIGGER_EVENTS[$triggerEvent] ?? $triggerEvent;
        $actionsCount = count($actions);

        return [
            'status' => 'completed',
            'automation_id' => $rule->id,
            'message' => __('brain.segmentation.automation_created', [
                'name' => $name,
                'id' => $rule->id,
                'trigger' => $triggerLabel,
                'actions' => $actionsCount,
                'status' => $statusLabel,
            ]),
        ];
    }

    protected function updateAutomation(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $automationId = $config['automation_id'] ?? null;

        if (!$automationId) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_missing_id')];
        }

        $rule = AutomationRule::forUser($user->id)->find($automationId);
        if (!$rule) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_not_found', ['id' => $automationId])];
        }

        $updates = [];
        if (isset($config['name'])) $updates['name'] = $config['name'];
        if (isset($config['description'])) $updates['description'] = $config['description'];
        if (isset($config['trigger_event'])) $updates['trigger_event'] = $config['trigger_event'];
        if (isset($config['trigger_config'])) $updates['trigger_config'] = $config['trigger_config'];
        if (isset($config['conditions'])) $updates['conditions'] = $config['conditions'];
        if (isset($config['condition_logic'])) $updates['condition_logic'] = $config['condition_logic'];
        if (isset($config['actions'])) $updates['actions'] = $config['actions'];
        if (isset($config['is_active'])) $updates['is_active'] = $config['is_active'];
        if (isset($config['limit_per_subscriber'])) $updates['limit_per_subscriber'] = $config['limit_per_subscriber'];
        if (isset($config['limit_count'])) $updates['limit_count'] = $config['limit_count'];
        if (isset($config['limit_period'])) $updates['limit_period'] = $config['limit_period'];

        if (empty($updates)) {
            return ['status' => 'completed', 'message' => __('brain.segmentation.automation_no_changes')];
        }

        $rule->update($updates);

        return [
            'status' => 'completed',
            'automation_id' => $rule->id,
            'message' => __('brain.segmentation.automation_updated', [
                'name' => $rule->name,
                'id' => $rule->id,
                'fields' => implode(', ', array_keys($updates)),
            ]),
        ];
    }

    protected function toggleAutomation(AiActionPlanStep $step, User $user): array
    {
        $automationId = $step->config['automation_id'] ?? null;

        if (!$automationId) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_missing_id')];
        }

        $rule = AutomationRule::forUser($user->id)->find($automationId);
        if (!$rule) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_not_found', ['id' => $automationId])];
        }

        $newState = !$rule->is_active;
        $rule->update(['is_active' => $newState]);

        $stateLabel = $newState
            ? '✅ ' . __('brain.segmentation.automation_active')
            : '⏸️ ' . __('brain.segmentation.automation_inactive');

        return [
            'status' => 'completed',
            'automation_id' => $rule->id,
            'message' => __('brain.segmentation.automation_toggled', [
                'name' => $rule->name,
                'id' => $rule->id,
                'state' => $stateLabel,
            ]),
        ];
    }

    protected function deleteAutomation(AiActionPlanStep $step, User $user): array
    {
        $automationId = $step->config['automation_id'] ?? null;

        if (!$automationId) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_missing_id')];
        }

        $rule = AutomationRule::forUser($user->id)->find($automationId);
        if (!$rule) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_not_found', ['id' => $automationId])];
        }

        // Don't delete system automations
        if ($rule->is_system) {
            return ['status' => 'failed', 'message' => __('brain.segmentation.automation_system_protected', ['name' => $rule->name])];
        }

        $name = $rule->name;
        $rule->delete();

        return [
            'status' => 'completed',
            'message' => __('brain.segmentation.automation_deleted', ['name' => $name, 'id' => $automationId]),
        ];
    }

    protected function listAutomations(AiActionPlanStep $step, User $user): array
    {
        $rules = AutomationRule::forUser($user->id)
            ->orderByDesc('is_active')
            ->orderByDesc('last_executed_at')
            ->get();

        if ($rules->isEmpty()) {
            return ['status' => 'completed', 'message' => __('brain.segmentation.automation_none')];
        }

        $msg = __('brain.segmentation.automation_list_header', ['count' => $rules->count()]) . "\n\n";

        foreach ($rules as $rule) {
            $statusIcon = $rule->is_active ? '✅' : '⏸️';
            $triggerLabel = AutomationRule::TRIGGER_EVENTS[$rule->trigger_event] ?? $rule->trigger_event;
            $actionsCount = is_array($rule->actions) ? count($rule->actions) : 0;
            $execCount = $rule->execution_count ?? 0;
            $lastExec = $rule->last_executed_at ? $rule->last_executed_at->format('d.m.Y H:i') : '-';

            $msg .= "{$statusIcon} **#{$rule->id} {$rule->name}**\n";
            $msg .= "  🎯 {$triggerLabel} → {$actionsCount} " . __('brain.segmentation.automation_actions_label') . "\n";
            $msg .= "  🔄 {$execCount}x | ⏰ {$lastExec}\n\n";
        }

        return ['status' => 'completed', 'message' => $msg];
    }
}
