<?php

namespace App\Services\Brain\Agents;

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
        ];
    }

    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
Jesteś ekspertem segmentacji i automatyzacji marketingu. Użytkownik chce:
Intencja: {$intentDesc}
Parametry: {$paramsJson}
{$knowledgeContext}

Stwórz plan w JSON:
{"title":"","description":"","steps":[{"action_type":"","title":"","description":"","config":{}}]}

Dostępne action_types:
- analyze_tag_distribution: pokaż rozkład tagów (config: {limit: 15})
- analyze_score_distribution: pokaż segmenty scoring (config: {})
- create_tag: stwórz tag (config: {name: "", color: "#hex"})
- apply_tag: przypisz tag subskrybentom (config: {tag_name: "", criteria: {status: "", min_score: N}})
- suggest_segments: rekomendacje segmentacji AI (config: {})
- automation_stats: statystyki automatyzacji (config: {days: 7})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 1500, 'temperature' => 0.3]);
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
            default => ['status' => 'completed', 'message' => "Action noted"],
        };
    }

    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $tagCount = Tag::count();
        $contactCount = CrmContact::forUser($user->id)->count();
        $rulesCount = AutomationRule::forUser($user->id)->count();

        $prompt = <<<PROMPT
Jesteś ekspertem segmentacji. Stan:
- Tagów: {$tagCount}, Kontaktów CRM: {$contactCount}, Automatyzacji: {$rulesCount}

Pytanie: {$intent['intent']}
{$knowledgeContext}

Podaj porady dotyczące segmentacji z konkretnymi krokami. Użyj emoji.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.5]);
        return ['type' => 'advice', 'message' => $response];
    }

    // === Step Executors ===

    protected function analyzeTagDistribution(AiActionPlanStep $step, User $user): array
    {
        $limit = $step->config['limit'] ?? 15;

        $tags = Tag::withCount(['subscribers' => function ($q) use ($user) {
            $q->where('subscribers.user_id', $user->id);
        }])->orderByDesc('subscribers_count')->take($limit)->get();

        if ($tags->isEmpty()) {
            return ['status' => 'completed', 'message' => __('brain.segmentation.no_tags')];
        }

        $totalSubs = Subscriber::where('user_id', $user->id)->subscribed()->count();
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

        $existing = Tag::where('name', $name)->first();
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

        $tag = Tag::where('name', $tagName)->first();
        if (!$tag) $tag = Tag::create(['name' => $tagName, 'color' => '#6366f1']);

        $query = Subscriber::where('user_id', $user->id)->subscribed();
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
        $activeSubs = Subscriber::where('user_id', $user->id)->subscribed()->count();
        $tagCount = Tag::withCount(['subscribers' => fn($q) => $q->where('subscribers.user_id', $user->id)])->get();
        $crmCount = CrmContact::forUser($user->id)->count();
        $hotLeads = CrmContact::forUser($user->id)->hotLeads()->count();

        $tagSummary = $tagCount->take(10)->map(fn($t) => "{$t->name}: {$t->subscribers_count}")->join(', ');

        $prompt = <<<PROMPT
Jesteś ekspertem segmentacji marketingowej. Na podstawie danych użytkownika zaproponuj segmenty:

Subskrybenci: {$totalSubs} (aktywni: {$activeSubs})
Kontakty CRM: {$crmCount} (hot leads: {$hotLeads})
Istniejące tagi: {$tagSummary}

Zaproponuj 3-5 nowych segmentów z kryteriami i rekomendowanymi akcjami.
Użyj emoji. Bądź konkretny.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.6]);
        return ['status' => 'completed', 'message' => $response];
    }

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
}
