<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\CampaignArchitectService;
use App\Services\CampaignAdvisorService;
use Illuminate\Support\Facades\Log;

class CampaignAgent extends BaseAgent
{
    public function __construct(
        protected AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
        protected CampaignArchitectService $architectService,
        protected CampaignAdvisorService $advisorService,
    ) {
        parent::__construct($aiService, $knowledgeBase);
    }

    public function getName(): string
    {
        return 'campaign';
    }

    public function getLabel(): string
    {
        return __('brain.campaign.label');
    }

    public function getCapabilities(): array
    {
        return [
            'create_campaign',
            'plan_drip_sequence',
            'analyze_campaign_results',
            'optimize_send_time',
            'suggest_audience',
            'schedule_campaign',
        ];
    }

    /**
     * Check if we need more information before creating a campaign plan.
     */
    public function needsMoreInfo(array $intent, User $user, string $knowledgeContext = ''): bool
    {
        $params = $intent['parameters'] ?? [];

        // Cron tasks have auto-filled context — never block autonomous execution
        if (($intent['channel'] ?? '') === 'cron' || !empty($params['cron_task'])) {
            return false;
        }

        // If user already provided details via the info-gathering step, no more info needed
        if (!empty($params['user_details'])) {
            return false;
        }
        // Need at least topic/goal/product info to create a meaningful campaign
        return empty($params['topic']) && empty($params['goal']) && empty($params['product']);
    }

    /**
     * Generate campaign-specific questions for the user.
     */
    public function getInfoQuestions(array $intent, User $user, string $knowledgeContext = ''): string
    {
        // Fetch available lists for context
        $lists = ContactList::where('user_id', $user->id)->withCount('subscribers')->get();
        $listsInfo = $lists->map(fn($l) => "• {$l->name} ({$l->subscribers_count} subscribers)")->join("\n");

        $response = __('brain.campaign.info_header') . "\n\n"
            . __('brain.campaign.info_goal') . "\n"
            . __('brain.campaign.info_topic') . "\n"
            . __('brain.campaign.info_tone') . "\n"
            . __('brain.campaign.info_audience') . "\n";

        if ($listsInfo) {
            $response .= "\n" . __('brain.campaign.info_lists') . "\n{$listsInfo}\n";
        }

        $response .= "\n" . __('brain.campaign.info_when') . "\n\n"
            . __('brain.campaign.info_footer');

        return $response;
    }

    /**
     * Create a plan for campaign-related actions.
     */
    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $params = $intent['parameters'] ?? [];

        // For cron tasks, enrich intent with auto-context so AI has everything it needs
        $autoContextBlock = '';
        if (!empty($params['cron_task']) && !empty($params['auto_context'])) {
            $auto = $params['auto_context'];
            $autoContextBlock = "\n\nAUTOMATIC EXECUTION CONTEXT (cron mode — no user interaction available):\n";
            if (!empty($auto['lists'])) {
                $autoContextBlock .= "Available contact lists:\n";
                foreach ($auto['lists'] as $list) {
                    $autoContextBlock .= "  - \"{$list['name']}\" (ID: {$list['id']}, {$list['subscribers_count']} subscribers)\n";
                }
            }
            if (!empty($auto['recent_topics'])) {
                $autoContextBlock .= "Recently used topics (avoid repeating): " . implode(', ', $auto['recent_topics']) . "\n";
            }
            $autoContextBlock .= "IMPORTANT: Since this is a cron task, you MUST choose a concrete topic, audience, and tone. Do NOT leave them empty or ask for clarification. Pick the best option based on the available data.\n";
            // Remove auto_context from params to avoid huge JSON
            unset($params['auto_context']);
            unset($params['cron_task']);
        }

