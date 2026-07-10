<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\Funnel;
use App\Models\Message;
use App\Models\User;
use App\Services\Funnels\FunnelService;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 3 — codified revenue playbooks.
 *
 * A playbook is a proven money-making funnel shape (welcome→offer,
 * cart abandonment, win-back, post-purchase upsell). Brain instantiates it:
 * generates the e-mail content with the LLM (brand voice from the knowledge
 * base, tone from strategy settings), creates the messages, builds the
 * funnel steps, and leaves the funnel in DRAFT — activation is a send-tier
 * action gated by the funnel agent.
 */
class PlaybookService
{
    /**
     * Playbook catalog. Sequences are linear: start → items… → end.
     * Item types: email (LLM-generated), delay, goal.
     */
    public const PLAYBOOKS = [
        'welcome' => [
            'name' => 'Welcome → first offer',
            'trigger_type' => Funnel::TRIGGER_LIST_SIGNUP,
            'goal_focus' => 'first purchase',
            'sequence' => [
                ['type' => 'email', 'key' => 'email_1', 'brief' => 'Warm welcome for a new subscriber. Thank them for joining, set expectations for what they will receive, deliver immediate value (a tip, resource or insight related to the topic). No hard selling.'],
                ['type' => 'delay', 'value' => 2, 'unit' => 'days'],
                ['type' => 'email', 'key' => 'email_2', 'brief' => 'Build trust: share the story/mission behind the brand and the single most useful piece of content or advice for this audience. Soft mention of the product/offer as a solution.'],
                ['type' => 'delay', 'value' => 2, 'unit' => 'days'],
                ['type' => 'email', 'key' => 'email_3', 'brief' => 'First offer: present the core product/offer with clear benefits, social proof and a strong call-to-action. Optionally a welcome discount or bonus for new subscribers.'],
                ['type' => 'goal', 'goal_type' => 'purchase', 'name' => 'First purchase'],
            ],
        ],
        'cart_abandon' => [
            'name' => 'Cart abandonment recovery',
            'trigger_type' => Funnel::TRIGGER_TAG_ADDED,
            'default_tag' => 'cart_abandoned',
            'goal_focus' => 'recovered order',
            'sequence' => [
                ['type' => 'delay', 'value' => 1, 'unit' => 'hours'],
                ['type' => 'email', 'key' => 'email_1', 'brief' => 'Gentle cart reminder: the customer left items in their cart. Helpful tone ("something went wrong?"), restate what they were buying and its main benefit, single clear link back to the cart. No discount yet.'],
                ['type' => 'delay', 'value' => 1, 'unit' => 'days'],
                ['type' => 'email', 'key' => 'email_2', 'brief' => 'Overcome objections: address the most common reasons for hesitation (price, trust, timing), add social proof or guarantee, and optionally a small time-limited incentive to complete the order now.'],
                ['type' => 'goal', 'goal_type' => 'purchase', 'name' => 'Recovered cart'],
            ],
        ],
        'winback' => [
            'name' => 'Win-back inactive subscribers',
            'trigger_type' => Funnel::TRIGGER_TAG_ADDED,
            'default_tag' => 'inactive',
            'goal_focus' => 'reactivation',
            'sequence' => [
                ['type' => 'email', 'key' => 'email_1', 'brief' => 'Re-engagement: "we miss you" tone, remind them of the value they signed up for, show what is new since they last engaged. One clear call-to-action to come back.'],
                ['type' => 'delay', 'value' => 3, 'unit' => 'days'],
                ['type' => 'email', 'key' => 'email_2', 'brief' => 'Win-back offer: an exclusive comeback incentive (discount, bonus content or free resource). Make clear this is a special offer for returning subscribers. Mention that they can unsubscribe if no longer interested — honest sunset framing.'],
                ['type' => 'goal', 'goal_type' => 'purchase', 'name' => 'Win-back purchase'],
            ],
        ],
        'post_purchase' => [
            'name' => 'Post-purchase upsell',
            'trigger_type' => Funnel::TRIGGER_TAG_ADDED,
            'default_tag' => 'customer',
            'goal_focus' => 'repeat purchase',
            'sequence' => [
                ['type' => 'delay', 'value' => 3, 'unit' => 'days'],
                ['type' => 'email', 'key' => 'email_1', 'brief' => 'Post-purchase thank you: confirm they made a great choice, give a tip to get the most out of what they bought, invite questions/replies. Builds satisfaction before any upsell.'],
                ['type' => 'delay', 'value' => 4, 'unit' => 'days'],
                ['type' => 'email', 'key' => 'email_2', 'brief' => 'Cross-sell/upsell: recommend the most complementary product/offer to what they already bought, framed as the natural next step. Clear benefits and call-to-action.'],
                ['type' => 'goal', 'goal_type' => 'purchase', 'name' => 'Repeat purchase'],
            ],
        ],
    ];

