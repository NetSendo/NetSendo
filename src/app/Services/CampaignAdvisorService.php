<?php

namespace App\Services;

use App\Models\CampaignAudit;
use App\Models\CampaignAuditIssue;
use App\Models\CampaignRecommendation;
use App\Models\User;
use App\Models\Message;
use App\Models\EmailOpen;
use App\Models\EmailClick;
use App\Services\AI\AiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CampaignAdvisorService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Generate recommendations for an audit based on user's target improvement
     */
    public function generateRecommendations(CampaignAudit $audit, float $targetImprovementPercent = 2.0): Collection
    {
        $user = $audit->user;

        // Get user's advisor settings
        $settings = $user->settings['campaign_advisor'] ?? [];
        $maxRecommendations = $settings['recommendation_count'] ?? 5;
        $focusAreas = $settings['focus_areas'] ?? [];

        $recommendations = collect();

        try {
            // 1. Generate Quick Wins from current issues
            $quickWins = $this->generateQuickWins($audit, $focusAreas);
            $recommendations = $recommendations->merge($quickWins);

            // 2. Generate Strategic recommendations from historical data
            $strategic = $this->generateStrategicRecommendations($user, $audit, $focusAreas);
            $recommendations = $recommendations->merge($strategic);

            // 3. Generate Growth recommendations using AI
            $growth = $this->generateGrowthRecommendations($user, $audit);
            $recommendations = $recommendations->merge($growth);

            // Prioritize and limit recommendations
            $recommendations = $this->prioritizeRecommendations($recommendations, $targetImprovementPercent);
            $recommendations = $recommendations->take($maxRecommendations);

            // Save recommendations to database
            foreach ($recommendations as $recData) {
                $audit->recommendations()->create($recData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate recommendations', [
                'audit_id' => $audit->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $audit->recommendations()->get();
    }

    /**
     * Generate Quick Win recommendations from current audit issues
     */
    protected function generateQuickWins(CampaignAudit $audit, array $focusAreas = []): Collection
    {
        $quickWins = collect();
        $issues = $audit->issues()->orderBy('impact_score', 'desc')->get();

        // Map issues to quick win recommendations
        $issueRecommendations = [
            CampaignAuditIssue::ISSUE_MISSING_PREHEADER => [
                'title' => 'Add preheaders to your emails',
                'description' => 'Emails without preheaders miss valuable inbox preview space. Adding compelling preheaders can increase open rates by 5-10%.',
                'expected_impact' => 3.0,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
                'action_steps' => [
                    'Open the email editor for each draft/scheduled email',
                    'Add a preheader that complements your subject line',
                    'Keep it under 100 characters for best display',
                    'Use personalization tokens for higher engagement',
                ],
            ],
            CampaignAuditIssue::ISSUE_LONG_SUBJECT => [
                'title' => 'Shorten your subject lines',
                'description' => 'Long subject lines get truncated on mobile devices. Keeping them under 50 characters ensures your message is fully visible.',
                'expected_impact' => 2.0,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
                'action_steps' => [
                    'Review subject lines over 50 characters',
                    'Focus on the most compelling part of your message',
                    'Use power words that trigger emotion',
                    'Test with emoji (sparingly) for visual appeal',
                ],
            ],
            CampaignAuditIssue::ISSUE_NO_PERSONALIZATION => [
                'title' => 'Personalize your email content',
                'description' => 'Personalized emails achieve 26% higher open rates. Using subscriber names and relevant data creates stronger connections.',
                'expected_impact' => 4.0,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
                'action_steps' => [
                    'Add [[first_name]] to subject lines and greetings',
                    'Use [[company]] or [[city]] for B2B communications',
                    'Create dynamic content blocks based on subscriber tags',
                    'Set up fallback values for missing data',
                ],
            ],
            CampaignAuditIssue::ISSUE_SPAM_CONTENT => [
                'title' => 'Reduce spam trigger words',
                'description' => 'Your content contains words that may trigger spam filters. Cleaning up the language improves deliverability.',
                'expected_impact' => 5.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'action_steps' => [
                    'Avoid ALL CAPS and excessive exclamation marks',
                    'Replace words like "FREE", "URGENT", "ACT NOW" with softer alternatives',
                    'Balance promotional and value-driven content',
                    'Use HTML email checkers before sending',
                ],
            ],
            CampaignAuditIssue::ISSUE_STALE_LIST => [
                'title' => 'Clean your subscriber lists',
                'description' => 'Lists with inactive subscribers hurt deliverability. Regular cleaning improves open rates and sender reputation.',
                'expected_impact' => 6.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'action_steps' => [
                    'Identify subscribers with no opens in 90 days',
                    'Run a re-engagement campaign before removing',
                    'Remove hard bounces immediately',
                    'Consider a sunset policy for long-inactive users',
                ],
            ],
            CampaignAuditIssue::ISSUE_POOR_TIMING => [
                'title' => 'Optimize your send times',
                'description' => 'Sending at optimal times significantly impacts open rates. Your best window is typically 9-11 AM or 2-4 PM local time.',
                'expected_impact' => 3.5,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
                'action_steps' => [
                    'Schedule emails between 9-11 AM for business audiences',
                    'Try 2-4 PM for consumer audiences',
                    'Tuesday through Thursday typically perform best',
                    'Avoid weekends unless you have data showing otherwise',
                ],
            ],
            CampaignAuditIssue::ISSUE_OVER_MAILING => [
                'title' => 'Reduce sending frequency',
                'description' => 'You are sending too frequently to some lists. This increases unsubscribes and spam complaints.',
                'expected_impact' => 4.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'action_steps' => [
                    'Limit to 2-3 emails per week per list',
                    'Create a preference center for frequency options',
                    'Segment high-engagement users for more content',
                    'Use automations instead of manual broadcasts where possible',
                ],
            ],
            CampaignAuditIssue::ISSUE_NO_AUTOMATION => [
                'title' => 'Set up welcome automations',
                'description' => 'Automated emails generate 320% more revenue than non-automated. Start with a welcome sequence.',
                'expected_impact' => 8.0,
                'effort_level' => CampaignRecommendation::EFFORT_HIGH,
                'action_steps' => [
                    'Create a 3-5 email welcome sequence',
                    'Set up automation triggered on new subscriber',
                    'Include value content before promotional offers',
                    'Track engagement to identify hot leads',
                ],
            ],
            CampaignAuditIssue::ISSUE_SMS_MISSING => [
                'title' => 'Launch SMS campaigns',
                'description' => 'You have phone numbers but are not using SMS. Multi-channel campaigns improve conversion by 12-15%.',
                'expected_impact' => 7.0,
                'effort_level' => CampaignRecommendation::EFFORT_HIGH,
                'action_steps' => [
                    'Create an SMS follow-up for key email campaigns',
                    'Use SMS for time-sensitive offers',
                    'Keep messages under 160 characters',
                    'Include a clear call-to-action with link',
                ],
            ],
        ];

        foreach ($issues as $issue) {
            if (isset($issueRecommendations[$issue->issue_key])) {
                $recData = $issueRecommendations[$issue->issue_key];

                // Skip if focus areas set and this category isn't included
                if (!empty($focusAreas) && !in_array($issue->category, $focusAreas)) {
                    continue;
                }

                $quickWins->push([
                    'type' => CampaignRecommendation::TYPE_QUICK_WIN,
                    'priority' => $this->calculatePriorityFromImpactEffort(
                        $recData['expected_impact'],
                        $recData['effort_level']
                    ),
                    'title' => $recData['title'],
                    'description' => $recData['description'],
                    'expected_impact' => $recData['expected_impact'],
                    'effort_level' => $recData['effort_level'],
                    'category' => $issue->category,
                    'action_steps' => $recData['action_steps'],
                    'context' => [
                        'source_issue_id' => $issue->id,
                        'source_issue_key' => $issue->issue_key,
                    ],
                ]);
            }
        }

        return $quickWins->take(3); // Max 3 quick wins
    }

    /**
     * Generate Strategic recommendations from historical performance data
     */
    protected function generateStrategicRecommendations(User $user, CampaignAudit $audit, array $focusAreas = []): Collection
    {
        $strategic = collect();

        // Analyze historical data
        $trends = $this->analyzePerformanceTrends($user);

        // Check open rate trend
        if (isset($trends['open_rate']) && $trends['open_rate']['trend'] === 'declining') {
            $strategic->push([
                'type' => CampaignRecommendation::TYPE_STRATEGIC,
                'priority' => 8,
                'title' => 'Reverse declining open rates',
                'description' => "Your open rates have dropped by {$trends['open_rate']['change']}% over the last 30 days. Focus on subject line optimization and list hygiene.",
                'expected_impact' => 5.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'category' => CampaignAuditIssue::CATEGORY_DELIVERABILITY,
                'action_steps' => [
                    'A/B test subject lines on your next 5 campaigns',
                    'Remove subscribers inactive for 90+ days',
                    'Check your sender reputation on mail-tester.com',
                    'Verify SPF/DKIM/DMARC records',
                ],
                'context' => [
                    'open_rate_change' => $trends['open_rate']['change'],
                    'current_rate' => $trends['open_rate']['current'],
                ],
            ]);
        }

        // Check click rate patterns
        if (isset($trends['click_rate']) && $trends['click_rate']['current'] < 2.0) {
            $strategic->push([
                'type' => CampaignRecommendation::TYPE_STRATEGIC,
                'priority' => 7,
                'title' => 'Improve email click-through rates',
                'description' => 'Your click rate is below 2%, which is under industry average. Better CTAs and content structure can help.',
                'expected_impact' => 4.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                'action_steps' => [
                    'Use button-style CTAs instead of text links',
                    'Place your main CTA above the fold',
                    'Use action-oriented language ("Get Started" vs "Click Here")',
                    'Limit to 1-2 primary CTAs per email',
                ],
                'context' => [
                    'current_click_rate' => $trends['click_rate']['current'],
                ],
            ]);
        }

        // Check for segmentation opportunities
        $subscriberCount = $user->subscribers()->count();
        $taggedPercent = $this->calculateTaggedSubscriberPercent($user);

        if ($subscriberCount > 500 && $taggedPercent < 30) {
            $strategic->push([
                'type' => CampaignRecommendation::TYPE_STRATEGIC,
                'priority' => 6,
                'title' => 'Implement subscriber segmentation',
                'description' => "Only {$taggedPercent}% of your subscribers are tagged. Better segmentation leads to 14% higher click rates.",
                'expected_impact' => 5.0,
                'effort_level' => CampaignRecommendation::EFFORT_HIGH,
                'category' => CampaignAuditIssue::CATEGORY_SEGMENTATION,
                'action_steps' => [
                    'Create interest-based tags from click behavior',
                    'Set up tag automations for key actions',
                    'Segment by engagement level (active/passive/cold)',
                    'Use dynamic content blocks for different segments',
                ],
                'context' => [
                    'subscriber_count' => $subscriberCount,
                    'tagged_percent' => $taggedPercent,
                ],
            ]);
        }

        return $strategic->take(2); // Max 2 strategic recommendations
    }

    /**
     * Generate AI-powered Growth recommendations
     */
    protected function generateGrowthRecommendations(User $user, CampaignAudit $audit): Collection
    {
        $growth = collect();

        try {
            $integration = $this->aiService->getDefaultIntegration();
            if (!$integration) {
                return $growth;
            }

            // Build context for AI
            $context = $this->buildAiContext($user, $audit);

            $prompt = $this->buildGrowthPrompt($context);
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 800,
                'temperature' => 0.4,
            ]);

            $aiRecommendations = $this->parseAiRecommendations($response);

            foreach ($aiRecommendations as $rec) {
                $growth->push([
                    'type' => CampaignRecommendation::TYPE_GROWTH,
                    'priority' => $rec['priority'] ?? 5,
                    'title' => $rec['title'],
                    'description' => $rec['description'],
                    'expected_impact' => $rec['expected_impact'] ?? 5.0,
                    'effort_level' => $rec['effort_level'] ?? CampaignRecommendation::EFFORT_MEDIUM,
                    'category' => $rec['category'] ?? null,
                    'action_steps' => $rec['action_steps'] ?? [],
                    'context' => [
                        'source' => 'ai_generated',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('AI growth recommendations failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $growth->take(2); // Max 2 AI recommendations
    }

    /**
     * Analyze historical performance trends
     */
    protected function analyzePerformanceTrends(User $user): array
    {
        $trends = [];

        // Get messages from last 30 days
        $recentMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('channel', 'email')
            ->where('created_at', '>=', now()->subDays(30))
            ->withCount('queueEntries as total_sent')
            ->get();

        if ($recentMessages->count() < 3) {
            return $trends;
        }

        // Calculate open rate trend
        $totalSent = $recentMessages->sum('total_sent');
        $totalOpens = 0;
        $totalClicks = 0;

        foreach ($recentMessages as $message) {
            $totalOpens += EmailOpen::where('message_id', $message->id)->count();
            $totalClicks += EmailClick::where('message_id', $message->id)->count();
        }

        $currentOpenRate = $totalSent > 0 ? ($totalOpens / $totalSent) * 100 : 0;
        $currentClickRate = $totalSent > 0 ? ($totalClicks / $totalSent) * 100 : 0;

        // Compare with previous period
        $olderMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('channel', 'email')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->withCount('queueEntries as total_sent')
            ->get();

        if ($olderMessages->count() >= 3) {
            $olderSent = $olderMessages->sum('total_sent');
            $olderOpens = 0;

            foreach ($olderMessages as $message) {
                $olderOpens += EmailOpen::where('message_id', $message->id)->count();
            }

            $previousOpenRate = $olderSent > 0 ? ($olderOpens / $olderSent) * 100 : 0;
            $openRateChange = $currentOpenRate - $previousOpenRate;

            $trends['open_rate'] = [
                'current' => round($currentOpenRate, 1),
                'previous' => round($previousOpenRate, 1),
                'change' => round($openRateChange, 1),
                'trend' => $openRateChange < -2 ? 'declining' : ($openRateChange > 2 ? 'improving' : 'stable'),
            ];
        }

        $trends['click_rate'] = [
            'current' => round($currentClickRate, 1),
        ];

        return $trends;
    }

    /**
     * Calculate percentage of subscribers with tags
     */
    protected function calculateTaggedSubscriberPercent(User $user): float
    {
        $total = $user->subscribers()->count();
        if ($total === 0) {
            return 0;
        }

        $tagged = $user->subscribers()->whereHas('tags')->count();
        return round(($tagged / $total) * 100, 1);
    }

    /**
     * Calculate priority from impact and effort
     */
    protected function calculatePriorityFromImpactEffort(float $impact, string $effort): int
    {
        $effortMultipliers = [
            CampaignRecommendation::EFFORT_LOW => 1.5,
            CampaignRecommendation::EFFORT_MEDIUM => 1.0,
            CampaignRecommendation::EFFORT_HIGH => 0.7,
        ];

        $multiplier = $effortMultipliers[$effort] ?? 1.0;
        $priority = (int) round($impact * $multiplier);

        return max(1, min(10, $priority));
    }

    /**
     * Prioritize recommendations based on target improvement
     */
    protected function prioritizeRecommendations(Collection $recommendations, float $targetPercent): Collection
    {
        return $recommendations->sortByDesc(function ($rec) use ($targetPercent) {
            // Calculate a score based on impact vs effort ratio
            // and how much it contributes to the target
            $impactScore = $rec['expected_impact'] ?? 0;

            $effortPenalty = match ($rec['effort_level'] ?? 'medium') {
                CampaignRecommendation::EFFORT_LOW => 0,
                CampaignRecommendation::EFFORT_MEDIUM => 1,
                CampaignRecommendation::EFFORT_HIGH => 3,
                default => 1,
            };

            // Boost quick wins
            $typeBonus = match ($rec['type'] ?? '') {
                CampaignRecommendation::TYPE_QUICK_WIN => 2,
                CampaignRecommendation::TYPE_STRATEGIC => 1,
                CampaignRecommendation::TYPE_GROWTH => 0,
                default => 0,
            };

            return ($impactScore - $effortPenalty + $typeBonus) * ($rec['priority'] ?? 5);
        })->values();
    }

    /**
     * Build context for AI recommendation generation
     */
    protected function buildAiContext(User $user, CampaignAudit $audit): array
    {
        return [
            'score' => $audit->overall_score,
            'critical_issues' => $audit->critical_count,
            'warning_issues' => $audit->warning_count,
            'subscriber_count' => $user->subscribers()->count(),
            'list_count' => $user->contactLists()->count(),
            'automation_count' => $user->automationRules()->count(),
            'has_sms' => Message::where('user_id', $user->id)->where('channel', 'sms')->exists(),
            'categories_with_issues' => $audit->issues()->distinct()->pluck('category')->toArray(),
        ];
    }

    /**
     * Build prompt for AI growth recommendations
     */
    protected function buildGrowthPrompt(array $context): string
    {
        return <<<PROMPT
You are an email marketing expert advisor. Based on this user's campaign data, provide 1-2 growth-focused recommendations.

USER DATA:
- Current health score: {$context['score']}/100
- Critical issues: {$context['critical_issues']}
- Warning issues: {$context['warning_issues']}
- Total subscribers: {$context['subscriber_count']}
- Contact lists: {$context['list_count']}
- Automations: {$context['automation_count']}
- Uses SMS: {$context['has_sms']}

CATEGORIES WITH ISSUES: {$context['categories_with_issues']}

Provide strategic growth recommendations that go beyond fixing current issues. Focus on scaling their email marketing success.

RESPOND IN JSON FORMAT ONLY:
[
  {
    "title": "Short recommendation title",
    "description": "2-3 sentence description",
    "expected_impact": 5.0,
    "effort_level": "medium",
    "priority": 6,
    "category": "revenue|content|automation|segmentation",
    "action_steps": ["Step 1", "Step 2", "Step 3"]
  }
]

Return 1-2 actionable recommendations. Focus on high-impact strategies.
PROMPT;
    }

    /**
     * Parse AI response into recommendations array
     */
    protected function parseAiRecommendations(string $response): array
    {
        try {
            if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
                $recommendations = json_decode($matches[0], true);
                if (is_array($recommendations)) {
                    return array_slice($recommendations, 0, 2);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse AI recommendations', ['response' => $response]);
        }

        return [];
    }

    /**
     * Track impact of applied recommendations
     */
    public function measureRecommendationImpact(CampaignRecommendation $recommendation): ?float
    {
        if (!$recommendation->is_applied || !$recommendation->applied_at) {
            return null;
        }

        $audit = $recommendation->audit;
        $user = $audit->user;

        // Get audits after the recommendation was applied
        $laterAudits = CampaignAudit::where('user_id', $user->id)
            ->where('status', CampaignAudit::STATUS_COMPLETED)
            ->where('created_at', '>', $recommendation->applied_at)
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        if ($laterAudits->isEmpty()) {
            return null;
        }

        // Calculate average improvement in relevant metrics
        $baseScore = $audit->overall_score;
        $laterAvgScore = $laterAudits->avg('overall_score');

        $improvement = $laterAvgScore - $baseScore;

        // Update the recommendation with measured impact
        $recommendation->recordImpact($improvement);

        return $improvement;
    }

    /**
     * Get summary of recommendation effectiveness for a user
     */
    public function getRecommendationEffectiveness(User $user): array
    {
        $applied = CampaignRecommendation::whereHas('audit', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('is_applied', true)->get();

        if ($applied->isEmpty()) {
            return [
                'total_applied' => 0,
                'avg_expected_impact' => 0,
                'avg_actual_impact' => null,
                'success_rate' => null,
            ];
        }

        $withResults = $applied->whereNotNull('result_impact');

        return [
            'total_applied' => $applied->count(),
            'avg_expected_impact' => round($applied->avg('expected_impact'), 1),
            'avg_actual_impact' => $withResults->count() > 0
                ? round($withResults->avg('result_impact'), 1)
                : null,
            'success_rate' => $withResults->count() > 0
                ? round($withResults->where('result_impact', '>', 0)->count() / $withResults->count() * 100)
                : null,
        ];
    }
}