        $paramsJson = json_encode($params);
        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are an email marketing expert. The user wants to perform the following action:
Intent: {$intentDesc}
Parameters: {$paramsJson}
{$autoContextBlock}

{$knowledgeContext}

{$langInstruction}

Create a detailed campaign plan. Respond in JSON:
{
  "title": "short plan title",
  "description": "description of what the plan will achieve",
  "steps": [
    {
      "action_type": "action_type",
      "title": "step title",
      "description": "step description",
      "config": {}
    }
  ]
}

Available action_types:
- select_audience: select target audience (config: {list_ids: [], segment_criteria: {}})
- generate_content: generate message content (config: {type: "email"|"sms", tone: "", topic: ""})
- create_message: create message in the system (config: {subject: "", content_ref: "step_N"})
- schedule_send: schedule sending (config: {send_at: "datetime|immediate", list_id: N})
- create_automation: create automation (config: {trigger: "", actions: []})
- analyze_results: analyze results after sending (config: {campaign_id: N, wait_hours: 24})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.3], $user, 'campaign');
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'campaign',
                $data['title'] ?? __('brain.campaign.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('CampaignAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute a campaign plan.
     */
    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $stepReports = [];
        $hasErrors = false;

        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($step, $user);
                $detail = $result['message'] ?? '';
                $stepReports[] = "  {$step->step_order}. ✅ **{$step->title}**" . ($detail ? "\n     ↳ {$detail}" : '');
            } catch (\Exception $e) {
                $hasErrors = true;
                $stepReports[] = "  {$step->step_order}. ❌ **{$step->title}**\n     ↳ {$e->getMessage()}";
            }
        }

        $completedCount = count(array_filter($stepReports, fn($r) => str_contains($r, '✅')));
        $icon = $hasErrors ? '⚠️' : '✅';
        $report = "{$icon} **{$plan->title}**\n";

        if ($plan->description) {
            $report .= "{$plan->description}\n";
        }

        $report .= "\n📋 **Completed steps** ({$completedCount}/{$plan->total_steps}):\n"
            . implode("\n", $stepReports);

        return [
            'type' => 'execution_result',
            'message' => $report,
        ];
    }

    /**
     * Execute a specific campaign step action.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'select_audience' => $this->executeSelectAudience($step, $user),
            'generate_content' => $this->executeGenerateContent($step, $user),
            'create_message' => $this->executeCreateMessage($step, $user),
            'schedule_send' => $this->executeScheduleSend($step, $user),
            default => ['status' => 'completed', 'message' => "Action '{$step->action_type}' noted"],
        };
    }

    /**
     * Advise on campaign actions (manual mode).
     */
    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are an email marketing expert. The user is in manual mode and needs advice.
Intent: {$intentDesc}
Parameters: {$paramsJson}

{$knowledgeContext}

{$langInstruction}

Provide detailed step-by-step instructions on how the user can do this manually in the NetSendo panel.
Include best practices and optimization tips.
Respond in a readable format with emoji.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.5], $user, 'campaign');

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Step Executors ===

    protected function executeSelectAudience(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $listIds = $config['list_ids'] ?? [];

        if (empty($listIds)) {
            // Auto-select best lists based on criteria
            $lists = ContactList::where('user_id', $user->id)
                ->withCount('subscribers')
                ->orderByDesc('subscribers_count')
                ->limit(3)
                ->get();

            return [
                'status' => 'completed',
                'selected_lists' => $lists->pluck('id')->toArray(),
                'total_subscribers' => $lists->sum('subscribers_count'),
                'message' => __('brain.campaign.audience_selected', ['count' => $lists->count(), 'subscribers' => $lists->sum('subscribers_count')]),
            ];
        }

        $lists = ContactList::whereIn('id', $listIds)
            ->where('user_id', $user->id)
            ->get();

        return [
            'status' => 'completed',
            'selected_lists' => $lists->pluck('id')->toArray(),
            'total_subscribers' => $lists->sum('subscribers_count'),
        ];
    }

    protected function executeGenerateContent(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $knowledgeContext = $this->knowledgeBase->getContext($user, 'message');

        $type = $config['type'] ?? 'email';
        $tone = $config['tone'] ?? 'profesjonalny';
        $topic = $config['topic'] ?? '';

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
Generate {$type} marketing message content.

Topic/goal: {$topic}
Tone: {$tone}

{$knowledgeContext}

{$langInstruction}

Respond in JSON:
{
  "subject": "message subject (for email)",
  "preview_text": "preview text (for email)",
  "content": "message content (HTML for email, text for SMS)",
  "cta_text": "CTA button text",
  "variants": [
    {"subject": "alternative subject 1"},
    {"subject": "alternative subject 2"}
  ]
}
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.7], $user, 'content_generation');
        $data = $this->parseJson($response);

        return [
            'status' => 'completed',
            'generated_content' => $data,
        ];
    }

    protected function executeCreateMessage(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        // Look for generated content from a previous step
        $plan = $step->plan;
        $contentStep = $plan->steps()
            ->where('action_type', 'generate_content')
            ->where('status', 'completed')
            ->first();

        $content = $contentStep?->result['generated_content'] ?? null;
        $subject = $config['subject'] ?? $content['subject'] ?? __('brain.campaign.default_message');
        $body = $content['content'] ?? '<p>Message content</p>';

        $message = Message::create([
            'user_id' => $user->id,
            'name' => $subject,
            'subject' => $subject,
            'preheader' => $content['preview_text'] ?? '',
            'content' => $body,
            'type' => 'email',
            'status' => 'draft',
        ]);

        return [
            'status' => 'completed',
            'message_id' => $message->id,
            'message' => __('brain.campaign.message_created', ['subject' => $subject, 'id' => $message->id]),
        ];
    }

    protected function executeScheduleSend(AiActionPlanStep $step, User $user): array
    {
        // For safety, we create as draft — actual sending requires manual confirmation
        return [
            'status' => 'completed',
            'message' => __('brain.campaign.schedule_ready'),
            'note' => 'Auto-send disabled for safety — manual scheduling required',
        ];
    }
}