    public function __construct(
        protected FunnelService $funnelService,
        protected KnowledgeBaseService $knowledgeBase,
    ) {}

    /**
     * Catalog for prompts/UI.
     */
    public static function catalog(): array
    {
        return collect(self::PLAYBOOKS)->map(fn ($def, $key) => [
            'key' => $key,
            'name' => $def['name'],
            'trigger_type' => $def['trigger_type'],
            'default_tag' => $def['default_tag'] ?? null,
            'emails' => collect($def['sequence'])->where('type', 'email')->count(),
        ])->values()->all();
    }

    /**
     * Instantiate a playbook: generate content, create messages, build the
     * funnel. Returns the DRAFT funnel + validation errors (empty = ready
     * to activate).
     *
     * @param array $options list_id (required for list_signup), tag, topic, name
     * @return array{funnel: ?Funnel, message_ids: int[], errors: string[]}
     */
    public function instantiate(User $user, string $playbookKey, array $options = []): array
    {
        $def = self::PLAYBOOKS[$playbookKey] ?? null;
        if (!$def) {
            return ['funnel' => null, 'message_ids' => [], 'errors' => ["Unknown playbook: {$playbookKey}"]];
        }

        // Trigger configuration
        $triggerType = $def['trigger_type'];
        $funnelData = [
            'user_id' => $user->id,
            'name' => $options['name'] ?? $def['name'],
            'trigger_type' => $triggerType,
        ];

        if ($triggerType === Funnel::TRIGGER_LIST_SIGNUP) {
            if (empty($options['list_id'])) {
                return ['funnel' => null, 'message_ids' => [], 'errors' => ['list_id is required for this playbook']];
            }
            $funnelData['trigger_list_id'] = (int) $options['list_id'];
        } elseif ($triggerType === Funnel::TRIGGER_TAG_ADDED) {
            $funnelData['trigger_tag'] = $options['tag'] ?? $def['default_tag'];
        }

        // 1. Generate e-mail content for every email item
        $messageIds = [];
        $emailMessages = [];
        foreach ($def['sequence'] as $item) {
            if ($item['type'] !== 'email') {
                continue;
            }

            $message = $this->generateEmailMessage($user, $def, $item, $options);
            if (!$message) {
                return ['funnel' => null, 'message_ids' => $messageIds, 'errors' => ["Content generation failed for {$item['key']}"]];
            }
            $emailMessages[$item['key']] = $message;
            $messageIds[] = $message->id;
        }

        // 2. Create the funnel (draft, with auto start step)
        $funnel = $this->funnelService->create($funnelData);
        $startStep = $funnel->getStartStep();

        // 3. Build linear nodes/edges: start → sequence… → end
        $nodes = [[
            'id' => (string) $startStep->id,
            'type' => 'start',
            'position' => ['x' => 100, 'y' => 100],
            'data' => ['name' => 'Start'],
        ]];
        $edges = [];
        $prevId = (string) $startStep->id;
        $y = 100;

        foreach ($def['sequence'] as $i => $item) {
            $nodeId = "playbook_node_{$i}";
            $y += 140;

            $data = ['name' => ucfirst($item['type']) . ' ' . ($i + 1)];
            if ($item['type'] === 'email') {
                $data['name'] = $emailMessages[$item['key']]->subject;
                $data['message_id'] = $emailMessages[$item['key']]->id;
            } elseif ($item['type'] === 'delay') {
                $data['name'] = "Wait {$item['value']} {$item['unit']}";
                $data['delay_value'] = $item['value'];
                $data['delay_unit'] = $item['unit'];
            } elseif ($item['type'] === 'goal') {
                $data['name'] = $item['name'];
                $data['goal_name'] = $item['name'];
                $data['goal_type'] = $item['goal_type'];
                $data['goal_config'] = [];
            }

            $nodes[] = [
                'id' => $nodeId,
                'type' => $item['type'],
                'position' => ['x' => 100, 'y' => $y],
                'data' => $data,
            ];
            $edges[] = ['source' => $prevId, 'target' => $nodeId, 'sourceHandle' => 'default'];
            $prevId = $nodeId;
        }

        $nodes[] = [
            'id' => 'playbook_end',
            'type' => 'end',
            'position' => ['x' => 100, 'y' => $y + 140],
            'data' => ['name' => 'End'],
        ];
        $edges[] = ['source' => $prevId, 'target' => 'playbook_end', 'sourceHandle' => 'default'];

        $this->funnelService->updateSteps($funnel, $nodes, $edges);

        $errors = $this->funnelService->validate($funnel->fresh('steps'));

        Log::info('Playbook instantiated', [
            'user_id' => $user->id,
            'playbook' => $playbookKey,
            'funnel_id' => $funnel->id,
            'messages' => count($messageIds),
            'validation_errors' => $errors,
        ]);

        return ['funnel' => $funnel->fresh('steps'), 'message_ids' => $messageIds, 'errors' => $errors];
    }

