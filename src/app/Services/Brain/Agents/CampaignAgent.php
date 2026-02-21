<?php

namespace App\Services\Brain\Agents;

use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\ContactList;
use App\Models\CrmContact;
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
            'create_ab_test',
            'check_ab_results',
            'list_ab_tests',
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

        // Always provide available lists and CRM data for AI context
        $listsBlock = $this->buildAvailableListsContext($user);
        $crmBlock = $this->buildCrmSegmentsContext($user);

        // For cron tasks, enrich intent with auto-context so AI has everything it needs
        $autoContextBlock = '';
        if (!empty($params['cron_task']) && !empty($params['auto_context'])) {
            $auto = $params['auto_context'];
            $autoContextBlock = "\n\nAUTOMATIC EXECUTION CONTEXT (cron mode — no user interaction available):\n";
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

{$listsBlock}

{$crmBlock}

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
- select_audience: select target audience (config: {list_ids: [N, ...], crm_contact_ids: [N, ...], crm_segment: "all"|"hot_leads"|"warm"|"cold"})
- generate_content: generate message content (config: {type: "email"|"sms", tone: "", topic: ""})
- create_message: create message in the system (config: {subject: "", content_ref: "step_N"})
- schedule_send: schedule sending (config: {send_at: "YYYY-MM-DD HH:MM"|"immediate", list_id: N, message_id: N})
- analyze_results: analyze results after sending (config: {campaign_id: N, wait_hours: 24})
- create_ab_test: create A/B test for a message (config: {message_id: N, test_type: "subject"|"content"|"sender"|"send_time"|"full", winning_metric: "open_rate"|"click_rate", sample_percentage: 20, test_duration_hours: 24, auto_select_winner: true, variants: [{subject: "", preheader: ""}, {subject: "", preheader: ""}]})
- check_ab_results: check results of an A/B test (config: {ab_test_id: N})
- list_ab_tests: list all A/B tests with status (config: {})

NOTE: When selecting audience, you can use list_ids for mailing lists AND/OR crm_contact_ids/crm_segment for CRM contacts.
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.3], $user, 'campaign');
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

    // === Context Builders ===

    /**
     * Build available mailing lists context for AI prompt.
     */
    protected function buildAvailableListsContext(User $user): string
    {
        try {
            $lists = ContactList::where('user_id', $user->id)
                ->withCount('subscribers')
                ->orderByDesc('subscribers_count')
                ->limit(20)
                ->get();

            if ($lists->isEmpty()) {
                return "AVAILABLE MAILING LISTS: none";
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

    /**
     * Build CRM contact segments context for AI prompt.
     */
    protected function buildCrmSegmentsContext(User $user): string
    {
        try {
            $totalContacts = CrmContact::forUser($user->id)->count();
            if ($totalContacts === 0) {
                return "CRM CONTACTS: none";
            }

            $hotLeads = CrmContact::forUser($user->id)->hotLeads(50)->count();
            $qualified = CrmContact::forUser($user->id)->withStatus('qualified')->count();
            $leads = CrmContact::forUser($user->id)->withStatus('lead')->count();

            $block = "CRM CONTACT SEGMENTS (can be targeted via crm_segment in select_audience):\n";
            $block .= "  - all: {$totalContacts} total CRM contacts\n";
            $block .= "  - hot_leads: {$hotLeads} contacts (score ≥ 50, recently active)\n";
            $block .= "  - warm: {$qualified} contacts (qualified status)\n";
            $block .= "  - cold: {$leads} contacts (lead status)\n";
            return $block;
        } catch (\Exception $e) {
            return '';
        }
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
            'create_ab_test' => $this->executeCreateAbTest($step, $user),
            'check_ab_results' => $this->executeCheckAbResults($step, $user),
            'list_ab_tests' => $this->executeListAbTests($step, $user),
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

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.5], $user, 'campaign');

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
        $crmContactIds = $config['crm_contact_ids'] ?? [];
        $crmSegment = $config['crm_segment'] ?? null;

        $selectedLists = collect();
        $crmContacts = collect();
        $messages = [];

        // Select mailing lists
        if (!empty($listIds)) {
            $selectedLists = ContactList::whereIn('id', $listIds)
                ->where('user_id', $user->id)
                ->withCount('subscribers')
                ->get();
        } elseif (empty($crmContactIds) && empty($crmSegment)) {
            // Auto-select best lists if nothing specified
            $selectedLists = ContactList::where('user_id', $user->id)
                ->withCount('subscribers')
                ->orderByDesc('subscribers_count')
                ->limit(3)
                ->get();
        }

        if ($selectedLists->isNotEmpty()) {
            $listsNames = $selectedLists->pluck('name')->join(', ');
            $messages[] = __('brain.campaign.audience_selected', [
                'count' => $selectedLists->count(),
                'subscribers' => $selectedLists->sum('subscribers_count'),
            ]) . " ({$listsNames})";
        }

        // Select CRM contacts by IDs
        if (!empty($crmContactIds)) {
            $crmContacts = CrmContact::forUser($user->id)
                ->whereIn('id', $crmContactIds)
                ->get();
            $messages[] = __('brain.campaign.crm_contacts_selected', ['count' => $crmContacts->count()]);
        }

        // Select CRM contacts by segment
        if ($crmSegment && empty($crmContactIds)) {
            $query = CrmContact::forUser($user->id);
            $segmentLabel = $crmSegment;

            switch ($crmSegment) {
                case 'hot_leads':
                    $query->hotLeads(50);
                    $segmentLabel = 'Hot Leads (score ≥ 50)';
                    break;
                case 'warm':
                    $query->withStatus('qualified');
                    $segmentLabel = 'Qualified';
                    break;
                case 'cold':
                    $query->withStatus('lead');
                    $segmentLabel = 'Cold leads';
                    break;
                default:
                    // 'all' — no filter
                    break;
            }

            $crmContacts = $query->limit(500)->get();
            $messages[] = __('brain.campaign.crm_segment_selected', [
                'segment' => $segmentLabel,
                'count' => $crmContacts->count(),
            ]);
        }

        // Extract subscriber IDs from CRM contacts
        $crmSubscriberIds = $crmContacts
            ->pluck('subscriber_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return [
            'status' => 'completed',
            'selected_lists' => $selectedLists->pluck('id')->toArray(),
            'total_subscribers' => $selectedLists->sum('subscribers_count') + count($crmSubscriberIds),
            'crm_contact_ids' => $crmContacts->pluck('id')->toArray(),
            'crm_subscriber_ids' => $crmSubscriberIds,
            'message' => implode("\n", $messages),
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

        $response = $this->callAi($prompt, ['max_tokens' => 6000, 'temperature' => 0.7], $user, 'content_generation');
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
        $config = $step->config;
        $sendAt = $config['send_at'] ?? null;
        $listId = $config['list_id'] ?? null;
        $messageId = $config['message_id'] ?? null;

        // Try to get message_id from a previous create_message step
        if (!$messageId) {
            $plan = $step->plan;
            $messageStep = $plan->steps()
                ->where('action_type', 'create_message')
                ->where('status', 'completed')
                ->first();
            $messageId = $messageStep?->result['message_id'] ?? null;
        }

        if (!$messageId) {
            return [
                'status' => 'completed',
                'message' => __('brain.campaign.schedule_ready'),
                'note' => 'No message ID found — go to panel to schedule manually.',
            ];
        }

        $message = Message::where('user_id', $user->id)->find($messageId);
        if (!$message) {
            return [
                'status' => 'failed',
                'message' => __('brain.campaign.ab_message_not_found', ['id' => $messageId]),
            ];
        }

        // Get list_id from previous select_audience step if not provided
        if (!$listId) {
            $plan = $step->plan;
            $audienceStep = $plan->steps()
                ->where('action_type', 'select_audience')
                ->where('status', 'completed')
                ->first();
            $selectedLists = $audienceStep?->result['selected_lists'] ?? [];
            $listId = $selectedLists[0] ?? null;
        }

        // Set scheduling metadata on the message
        $updates = ['status' => 'draft'];

        if ($sendAt && $sendAt !== 'immediate') {
            try {
                $scheduledAt = \Carbon\Carbon::parse($sendAt);
                $updates['scheduled_at'] = $scheduledAt;
                $updates['status'] = 'scheduled';
            } catch (\Exception $e) {
                // Invalid date — keep as draft
            }
        }

        if ($listId) {
            $updates['contact_list_id'] = $listId;
        }

        $message->update($updates);

        $scheduledInfo = isset($updates['scheduled_at'])
            ? $updates['scheduled_at']->format('d.m.Y H:i')
            : 'draft';

        return [
            'status' => 'completed',
            'message_id' => $message->id,
            'message' => __('brain.campaign.schedule_created', [
                'subject' => $message->subject ?? $message->name,
                'schedule' => $scheduledInfo,
                'list' => $listId ? (ContactList::find($listId)?->name ?? "ID:{$listId}") : '-',
            ]),
        ];
    }

    // === A/B Test Executors ===

    protected function executeCreateAbTest(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        // Find message — either from config or from a previous create_message step
        $messageId = $config['message_id'] ?? null;

        if (!$messageId) {
            $plan = $step->plan;
            $messageStep = $plan->steps()
                ->where('action_type', 'create_message')
                ->where('status', 'completed')
                ->first();
            $messageId = $messageStep?->result['message_id'] ?? null;
        }

        if (!$messageId) {
            return ['status' => 'failed', 'message' => __('brain.campaign.ab_no_message')];
        }

        $message = Message::where('user_id', $user->id)->find($messageId);
        if (!$message) {
            return ['status' => 'failed', 'message' => __('brain.campaign.ab_message_not_found', ['id' => $messageId])];
        }

        $testType = $config['test_type'] ?? AbTest::TYPE_SUBJECT;
        $winningMetric = $config['winning_metric'] ?? AbTest::METRIC_OPEN_RATE;
        $samplePct = $config['sample_percentage'] ?? 20;
        $durationHours = $config['test_duration_hours'] ?? 24;
        $autoWinner = $config['auto_select_winner'] ?? true;

        // Create the A/B test
        $abTest = AbTest::create([
            'message_id' => $messageId,
            'user_id' => $user->id,
            'name' => $config['name'] ?? __('brain.campaign.ab_test_name', ['subject' => mb_substr($message->subject ?? $message->name, 0, 30)]),
            'status' => AbTest::STATUS_DRAFT,
            'test_type' => $testType,
            'winning_metric' => $winningMetric,
            'sample_percentage' => min(50, max(5, $samplePct)),
            'test_duration_hours' => min(168, max(1, $durationHours)),
            'auto_select_winner' => $autoWinner,
            'confidence_threshold' => $config['confidence_threshold'] ?? 95,
        ]);

        // Create variants
        $variants = $config['variants'] ?? [];
        if (empty($variants)) {
            // Auto-generate 2 variants if none provided
            $variants = [
                ['subject' => $message->subject, 'is_control' => true],
                ['subject' => $message->subject . ' — sprawdź!', 'is_control' => false],
            ];
        }

        $createdVariants = [];
        foreach ($variants as $i => $variantData) {
            $letter = AbTestVariant::VARIANT_LETTERS[$i] ?? chr(65 + $i);

            $variant = AbTestVariant::create([
                'ab_test_id' => $abTest->id,
                'variant_letter' => $letter,
                'subject' => $variantData['subject'] ?? $message->subject,
                'preheader' => $variantData['preheader'] ?? $message->preheader,
                'content' => $variantData['content'] ?? null,
                'from_name' => $variantData['from_name'] ?? null,
                'from_email' => $variantData['from_email'] ?? null,
                'weight' => 50,
                'is_control' => $variantData['is_control'] ?? ($i === 0),
                'is_ai_generated' => $i > 0,
            ]);

            $createdVariants[] = "{$letter}: \"{$variant->subject}\"";
        }

        $variantsList = implode("\n  ", $createdVariants);
        $testTypeLabel = match ($testType) {
            'subject' => 'Temat',
            'content' => 'Treść',
            'sender' => 'Nadawca',
            'send_time' => 'Czas wysyłki',
            'full' => 'Pełny test',
            default => $testType,
        };

        return [
            'status' => 'completed',
            'ab_test_id' => $abTest->id,
            'message' => __('brain.campaign.ab_test_created', [
                'name' => $abTest->name,
                'id' => $abTest->id,
                'type' => $testTypeLabel,
                'variants' => count($createdVariants),
                'sample' => $samplePct,
                'duration' => $durationHours,
            ]) . "\n  {$variantsList}",
        ];
    }

    protected function executeCheckAbResults(AiActionPlanStep $step, User $user): array
    {
        $abTestId = $step->config['ab_test_id'] ?? null;

        if (!$abTestId) {
            // Try to find the most recent test
            $abTest = AbTest::forUser($user->id)->latest()->first();
        } else {
            $abTest = AbTest::forUser($user->id)->find($abTestId);
        }

        if (!$abTest) {
            return ['status' => 'failed', 'message' => __('brain.campaign.ab_no_tests')];
        }

        $results = $abTest->calculateResults();
        $winner = $abTest->determineWinner();

        $msg = __('brain.campaign.ab_results_header', [
            'name' => $abTest->name,
            'status' => strtoupper($abTest->status),
        ]) . "\n\n";

        foreach ($results as $letter => $data) {
            $isWinner = $winner && $winner->variant_letter === $letter;
            $winnerIcon = $isWinner ? ' 🏆' : '';

            $msg .= "**{$letter}**{$winnerIcon}:\n";
            $msg .= "  📤 " . __('brain.campaign.ab_sent') . ": {$data['sent']}\n";
            $msg .= "  👁️ OR: {$data['open_rate']}%\n";
            $msg .= "  🖱️ CR: {$data['click_rate']}%\n";
            $msg .= "  📊 CTOR: {$data['click_to_open_rate']}%\n\n";
        }

        if ($winner) {
            $msg .= "🏆 " . __('brain.campaign.ab_winner', [
                'letter' => $winner->variant_letter,
                'metric' => $abTest->winning_metric,
            ]);
        } elseif ($abTest->isRunning()) {
            $elapsed = $abTest->test_started_at ? $abTest->test_started_at->diffForHumans(null, true) : '?';
            $msg .= "⏳ " . __('brain.campaign.ab_still_running', ['elapsed' => $elapsed]);
        }

        return ['status' => 'completed', 'message' => $msg];
    }

    protected function executeListAbTests(AiActionPlanStep $step, User $user): array
    {
        $tests = AbTest::forUser($user->id)
            ->with('variants')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($tests->isEmpty()) {
            return ['status' => 'completed', 'message' => __('brain.campaign.ab_no_tests')];
        }

        $msg = __('brain.campaign.ab_list_header', ['count' => $tests->count()]) . "\n\n";

        foreach ($tests as $test) {
            $statusIcon = match ($test->status) {
                AbTest::STATUS_RUNNING => '🟢',
                AbTest::STATUS_COMPLETED => '✅',
                AbTest::STATUS_PAUSED => '⏸️',
                AbTest::STATUS_CANCELLED => '❌',
                default => '📝',
            };

            $variantCount = $test->variants->count();
            $winner = $test->winner_variant_id
                ? $test->variants->firstWhere('id', $test->winner_variant_id)?->variant_letter ?? '-'
                : '-';

            $msg .= "{$statusIcon} **#{$test->id} {$test->name}**\n";
            $msg .= "  🧪 {$test->test_type} | {$variantCount} " . __('brain.campaign.ab_variants_label') . "\n";
            $msg .= "  📊 " . __('brain.campaign.ab_metric') . ": {$test->winning_metric}";

            if ($winner !== '-') {
                $msg .= " | 🏆 {$winner}";
            }

            $msg .= "\n  📅 " . $test->created_at->format('d.m.Y H:i') . "\n\n";
        }

        return ['status' => 'completed', 'message' => $msg];
    }
}
