<?php

namespace App\Services;

use App\Models\CampaignPlan;
use App\Models\CampaignPlanStep;
use App\Models\CampaignBenchmark;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\AutomationRule;
use App\Services\AI\AiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignArchitectService
{
    public function __construct(
        protected AiService $aiService,
        protected TemplateAiService $templateAiService
    ) {}

    /**
     * Gather audience data from selected contact lists
     */
    public function getAudienceData(array $listIds): array
    {
        $lists = ContactList::whereIn('id', $listIds)->get();

        $totalSubscribers = 0;
        $activeSubscribers = 0;
        $inactiveSubscribers = 0;
        $customers = 0;
        $leads = 0;
        $avgOpenRate = 0;
        $avgClickRate = 0;
        $avgUnsubRate = 0;
        $listData = [];

        foreach ($lists as $list) {
            $total = $list->subscribers()->count();
            $active = $list->subscribers()
                ->where('contact_list_subscriber.status', 'active')
                ->count();

            $totalSubscribers += $total;
            $activeSubscribers += $active;
            $inactiveSubscribers += ($total - $active);

            $listData[] = [
                'id' => $list->id,
                'name' => $list->name,
                'type' => $list->type,
                'total' => $total,
                'active' => $active,
            ];
        }

        // Engagement metrics - use defaults since messages don't have direct list relationship
        // In future, this could be calculated from message_recipient stats if available
        $avgOpenRate = 18.0; // Industry average default
        $avgClickRate = 2.0;
        $avgUnsubRate = 0.3;

        return [
            'total_subscribers' => $totalSubscribers,
            'active_subscribers' => $activeSubscribers,
            'inactive_subscribers' => $inactiveSubscribers,
            'engagement' => [
                'open_rate' => round($avgOpenRate, 2),
                'click_rate' => round($avgClickRate, 2),
                'unsubscribe_rate' => round($avgUnsubRate, 2),
            ],
            'lists' => $listData,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate AI-powered campaign strategy
     */
    public function generateStrategy(CampaignPlan $plan): array
    {
        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            throw new \Exception('No AI integration configured. Please configure AI in Settings.');
        }

        // Get benchmark data for this industry/goal
        $benchmark = CampaignBenchmark::getBenchmark(
            $plan->industry ?? 'other',
            $plan->campaign_goal
        );

        $benchmarkInfo = '';
        if ($benchmark) {
            $benchmarkInfo = <<<BENCH
Industry Benchmarks:
- Average Open Rate: {$benchmark->avg_open_rate}%
- Average Click Rate: {$benchmark->avg_click_rate}%
- Average Conversion Rate: {$benchmark->avg_conversion_rate}%
- Recommended Messages: {$benchmark->recommended_messages}
- Recommended Timeline: {$benchmark->recommended_timeline_days} days
BENCH;
        }

        $audienceInfo = '';
        if ($plan->audience_snapshot) {
            $snapshot = $plan->audience_snapshot;
            $audienceInfo = <<<AUD
Audience Data:
- Total Subscribers: {$snapshot['total_subscribers']}
- Active Subscribers: {$snapshot['active_subscribers']}
- Historical Open Rate: {$snapshot['engagement']['open_rate']}%
- Historical Click Rate: {$snapshot['engagement']['click_rate']}%
AUD;
        }

        $businessModels = CampaignPlan::getBusinessModels();
        $campaignGoals = CampaignPlan::getCampaignGoals();
        $languages = CampaignPlan::getLanguages();

        $businessModelLabel = $businessModels[$plan->business_model] ?? $plan->business_model;
        $campaignGoalLabel = $campaignGoals[$plan->campaign_goal] ?? $plan->campaign_goal;
        $targetLanguage = $languages[$plan->campaign_language ?? 'en'] ?? 'English';
        $languageCode = $plan->campaign_language ?? 'en';

        $prompt = <<<PROMPT
You are an expert email/SMS marketing strategist. Create a comprehensive campaign plan based on the following context:

BUSINESS CONTEXT:
- Industry: {$plan->industry}
- Business Model: {$businessModelLabel}
- Campaign Goal: {$campaignGoalLabel}
- Average Order Value: \${$plan->average_order_value}
- Margin: {$plan->margin_percent}%
- Decision Cycle: {$plan->decision_cycle_days} days

{$benchmarkInfo}

{$audienceInfo}

IMPORTANT LANGUAGE REQUIREMENT:
Generate ALL message content (subjects, descriptions, content_hints) in {$targetLanguage} ({$languageCode}).
The summary and channels_strategy fields should also be in {$targetLanguage}.

TASK: Generate a detailed campaign strategy with:
1. Recommended number of messages (email and/or SMS)
2. Message sequence with timing (delay in days)
3. Message types (educational, sales, reminder, social_proof, follow_up, etc.)
4. Subject line suggestions for each message (IN {$targetLanguage})
5. Conditional logic (IF opened/clicked/purchased THEN...)
6. Channel recommendations (email vs SMS for each step)

IMPORTANT: Return your response as a valid JSON object with this exact structure:
{
    "summary": "Brief strategy overview in 2-3 sentences",
    "total_emails": 5,
    "total_sms": 1,
    "timeline_days": 14,
    "channels_strategy": "Primarily email with SMS for urgency and reminders",
    "steps": [
        {
            "order": 1,
            "channel": "email",
            "message_type": "educational",
            "delay_days": 0,
            "delay_hours": 0,
            "subject": "Welcome! Here's what you need to know...",
            "description": "Introduction email that sets expectations and provides immediate value",
            "conditions": null,
            "content_hints": ["Include brand story", "Set expectations", "Provide quick win"]
        },
        {
            "order": 2,
            "channel": "email",
            "message_type": "social_proof",
            "delay_days": 2,
            "delay_hours": 0,
            "subject": "See what others are saying...",
            "description": "Share customer testimonials and success stories",
            "conditions": {"trigger": "opened", "previous_step": 1},
            "content_hints": ["Include customer quotes", "Show results"]
        }
    ],
    "segmentation_suggestions": [
        "Segment by engagement level after message 2",
        "Create VIP segment for high engagers"
    ],
    "optimization_tips": [
        "Test subject lines with emojis",
        "Send time: Tuesday-Thursday morning"
    ]
}

Respond ONLY with valid JSON, no additional text or markdown formatting.
PROMPT;

        $response = $this->aiService->generateContent($prompt, $integration, [
            'max_tokens' => $integration->max_tokens_large ?? 50000,
            'temperature' => 0.7,
        ]);

        // Parse JSON response
        $strategy = $this->extractJsonFromResponse($response);

        if (!$strategy) {
            Log::error('Failed to parse AI strategy response', ['response' => $response]);
            throw new \Exception('Failed to generate strategy. Please try again.');
        }

        // Save strategy to plan
        $plan->update(['strategy' => $strategy]);

        // Create plan steps
        if (!empty($strategy['steps'])) {
            // Clear existing steps
            $plan->steps()->delete();

            foreach ($strategy['steps'] as $stepData) {
                $plan->steps()->create([
                    'order' => $stepData['order'] ?? 0,
                    'channel' => $stepData['channel'] ?? 'email',
                    'message_type' => $stepData['message_type'] ?? 'educational',
                    'subject' => $stepData['subject'] ?? null,
                    'description' => $stepData['description'] ?? null,
                    'delay_days' => $stepData['delay_days'] ?? 0,
                    'delay_hours' => $stepData['delay_hours'] ?? 0,
                    'conditions' => $stepData['conditions'] ?? null,
                    'content_hints' => $stepData['content_hints'] ?? null,
                ]);
            }
        }

        // Calculate and save forecast
        $forecast = $this->calculateForecast($plan);
        $plan->update(['forecast' => $forecast]);

        return $strategy;
    }

    /**
     * Calculate forecast metrics based on plan and benchmarks
     */
    public function calculateForecast(CampaignPlan $plan, array $adjustments = []): array
    {
        $benchmark = CampaignBenchmark::getBenchmark(
            $plan->industry ?? 'other',
            $plan->campaign_goal
        );

        // Get base rates from benchmark or defaults
        $baseOpenRate = $benchmark?->avg_open_rate ?? 18.0;
        $baseClickRate = $benchmark?->avg_click_rate ?? 2.0;
        $baseConversionRate = $benchmark?->avg_conversion_rate ?? 1.0;

        // Apply historical performance modifier if available
        if ($plan->audience_snapshot) {
            $historicalOpen = $plan->audience_snapshot['engagement']['open_rate'] ?? 0;
            if ($historicalOpen > 0) {
                // Blend benchmark with historical (60% historical, 40% benchmark)
                $baseOpenRate = ($historicalOpen * 0.6) + ($baseOpenRate * 0.4);
            }

            $historicalClick = $plan->audience_snapshot['engagement']['click_rate'] ?? 0;
            if ($historicalClick > 0) {
                $baseClickRate = ($historicalClick * 0.6) + ($baseClickRate * 0.4);
            }
        }

        // Apply number of messages modifier (more messages = higher diminishing returns)
        $messageCount = $plan->steps()->count() ?: ($plan->strategy['total_emails'] ?? 5);
        $messageFactor = 1 + (min($messageCount, 10) * 0.05); // Up to 50% boost for sequence

        // Apply adjustments from sliders
        $messageCountAdj = $adjustments['message_count'] ?? 1.0;
        $timelineAdj = $adjustments['timeline'] ?? 1.0;
        $audienceSizeAdj = $adjustments['audience_size'] ?? 1.0;

        // Calculate final rates
        $predictedOpenRate = min(round($baseOpenRate * $messageFactor * $timelineAdj, 2), 80);
        $predictedClickRate = min(round($baseClickRate * $messageFactor * $messageCountAdj, 2), 30);
        $predictedConversionRate = min(round($baseConversionRate * $messageFactor * $messageCountAdj, 2), 15);

        // Calculate revenue projection
        $audienceSize = $plan->audience_snapshot['active_subscribers'] ?? 1000;
        $audienceSize = (int) ($audienceSize * $audienceSizeAdj);

        $aov = $plan->average_order_value ?? 100;
        $margin = $plan->margin_percent ?? 30;

        $expectedConversions = (int) ($audienceSize * ($predictedConversionRate / 100));
        $projectedRevenue = round($expectedConversions * $aov, 2);
        $projectedProfit = round($projectedRevenue * ($margin / 100), 2);

        // Estimate campaign cost (simplified)
        $emailCount = $plan->strategy['total_emails'] ?? 5;
        $smsCount = $plan->strategy['total_sms'] ?? 0;
        $estimatedCost = ($emailCount * $audienceSize * 0.001) + ($smsCount * $audienceSize * 0.02);

        $roi = ($estimatedCost > 0 && $projectedProfit > 0)
            ? round((($projectedProfit - $estimatedCost) / $estimatedCost) * 100, 0)
            : 0;

        $forecast = [
            'open_rate' => $predictedOpenRate,
            'click_rate' => $predictedClickRate,
            'conversion_rate' => $predictedConversionRate,
            'audience_size' => $audienceSize,
            'expected_conversions' => $expectedConversions,
            'projected_revenue' => $projectedRevenue,
            'projected_profit' => $projectedProfit,
            'estimated_cost' => round($estimatedCost, 2),
            'roi' => $roi,
            'calculated_at' => now()->toISOString(),
        ];

        return $forecast;
    }

    /**
     * Export plan to NetSendo campaigns/automations
     */
    public function exportToCampaigns(CampaignPlan $plan, string $exportMode = 'draft'): array
    {
        $createdItems = [
            'emails' => [],
            'sms' => [],
            'automations' => [],
        ];

        $steps = $plan->steps()->orderBy('order')->get();

        if ($steps->isEmpty()) {
            throw new \Exception('No campaign steps to export.');
        }

        $selectedLists = $plan->selected_lists ?? [];
        $primaryListId = $selectedLists[0] ?? null;

        if (!$primaryListId) {
            throw new \Exception('No contact list selected for export.');
        }

        DB::beginTransaction();
        try {
            $previousMessageId = null;

            foreach ($steps as $step) {
                $channel = $step->channel ?? 'email';

                // Create message (email or SMS) - always as draft
                $message = Message::create([
                    'user_id' => $plan->user_id,
                    'campaign_plan_id' => $plan->id,
                    'channel' => $channel,
                    'subject' => $step->subject ?? ($plan->name . ' - ' . ($channel === 'sms' ? 'SMS' : 'Email') . ' ' . $step->order),
                    'content' => '<p>' . ($step->description ?? 'Content to be created') . '</p>',
                    'status' => 'draft', // Always create as draft
                    'type' => 'broadcast',
                ]);

                // Attach the primary contact list
                $message->contactLists()->attach($primaryListId);

                $itemData = [
                    'id' => $message->id,
                    'subject' => $message->subject,
                    'step_order' => $step->order,
                    'channel' => $channel,
                ];

                if ($channel === 'sms') {
                    $createdItems['sms'][] = $itemData;
                } else {
                    $createdItems['emails'][] = $itemData;
                }

                // Note: Automation rules with conditional logic can be created manually
                // The existing AutomationRule schema differs from what we need here
                // Future enhancement: integrate with automation builder

                $previousMessageId = $message->id;
            }

            // Update plan status
            $plan->update([
                'status' => 'exported',
                'exported_at' => now(),
                'exported_items' => $createdItems,
            ]);

            DB::commit();

            return $createdItems;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Campaign export failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract JSON from AI response
     */
    protected function extractJsonFromResponse(string $response): ?array
    {
        // Remove markdown code blocks if present
        $clean = preg_replace('/```(?:json)?\\s*/i', '', $response);
        $clean = preg_replace('/```\\s*/i', '', $clean);
        $clean = trim($clean);

        // Try direct parse
        $json = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        // Try to find JSON object in response
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
        }

        return null;
    }
}
