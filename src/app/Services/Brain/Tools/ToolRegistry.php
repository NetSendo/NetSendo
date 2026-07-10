<?php

namespace App\Services\Brain\Tools;

/**
 * Brain 2.0 (Phase 1) — central registry of agent step actions.
 *
 * Single source of truth for every executable action: which agent owns it,
 * its permission tier, its human/LLM-facing description and config shape.
 *
 * Consumed by:
 * - agent planner prompts (promptSection) — no more prompt/executor drift
 * - BaseAgent::createPlan — step validation at plan-creation time
 * - ModeController::getActionTier — permission tiers
 * - the Approval Center UI — per-step tier badges
 */
class ToolRegistry
{
    public const TIER_READ = 'read';
    public const TIER_WRITE = 'write';
    public const TIER_SEND = 'send';
    public const TIER_DESTRUCTIVE = 'destructive';

    /**
     * agent => [action_type => [tier, description, config]]
     * `config` is the LLM-facing config shape shown in planner prompts.
     */
    public const TOOLS = [
        'campaign' => [
            'select_audience' => [
                'tier' => self::TIER_READ,
                'description' => 'select target audience',
                'config' => '{list_ids: [N, ...], crm_contact_ids: [N, ...], crm_segment: "all"|"hot_leads"|"warm"|"cold"}',
            ],
            'generate_content' => [
                'tier' => self::TIER_READ,
                'description' => 'generate message content',
                'config' => '{type: "email"|"sms", tone: "", topic: ""}',
            ],
            'create_message' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create message in the system as draft',
                'config' => '{subject: "", content_ref: "step_N"}',
            ],
            'schedule_send' => [
                'tier' => self::TIER_SEND,
                'description' => 'REALLY send or schedule the campaign — dispatches actual emails through the send pipeline after safety checks',
                'config' => '{send_at: "YYYY-MM-DD HH:MM"|"immediate", list_ids: [N], message_id: N}',
            ],
            'create_ab_test' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create A/B test for a message',
                'config' => '{message_id: N, test_type: "subject"|"content"|"sender"|"send_time"|"full", winning_metric: "open_rate"|"click_rate", sample_percentage: 20, test_duration_hours: 24, auto_select_winner: true, variants: [{subject: "", preheader: ""}, {subject: "", preheader: ""}]}',
            ],
            'start_ab_test' => [
                'tier' => self::TIER_SEND,
                'description' => 'start a created A/B test so variants get sampled during sending — use after create_ab_test and before/with schedule_send',
                'config' => '{ab_test_id: N}',
            ],
            'apply_ab_winner' => [
                'tier' => self::TIER_SEND,
                'description' => 'complete a running A/B test, pick the winner and send it to the remaining audience',
                'config' => '{ab_test_id: N, force: false}',
            ],
            'check_ab_results' => [
                'tier' => self::TIER_READ,
                'description' => 'check results of an A/B test',
                'config' => '{ab_test_id: N}',
            ],
            'list_ab_tests' => [
                'tier' => self::TIER_READ,
                'description' => 'list all A/B tests with status',
                'config' => '{}',
            ],
        ],

        'list' => [
            'create_list' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create a new list',
                'config' => '{name: "", description: ""}',
            ],
            'tag_subscribers' => [
                'tier' => self::TIER_WRITE,
                'description' => 'tag subscribers',
                'config' => '{tag_name: "", list_id: N, criteria: {}}',
            ],
            'clean_bounced' => [
                'tier' => self::TIER_DESTRUCTIVE,
                'description' => 'clean bounced/complained subscribers (mass unsubscribe — requires approval)',
                'config' => '{list_id: N}',
            ],
            'show_stats' => [
                'tier' => self::TIER_READ,
                'description' => 'show statistics',
                'config' => '{list_id: N}',
            ],
        ],

        'message' => [
            'generate_subject' => [
                'tier' => self::TIER_READ,
                'description' => 'generate subject line variants',
                'config' => '{topic: "", count: 5, tone: ""}',
            ],
            'generate_body' => [
                'tier' => self::TIER_READ,
                'description' => 'generate content',
                'config' => '{type: "email"|"sms", topic: "", tone: "", length: ""}',
            ],
            'create_message' => [
                'tier' => self::TIER_WRITE,
                'description' => 'save message as draft',
                'config' => '{subject: "", type: "email"|"sms"}',
            ],
            'generate_ab_variants' => [
                'tier' => self::TIER_READ,
                'description' => 'generate A/B variants',
                'config' => '{original_subject: "", count: 3}',
            ],
            'improve_content' => [
                'tier' => self::TIER_WRITE,
                'description' => 'improve existing content',
                'config' => '{message_id: N, improvements: []}',
            ],
        ],

        'crm' => [
            'search_contacts' => [
                'tier' => self::TIER_READ,
                'description' => 'search contacts',
                'config' => '{query: "", status: "lead|prospect|client", min_score: N}',
            ],
            'create_contact' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create CRM contact from subscriber',
                'config' => '{email: "", source: "", status: "lead"}',
            ],
            'update_contact_status' => [
                'tier' => self::TIER_WRITE,
                'description' => 'change contact status',
                'config' => '{contact_id: N, new_status: "prospect|client"}',
            ],
            'create_deal' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create deal in pipeline',
                'config' => '{name: "", value: N, contact_id: N, pipeline_id: N}',
            ],
            'move_deal_stage' => [
                'tier' => self::TIER_WRITE,
                'description' => 'move deal to stage',
                'config' => '{deal_id: N, stage_name: ""}',
            ],
            'create_task' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create CRM task',
                'config' => '{title: "", type: "call|email|meeting|follow_up", priority: "low|medium|high", contact_id: N, due_days: N}',
            ],
            'score_analysis' => [
                'tier' => self::TIER_READ,
                'description' => 'analyze lead scoring',
                'config' => '{min_score: N, status: ""}',
            ],
            'pipeline_summary' => [
                'tier' => self::TIER_READ,
                'description' => 'show pipeline summary',
                'config' => '{pipeline_id: N|null}',
            ],
            'create_company' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create company',
                'config' => '{name: "", website: "", industry: ""}',
            ],
        ],

        'analytics' => [
            'fetch_campaign_stats' => [
                'tier' => self::TIER_READ,
                'description' => 'campaign statistics',
                'config' => '{days: 30}',
            ],
            'fetch_subscriber_stats' => [
                'tier' => self::TIER_READ,
                'description' => 'subscriber statistics',
                'config' => '{days: 30}',
            ],
            'generate_report' => [
                'tier' => self::TIER_READ,
                'description' => 'AI report',
                'config' => '{type: "monthly|weekly|custom"}',
            ],
            'compare_performance' => [
                'tier' => self::TIER_READ,
                'description' => 'compare campaigns',
                'config' => '{}',
            ],
            'analyze_trends' => [
                'tier' => self::TIER_READ,
                'description' => 'trend analysis',
                'config' => '{days: 30}',
            ],
            'ai_usage_report' => [
                'tier' => self::TIER_READ,
                'description' => 'AI Brain usage',
                'config' => '{days: 30}',
            ],
        ],

        'segmentation' => [
            'analyze_tag_distribution' => [
                'tier' => self::TIER_READ,
                'description' => 'show tag distribution',
                'config' => '{limit: 15}',
            ],
            'analyze_score_distribution' => [
                'tier' => self::TIER_READ,
                'description' => 'show scoring segments',
                'config' => '{}',
            ],
            'create_tag' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create tag',
                'config' => '{name: "", color: "#hex"}',
            ],
            'apply_tag' => [
                'tier' => self::TIER_WRITE,
                'description' => 'apply tag to subscribers',
                'config' => '{tag_name: "", criteria: {status: "", min_score: N}}',
            ],
            'suggest_segments' => [
                'tier' => self::TIER_READ,
                'description' => 'AI segmentation recommendations',
                'config' => '{}',
            ],
            'automation_stats' => [
                'tier' => self::TIER_READ,
                'description' => 'automation statistics',
                'config' => '{days: 7}',
            ],
            'create_automation' => [
                'tier' => self::TIER_WRITE,
                'description' => 'create automation rule (created inactive by default)',
                'config' => '{name: "", trigger_event: "", trigger_config: {}, conditions: [{type: "", config: {}}], condition_logic: "and"|"or", actions: [{type: "", config: {}}], is_active: true|false, limit_per_subscriber: true|false, limit_count: N, limit_period: "hour"|"day"|"week"|"month"|"ever"}',
            ],
            'update_automation' => [
                'tier' => self::TIER_DESTRUCTIVE,
                'description' => 'update existing automation (changes live behaviour — requires approval)',
                'config' => '{automation_id: N, name: "", is_active: true|false, trigger_event: "", actions: [...]}',
            ],
            'toggle_automation' => [
                'tier' => self::TIER_DESTRUCTIVE,
                'description' => 'enable/disable automation (requires approval)',
                'config' => '{automation_id: N}',
            ],
            'delete_automation' => [
                'tier' => self::TIER_DESTRUCTIVE,
                'description' => 'delete automation (requires approval)',
                'config' => '{automation_id: N}',
            ],
            'list_automations' => [
                'tier' => self::TIER_READ,
                'description' => 'list all automations with stats',
                'config' => '{}',
            ],
        ],

        'funnel' => [
            'list_playbooks' => [
                'tier' => self::TIER_READ,
                'description' => 'list available revenue playbooks (proven funnel shapes)',
                'config' => '{}',
            ],
            'create_funnel_from_playbook' => [
                'tier' => self::TIER_WRITE,
                'description' => 'instantiate a revenue playbook: AI-generated emails + funnel steps, created as DRAFT (welcome requires list_id; cart_abandon/winback/post_purchase use a trigger tag)',
                'config' => '{playbook: "welcome"|"cart_abandon"|"winback"|"post_purchase", list_id: N, tag: "", topic: "", name: ""}',
            ],
            'list_funnels' => [
                'tier' => self::TIER_READ,
                'description' => 'list existing funnels with status and enrollment',
                'config' => '{}',
            ],
            'funnel_stats' => [
                'tier' => self::TIER_READ,
                'description' => 'funnel performance: enrollments, completions, goal revenue',
                'config' => '{funnel_id: N}',
            ],
            'activate_funnel' => [
                'tier' => self::TIER_SEND,
                'description' => 'activate a draft/paused funnel — enrolled subscribers will start receiving its emails',
                'config' => '{funnel_id: N}',
            ],
            'pause_funnel' => [
                'tier' => self::TIER_WRITE,
                'description' => 'pause an active funnel (stops further sends)',
                'config' => '{funnel_id: N}',
            ],
        ],

        'deliverability' => [
            'deliverability_status' => [
                'tier' => self::TIER_READ,
                'description' => 'domain auth (SPF/DKIM/DMARC) and mailbox blacklist status',
                'config' => '{}',
            ],
            'bounce_health' => [
                'tier' => self::TIER_READ,
                'description' => 'recent send health: bounce/complaint rates and circuit-breaker state',
                'config' => '{}',
            ],
            'spam_check_message' => [
                'tier' => self::TIER_READ,
                'description' => 'spam-trigger analysis + inbox placement estimate for a draft message',
                'config' => '{message_id: N}',
            ],
        ],

        'revenue' => [
            'revenue_summary' => [
                'tier' => self::TIER_READ,
                'description' => 'total revenue summary: by currency, by source, attributed share',
                'config' => '{days: 30}',
            ],
            'campaign_revenue' => [
                'tier' => self::TIER_READ,
                'description' => 'revenue attributed to campaigns with RPM (revenue per 1000 delivered)',
                'config' => '{days: 30, limit: 10}',
            ],
            'subscriber_ltv' => [
                'tier' => self::TIER_READ,
                'description' => 'top subscribers by lifetime revenue',
                'config' => '{limit: 10}',
            ],
        ],

        'research' => [
            'web_search' => [
                'tier' => self::TIER_READ,
                'description' => 'search the web for current information',
                'config' => '{query: "", type: "general|news"}',
            ],
            'deep_research' => [
                'tier' => self::TIER_READ,
                'description' => 'in-depth AI research with cited sources',
                'config' => '{query: ""}',
            ],
            'company_research' => [
                'tier' => self::TIER_READ,
                'description' => 'research a specific company',
                'config' => '{company: "", website: ""}',
            ],
            'trend_analysis' => [
                'tier' => self::TIER_READ,
                'description' => 'analyze market/industry trends',
                'config' => '{topic: ""}',
            ],
            'content_research' => [
                'tier' => self::TIER_READ,
                'description' => 'research content ideas',
                'config' => '{topic: "", type: "email|sms"}',
            ],
            'save_to_knowledge' => [
                'tier' => self::TIER_WRITE,
                'description' => 'save findings to knowledge base',
                'config' => '{category: "", title: ""}',
            ],
        ],
    ];

    /**
     * All actions for an agent: [action_type => definition].
     */
    public static function actionsForAgent(string $agent): array
    {
        return self::TOOLS[$agent] ?? [];
    }

    /**
     * Permission tier for an action type (searched across all agents).
     * Unknown actions default to TIER_WRITE.
     */
    public static function tierOf(string $actionType): string
    {
        foreach (self::TOOLS as $actions) {
            if (isset($actions[$actionType])) {
                return $actions[$actionType]['tier'];
            }
        }

        return self::TIER_WRITE;
    }

    /**
     * Whether an agent owns the given action type.
     */
    public static function isValidAction(string $agent, string $actionType): bool
    {
        return isset(self::TOOLS[$agent][$actionType]);
    }

    /**
     * Generate the "Available action_types" block for a planner prompt.
     *
     * @param string[]|null $only Restrict to these action names (e.g. research
     *                            tools gated by the user's configured API keys)
     */
    public static function promptSection(string $agent, ?array $only = null): string
    {
        $actions = self::actionsForAgent($agent);

        if ($only !== null) {
            $actions = array_intersect_key($actions, array_flip($only));
        }

        $lines = ["Available action_types (use ONLY these — no other actions exist):"];
        foreach ($actions as $name => $def) {
            $lines[] = "- {$name}: {$def['description']} (config: {$def['config']})";
        }

        return implode("\n", $lines);
    }
}
