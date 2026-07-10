<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\ContactList;
use App\Models\Funnel;
use App\Models\FunnelGoalConversion;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\Brain\PlaybookService;
use App\Services\Funnels\FunnelService;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 3 — Funnel Agent.
 *
 * Gives Brain access to NetSendo's most powerful automation primitive:
 * multi-step funnels with revenue goals. Creation goes through codified
 * PLAYBOOKS (welcome→offer, cart abandonment, win-back, post-purchase) —
 * proven shapes with LLM-generated content — never free-form step soup.
 * Activation is a send-tier action (funnels e-mail real people).
 */
class FunnelAgent extends BaseAgent
{
    public function __construct(
        protected AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
        protected PlaybookService $playbooks,
        protected FunnelService $funnelService,
    ) {
        parent::__construct($aiService, $knowledgeBase);
    }

    public function getName(): string
    {
        return 'funnel';
    }

    public function getLabel(): string
    {
        return __('brain.funnel.label');
    }

    public function getCapabilities(): array
    {
        return [
            'revenue_playbooks',
            'create_funnel_from_playbook',
            'funnel_management',
            'funnel_revenue_stats',
        ];
    }

    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);
        $langInstruction = $this->getLanguageInstruction($user);

        $actionsBlock = \App\Services\Brain\Tools\ToolRegistry::promptSection('funnel');
        $playbooksJson = json_encode(PlaybookService::catalog());
        $listsBlock = $this->buildListsContext($user);

        $prompt = <<<PROMPT
You are a marketing automation expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}

AVAILABLE PLAYBOOKS (proven revenue funnel shapes):
{$playbooksJson}

{$listsBlock}

{$knowledgeContext}

{$langInstruction}

Create a funnel plan in JSON:
{"title":"","description":"","steps":[{"action_type":"","title":"","description":"","config":{}}]}

{$actionsBlock}