    /**
     * Generate one playbook e-mail with the LLM and persist it as a
     * funnel-ready Message (status 'ready' — invisible to the broadcast cron).
     */
    protected function generateEmailMessage(User $user, array $def, array $item, array $options): ?Message
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $strategy = $settings->getStrategyForAgent('campaign');
        $tone = $strategy['tone'] ?? 'professional';
        $langName = AiBrainSettings::getLanguageName($settings->resolveLanguage($user));
        $knowledgeContext = $this->knowledgeBase->getContext($user, 'message');
        $topic = $options['topic'] ?? '';

        $prompt = <<<PROMPT
You are an expert email marketing copywriter. Write ONE email for an automated "{$def['name']}" funnel.

EMAIL ROLE: {$item['brief']}
FUNNEL GOAL: {$def['goal_focus']}
TONE: {$tone}
TOPIC/PRODUCT CONTEXT: {$topic}

{$knowledgeContext}

Personalization: you may use [[fname]] for the first name and {{male_form|female_form}} for gender-dependent words. Include an unsubscribe hint is NOT needed (added automatically).

IMPORTANT: Write the email in {$langName}.

Respond in JSON only:
{"subject": "...", "preheader": "...", "content": "<p>HTML content...</p>"}
PROMPT;

        try {
            $integration = app(\App\Services\AI\AiService::class)->getDefaultIntegration();
            if (!$integration) {
                return null;
            }

            $response = BrainAi::generate($user, 'playbook_content', $prompt, $integration, [
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ]);

            $data = $this->parseJson($response);
            if (!$data || empty($data['subject']) || empty($data['content'])) {
                return null;
            }

            return Message::create([
                'user_id' => $user->id,
                'subject' => $data['subject'],
                'preheader' => $data['preheader'] ?? '',
                'content' => $data['content'],
                'channel' => 'email',
                'type' => 'broadcast',
                'status' => 'ready', // funnel pool — never picked up by the broadcast cron
            ]);
        } catch (\Exception $e) {
            Log::error('Playbook email generation failed', [
                'user_id' => $user->id,
                'key' => $item['key'] ?? '',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function parseJson(string $response): ?array
    {
        $response = trim($response);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            $response = $matches[1];
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
}