IMPORTANT:
- New funnels are created as DRAFT. Add a separate activate_funnel step ONLY when the user explicitly wants the funnel live.
- welcome playbook requires list_id; cart_abandon/winback/post_purchase use a trigger tag.
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.3], $user, 'campaign');
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'funnel',
                $data['title'] ?? __('brain.funnel.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('FunnelAgent plan failed', ['error' => $e->getMessage()]);
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
                if (!empty($result['message'])) {
                    $messages[] = $result['message'];
                }
            } catch (\Exception $e) {
                $messages[] = "❌ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => implode("\n\n", $messages) ?: __('brain.funnel.done'),
        ];
    }

    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $langInstruction = $this->getLanguageInstruction($user);
        $playbooksJson = json_encode(PlaybookService::catalog());

        $prompt = "You are a marketing automation expert. Manual mode — advise only.\n"
            . "Intent: {$intent['intent']}\n\nAvailable playbooks: {$playbooksJson}\n{$knowledgeContext}\n\n{$langInstruction}\n\n"
            . "Explain which revenue funnel/playbook fits best and how to set it up in the NetSendo panel. Use emoji.";

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.5], $user, 'campaign');

        return ['type' => 'advice', 'message' => $response];
    }

    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'list_playbooks' => $this->listPlaybooks($step, $user),
            'create_funnel_from_playbook' => $this->createFromPlaybook($step, $user),
            'list_funnels' => $this->listFunnels($step, $user),
            'funnel_stats' => $this->funnelStats($step, $user),
            'activate_funnel' => $this->activateFunnel($step, $user),
            'pause_funnel' => $this->pauseFunnel($step, $user),
            default => $this->failUnknownAction($step),
        };
    }

    // === Step Executors ===

    protected function listPlaybooks(AiActionPlanStep $step, User $user): array
    {
        $catalog = PlaybookService::catalog();

        $msg = __('brain.funnel.playbooks_header') . "\n";
        foreach ($catalog as $playbook) {
            $trigger = $playbook['trigger_type'] === Funnel::TRIGGER_LIST_SIGNUP
                ? 'list signup'
                : "tag: {$playbook['default_tag']}";
            $msg .= "  • **{$playbook['key']}** — {$playbook['name']} ({$playbook['emails']} emails, trigger: {$trigger})\n";
        }

        return ['status' => 'completed', 'data' => $catalog, 'message' => $msg];
    }

    protected function createFromPlaybook(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $playbookKey = $config['playbook'] ?? null;

        if (!$playbookKey || !isset(PlaybookService::PLAYBOOKS[$playbookKey])) {
            return ['status' => 'failed', 'message' => __('brain.funnel.unknown_playbook', ['key' => (string) $playbookKey])];
        }

        // Validate list ownership when provided
        $options = [
            'topic' => $config['topic'] ?? '',
            'name' => $config['name'] ?? null,
            'tag' => $config['tag'] ?? null,
        ];
        if (!empty($config['list_id'])) {
            $listId = ContactList::where('user_id', $user->id)->whereIn('id', [(int) $config['list_id']])->value('id');
            if (!$listId) {
                return ['status' => 'failed', 'message' => __('brain.funnel.list_not_found', ['id' => $config['list_id']])];
            }
            $options['list_id'] = $listId;
        }

        if ($this->isDryRun($user)) {
            return [
                'status' => 'completed',
                'dry_run' => true,
                'message' => __('brain.funnel.dry_run_create', ['playbook' => $playbookKey]),
            ];
        }

        $result = $this->playbooks->instantiate($user, $playbookKey, $options);

        if (!$result['funnel']) {
            return [
                'status' => 'failed',
                'message' => __('brain.funnel.create_failed', ['errors' => implode('; ', $result['errors'])]),
            ];
        }

        $funnel = $result['funnel'];
        $validationNote = empty($result['errors'])
            ? __('brain.funnel.ready_to_activate')
            : '⚠️ ' . implode('; ', $result['errors']);

        return [
            'status' => 'completed',
            'funnel_id' => $funnel->id,
            'message_ids' => $result['message_ids'],
            'message' => __('brain.funnel.created', [
                'name' => $funnel->name,
                'id' => $funnel->id,
                'steps' => $funnel->steps->count(),
                'emails' => count($result['message_ids']),
            ]) . "\n  {$validationNote}",
        ];
    }

    protected function listFunnels(AiActionPlanStep $step, User $user): array
    {
        $funnels = Funnel::forUser($user->id)->withCount('steps')->orderByDesc('created_at')->limit(15)->get();

        if ($funnels->isEmpty()) {
            return ['status' => 'completed', 'data' => [], 'message' => __('brain.funnel.no_funnels')];
        }

        $msg = __('brain.funnel.list_header', ['count' => $funnels->count()]) . "\n";
        foreach ($funnels as $funnel) {
            $icon = match ($funnel->status) {
                Funnel::STATUS_ACTIVE => '🟢',
                Funnel::STATUS_PAUSED => '⏸️',
                default => '📝',
            };
            $msg .= "{$icon} #{$funnel->id} **{$funnel->name}** — {$funnel->status}, {$funnel->steps_count} steps, "
                . "👥 {$funnel->subscribers_count}\n";
        }

        return ['status' => 'completed', 'data' => $funnels->toArray(), 'message' => $msg];
    }

    protected function funnelStats(AiActionPlanStep $step, User $user): array
    {
        $funnel = $this->resolveFunnel($step, $user);
        if (!$funnel) {
            return ['status' => 'failed', 'message' => __('brain.funnel.not_found')];
        }

        $stats = $funnel->getStats();
        $revenue = 0.0;
        try {
            $revenue = (float) FunnelGoalConversion::getTotalRevenue($funnel->id);
        } catch (\Exception $e) {
            // goal conversions table may not exist
        }

        $msg = __('brain.funnel.stats_header', ['name' => $funnel->name, 'status' => $funnel->status]) . "\n"
            . "👥 " . __('brain.funnel.stats_subscribers', [
                'total' => $stats['total_subscribers'] ?? $funnel->subscribers_count,
                'completed' => $stats['completed'] ?? $funnel->completed_count,
            ]) . "\n"
            . "💰 " . __('brain.funnel.stats_revenue', ['amount' => number_format($revenue, 2)]);

        return [
            'status' => 'completed',
            'data' => array_merge($stats, ['revenue' => $revenue]),
            'message' => $msg,
        ];
    }

    protected function activateFunnel(AiActionPlanStep $step, User $user): array
    {
        $funnel = $this->resolveFunnel($step, $user);
        if (!$funnel) {
            return ['status' => 'failed', 'message' => __('brain.funnel.not_found')];
        }

        if ($funnel->isActive()) {
            return ['status' => 'completed', 'funnel_id' => $funnel->id, 'message' => __('brain.funnel.already_active', ['name' => $funnel->name])];
        }

        $errors = $this->funnelService->validate($funnel);
        if (!empty($errors)) {
            return [
                'status' => 'failed',
                'funnel_id' => $funnel->id,
                'message' => __('brain.funnel.validation_failed', ['errors' => implode('; ', $errors)]),
            ];
        }

        if ($this->isDryRun($user)) {
            return [
                'status' => 'completed',
                'funnel_id' => $funnel->id,
                'dry_run' => true,
                'message' => __('brain.funnel.dry_run_activate', ['name' => $funnel->name]),
            ];
        }

        if (!$funnel->activate()) {
            return ['status' => 'failed', 'funnel_id' => $funnel->id, 'message' => __('brain.funnel.activation_failed', ['name' => $funnel->name])];
        }

        return [
            'status' => 'completed',
            'funnel_id' => $funnel->id,
            'message' => __('brain.funnel.activated', ['name' => $funnel->name, 'id' => $funnel->id]),
        ];
    }

    protected function pauseFunnel(AiActionPlanStep $step, User $user): array
    {
        $funnel = $this->resolveFunnel($step, $user);
        if (!$funnel) {
            return ['status' => 'failed', 'message' => __('brain.funnel.not_found')];
        }

        $funnel->pause();

        return [
            'status' => 'completed',
            'funnel_id' => $funnel->id,
            'message' => __('brain.funnel.paused', ['name' => $funnel->name]),
        ];
    }

    // === Helpers ===

    protected function resolveFunnel(AiActionPlanStep $step, User $user): ?Funnel
    {
        $funnelId = $step->config['funnel_id'] ?? null;

        if (!$funnelId) {
            // Fall back to a funnel created earlier in this plan
            $createStep = $step->plan->steps()
                ->where('action_type', 'create_funnel_from_playbook')
                ->where('status', 'completed')
                ->first();
            $funnelId = $createStep?->result['funnel_id'] ?? null;
        }

        return $funnelId ? Funnel::forUser($user->id)->find($funnelId) : null;
    }

    protected function buildListsContext(User $user): string
    {
        try {
            $lists = ContactList::where('user_id', $user->id)
                ->withCount('subscribers')
                ->orderByDesc('subscribers_count')
                ->limit(15)
                ->get();

            if ($lists->isEmpty()) {
                return 'AVAILABLE MAILING LISTS: none';
            }

            $block = "AVAILABLE MAILING LISTS:\n";
            foreach ($lists as $list) {
                $block .= "  - ID: {$list->id} | \"{$list->name}\" | {$list->subscribers_count} subscribers\n";
            }

            return $block;
        } catch (\Exception $e) {
            return '';
        }
    }
}
