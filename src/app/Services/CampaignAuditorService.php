<?php

namespace App\Services;

use App\Models\CampaignAudit;
use App\Models\CampaignAuditIssue;
use App\Models\User;
use App\Models\Message;
use App\Models\ContactList;
use App\Models\AutomationRule;
use App\Models\Subscriber;
use App\Models\EmailOpen;
use App\Models\EmailClick;
use App\Services\AI\AiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignAuditorService
{
    public function __construct(
        protected AiService $aiService,
        protected TemplateAiService $templateAiService,
        protected CampaignAdvisorService $advisorService
    ) {}

    /**
     * Run a complete audit for a user
     */
    public function runAudit(User $user, string $type = CampaignAudit::TYPE_FULL, int $lookbackDays = 30): CampaignAudit
    {
        $audit = CampaignAudit::create([
            'user_id' => $user->id,
            'status' => CampaignAudit::STATUS_RUNNING,
            'audit_type' => $type,
            'started_at' => now(),
        ]);

        try {
            // Count items to analyze
            $messagesCount = Message::where('user_id', $user->id)->count();
            $listsCount = ContactList::where('user_id', $user->id)->count();
            $automationsCount = AutomationRule::where('user_id', $user->id)->count();

            $audit->update([
                'messages_analyzed' => $messagesCount,
                'lists_analyzed' => $listsCount,
                'automations_analyzed' => $automationsCount,
            ]);

            // Calculate frequency lookback (proportional - roughly 7 days for 30 day period)
            $frequencyLookback = max(7, (int) ceil($lookbackDays / 4.3));

            // 1. Analyze campaign frequency
            $this->analyzeFrequency($audit, $user, $frequencyLookback);

            // 2. Check content for spam signals
            $this->analyzeContent($audit, $user, $lookbackDays);

            // 3. Evaluate timing patterns
            $this->analyzeTiming($audit, $user, $lookbackDays);

            // 4. Check segmentation quality
            $this->analyzeSegmentation($audit, $user, $lookbackDays);

            // 5. Deliverability risk assessment
            $this->analyzeDeliverability($audit, $user, $lookbackDays);

            // 6. Automation analysis
            $this->analyzeAutomations($audit, $user);

            // 7. Revenue optimization opportunities
            $this->analyzeRevenueOpportunities($audit, $user, $lookbackDays);

            // 8. AI-powered deep analysis (only for full audit)
            if ($type === CampaignAudit::TYPE_FULL) {
                $this->runAiAnalysis($audit, $user, $lookbackDays);
            }

            // Calculate overall score
            $this->calculateOverallScore($audit);

            // Generate recommendations using the advisor service
            $targetPercent = $user->settings['campaign_advisor']['weekly_improvement_target'] ?? 2.0;
            $this->advisorService->generateRecommendations($audit, $targetPercent);

            // Generate AI summary
            $language = $user->settings['campaign_advisor']['analysis_language'] ?? 'en';
            $aiSummary = $this->generateAiSummary($audit, $user, $language);

            $audit->update([
                'status' => CampaignAudit::STATUS_COMPLETED,
                'ai_summary' => $aiSummary,
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Campaign audit failed', [
                'audit_id' => $audit->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $audit->update([
                'status' => CampaignAudit::STATUS_FAILED,
                'summary' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);
        }

        return $audit->fresh(['issues', 'recommendations']);
    }

    /**
     * Analyze sending frequency
     */
    protected function analyzeFrequency(CampaignAudit $audit, User $user, int $lookbackDays = 7): void
    {
        // Check messages sent in the lookback period per list
        $lists = ContactList::where('user_id', $user->id)->get();

        foreach ($lists as $list) {
            $messagesCount = Message::where('user_id', $user->id)
                ->where('status', 'sent')
                ->where('created_at', '>=', now()->subDays($lookbackDays))
                ->whereHas('contactLists', function ($q) use ($list) {
                    $q->where('contact_lists.id', $list->id);
                })
                ->count();

            // Calculate threshold proportionally (more than 5 per 7 days)
            $threshold = max(5, (int) ceil($lookbackDays * 0.7));

            if ($messagesCount > $threshold) {
                $audit->issues()->create([
                    'severity' => CampaignAuditIssue::SEVERITY_CRITICAL,
                    'category' => CampaignAuditIssue::CATEGORY_FREQUENCY,
                    'issue_key' => CampaignAuditIssue::ISSUE_OVER_MAILING,
                    'message' => "List '{$list->name}' received {$messagesCount} emails in {$lookbackDays} days – unsubscribe risk +18%",
                    'recommendation' => 'Reduce sending to 2-3 emails per week. Consider segmenting high-engagement users for more frequent contact.',
                    'impact_score' => 18.0,
                    'affected_type' => 'contact_list',
                    'affected_id' => $list->id,
                    'context' => [
                        'emails_sent' => $messagesCount,
                        'period_days' => $lookbackDays,
                        'recommended_max' => (int) ceil($lookbackDays * 0.4),
                    ],
                    'is_fixable' => false,
                ]);
            }
        }

        // Check for back-to-back promotional emails
        $consecutivePromos = $this->countConsecutivePromotionalEmails($user);
        if ($consecutivePromos >= 5) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_CRITICAL,
                'category' => CampaignAuditIssue::CATEGORY_FREQUENCY,
                'issue_key' => 'consecutive_promos',
                'message' => "{$consecutivePromos} promotional emails sent in a row without value content",
                'recommendation' => 'Mix in educational, storytelling, or pure-value content between promotions. The ideal ratio is 3:1 value-to-promo.',
                'impact_score' => 15.0,
                'is_fixable' => false,
            ]);
        }
    }

    /**
     * Analyze content quality
     */
    protected function analyzeContent(CampaignAudit $audit, User $user, int $lookbackDays = 30): void
    {
        $recentMessages = Message::where('user_id', $user->id)
            ->whereIn('status', ['sent', 'draft', 'scheduled'])
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->limit(20)
            ->get();

        foreach ($recentMessages as $message) {
            // Check for spam patterns
            $spamScore = $this->calculateSpamScore($message->content ?? '');

            if ($spamScore > 50) {
                $audit->issues()->create([
                    'severity' => $spamScore > 70 ? CampaignAuditIssue::SEVERITY_CRITICAL : CampaignAuditIssue::SEVERITY_WARNING,
                    'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                    'issue_key' => CampaignAuditIssue::ISSUE_SPAM_CONTENT,
                    'message' => "Message '{$message->subject}' has spam-like content (risk score: {$spamScore}%)",
                    'recommendation' => 'Reduce promotional language, avoid ALL CAPS, limit exclamation marks, and remove spam trigger words.',
                    'impact_score' => $spamScore / 5,
                    'affected_type' => 'message',
                    'affected_id' => $message->id,
                    'context' => [
                        'spam_score' => $spamScore,
                        'subject' => $message->subject,
                    ],
                    'is_fixable' => true,
                ]);
            }

            // Check for missing preheader
            if (empty($message->preheader)) {
                $audit->issues()->create([
                    'severity' => CampaignAuditIssue::SEVERITY_INFO,
                    'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                    'issue_key' => CampaignAuditIssue::ISSUE_MISSING_PREHEADER,
                    'message' => "Message '{$message->subject}' has no preheader – missing inbox preview opportunity",
                    'recommendation' => 'Add a compelling preheader to increase open rates by 5-10%.',
                    'impact_score' => 5.0,
                    'affected_type' => 'message',
                    'affected_id' => $message->id,
                    'is_fixable' => true,
                ]);
            }

            // Check for long subject lines
            if (strlen($message->subject ?? '') > 60) {
                $audit->issues()->create([
                    'severity' => CampaignAuditIssue::SEVERITY_INFO,
                    'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                    'issue_key' => CampaignAuditIssue::ISSUE_LONG_SUBJECT,
                    'message' => "Message '{$message->subject}' has a long subject line (" . strlen($message->subject) . " chars) – may be truncated on mobile",
                    'recommendation' => 'Keep subject lines under 50 characters for optimal mobile display.',
                    'impact_score' => 3.0,
                    'affected_type' => 'message',
                    'affected_id' => $message->id,
                    'is_fixable' => true,
                ]);
            }

            // Check for personalization
            $content = $message->content ?? '';
            $hasPersonalization = preg_match('/\[\[(first_name|name|email)\]\]/', $content);
            if (!$hasPersonalization && $message->type === 'broadcast') {
                $audit->issues()->create([
                    'severity' => CampaignAuditIssue::SEVERITY_INFO,
                    'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                    'issue_key' => CampaignAuditIssue::ISSUE_NO_PERSONALIZATION,
                    'message' => "Broadcast '{$message->subject}' has no personalization – generic feel reduces engagement",
                    'recommendation' => 'Add [[first_name]] or [[name]] placeholders to personalize the message. Personalized emails get 26% higher open rates.',
                    'impact_score' => 6.0,
                    'affected_type' => 'message',
                    'affected_id' => $message->id,
                    'is_fixable' => true,
                ]);
            }
        }
    }

    /**
     * Analyze send timing
     */
    protected function analyzeTiming(CampaignAudit $audit, User $user, int $lookbackDays = 30): void
    {
        // Get sent messages with their performance
        $sentMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('channel', 'email')
            ->whereNotNull('scheduled_at')
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->get();

        if ($sentMessages->isEmpty()) {
            return;
        }

        // Analyze send times
        $poorTimingMessages = $sentMessages->filter(function ($message) {
            $hour = $message->scheduled_at?->hour ?? 12;
            // Poor timing: very early morning (0-6) or very late (23-24)
            return $hour < 6 || $hour >= 23;
        });

        if ($poorTimingMessages->count() > 2) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_WARNING,
                'category' => CampaignAuditIssue::CATEGORY_TIMING,
                'issue_key' => CampaignAuditIssue::ISSUE_POOR_TIMING,
                'message' => "{$poorTimingMessages->count()} messages sent during low-engagement hours (before 6 AM or after 11 PM)",
                'recommendation' => 'Schedule emails between 9-11 AM or 2-4 PM for optimal engagement. Tuesday-Thursday typically performs best.',
                'impact_score' => 8.0,
                'context' => [
                    'affected_messages' => $poorTimingMessages->pluck('id')->toArray(),
                    'recommended_hours' => '9-11 AM, 2-4 PM',
                ],
                'is_fixable' => false,
            ]);
        }
    }

    /**
     * Analyze segmentation quality
     */
    protected function analyzeSegmentation(CampaignAudit $audit, User $user, int $lookbackDays = 30): void
    {
        // Check for broadcasts without exclusions
        $broadcastsWithoutExclusions = Message::where('user_id', $user->id)
            ->where('type', 'broadcast')
            ->where('status', 'sent')
            ->whereDoesntHave('excludedLists')
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->count();

        if ($broadcastsWithoutExclusions > 5) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_WARNING,
                'category' => CampaignAuditIssue::CATEGORY_SEGMENTATION,
                'issue_key' => CampaignAuditIssue::ISSUE_NO_SEGMENTATION,
                'message' => "{$broadcastsWithoutExclusions} broadcasts sent without exclusion lists – missing targeted approach",
                'recommendation' => 'Use exclusion lists to target active segments. Exclude inactive subscribers (no opens in 90 days) to improve deliverability.',
                'impact_score' => 8.0,
                'context' => [
                    'broadcasts_without_exclusions' => $broadcastsWithoutExclusions,
                ],
                'is_fixable' => false,
            ]);
        }

        // Check for lists without tags usage
        $listsWithoutTags = ContactList::where('user_id', $user->id)
            ->whereDoesntHave('subscribers.tags')
            ->count();

        $totalLists = ContactList::where('user_id', $user->id)->count();

        if ($totalLists > 0 && ($listsWithoutTags / $totalLists) > 0.7) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_INFO,
                'category' => CampaignAuditIssue::CATEGORY_SEGMENTATION,
                'issue_key' => 'no_tags_usage',
                'message' => "70%+ of your lists have no tagged subscribers – limited segmentation capability",
                'recommendation' => 'Use tags to segment by interests, behavior, or purchase history. Tagged campaigns achieve 14% higher click rates.',
                'impact_score' => 5.0,
                'is_fixable' => false,
            ]);
        }
    }

    /**
     * Analyze deliverability risks
     */
    protected function analyzeDeliverability(CampaignAudit $audit, User $user, int $lookbackDays = 30): void
    {
        // Check for stale lists (not updated in lookback period)
        $staleLists = ContactList::where('user_id', $user->id)
            ->where('updated_at', '<', now()->subDays($lookbackDays))
            ->get();

        foreach ($staleLists as $list) {
            $subscriberCount = $list->subscribers()->count();
            if ($subscriberCount > 100) {
                $audit->issues()->create([
                    'severity' => CampaignAuditIssue::SEVERITY_WARNING,
                    'category' => CampaignAuditIssue::CATEGORY_DELIVERABILITY,
                    'issue_key' => CampaignAuditIssue::ISSUE_STALE_LIST,
                    'message' => "List '{$list->name}' ({$subscriberCount} subscribers) not cleaned for {$lookbackDays}+ days – deliverability risk",
                    'recommendation' => 'Remove inactive subscribers (no opens in 90 days). Clean bounced emails. Re-engagement campaigns can recover 10-15% of inactive users.',
                    'impact_score' => 12.0,
                    'affected_type' => 'contact_list',
                    'affected_id' => $list->id,
                    'context' => [
                        'subscriber_count' => $subscriberCount,
                        'last_updated' => $list->updated_at->toISOString(),
                        'suggested_action' => 'clean_inactive_90_days',
                    ],
                    'is_fixable' => true,
                ]);
            }
        }

        // Calculate average open rate
        $sentMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('channel', 'email')
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->withCount('queueEntries as total_sent')
            ->get();

        if ($sentMessages->count() >= 5) {
            $totalSent = $sentMessages->sum('total_sent');
            $totalOpens = 0;

            foreach ($sentMessages as $message) {
                $totalOpens += EmailOpen::where('message_id', $message->id)->count();
            }

            if ($totalSent > 0) {
                $avgOpenRate = ($totalOpens / $totalSent) * 100;

                if ($avgOpenRate < 15) {
                    $audit->issues()->create([
                        'severity' => CampaignAuditIssue::SEVERITY_CRITICAL,
                        'category' => CampaignAuditIssue::CATEGORY_DELIVERABILITY,
                        'issue_key' => CampaignAuditIssue::ISSUE_LOW_OPEN_RATE,
                        'message' => "Average open rate is only " . round($avgOpenRate, 1) . "% – significantly below industry average (20-25%)",
                        'recommendation' => 'Check sender reputation, improve subject lines, clean inactive subscribers, and verify SPF/DKIM settings.',
                        'impact_score' => 20.0,
                        'context' => [
                            'open_rate' => round($avgOpenRate, 2),
                            'total_sent' => $totalSent,
                            'total_opens' => $totalOpens,
                            'industry_average' => 22,
                        ],
                        'is_fixable' => false,
                    ]);
                }
            }
        }
    }

    /**
     * Analyze automations
     */
    protected function analyzeAutomations(CampaignAudit $audit, User $user): void
    {
        $automations = AutomationRule::where('user_id', $user->id)->get();

        if ($automations->isEmpty()) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_INFO,
                'category' => CampaignAuditIssue::CATEGORY_AUTOMATION,
                'issue_key' => CampaignAuditIssue::ISSUE_NO_AUTOMATION,
                'message' => "No automations configured – missing opportunity for hands-free engagement",
                'recommendation' => 'Set up welcome sequences and re-engagement automations. Automated emails generate 320% more revenue than non-automated.',
                'impact_score' => 15.0,
                'is_fixable' => false,
            ]);
            return;
        }

        // Check for inactive automations
        $inactiveAutomations = $automations->filter(fn ($a) => !$a->is_active);

        if ($inactiveAutomations->count() > 3) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_WARNING,
                'category' => CampaignAuditIssue::CATEGORY_AUTOMATION,
                'issue_key' => CampaignAuditIssue::ISSUE_INACTIVE_AUTOMATION,
                'message' => "{$inactiveAutomations->count()} automations are disabled – potential revenue left on the table",
                'recommendation' => 'Review and activate paused automations if still relevant. Delete obsolete ones to keep your system clean.',
                'impact_score' => 8.0,
                'context' => [
                    'inactive_count' => $inactiveAutomations->count(),
                    'inactive_names' => $inactiveAutomations->pluck('name')->take(5)->toArray(),
                ],
                'is_fixable' => false,
            ]);
        }

        // Check for automations never executed
        $neverExecuted = $automations->filter(fn ($a) => $a->is_active && $a->execution_count === 0);

        if ($neverExecuted->count() > 0) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_INFO,
                'category' => CampaignAuditIssue::CATEGORY_AUTOMATION,
                'issue_key' => 'automations_never_triggered',
                'message' => "{$neverExecuted->count()} active automations have never been triggered",
                'recommendation' => 'Verify trigger conditions. Check if the triggering events are actually occurring in your system.',
                'impact_score' => 3.0,
                'context' => [
                    'automation_names' => $neverExecuted->pluck('name')->toArray(),
                ],
                'is_fixable' => false,
            ]);
        }
    }

    /**
     * Analyze revenue opportunities
     */
    protected function analyzeRevenueOpportunities(CampaignAudit $audit, User $user, int $lookbackDays = 30): void
    {
        // Check if user has phone numbers but no SMS campaigns
        $listsWithPhones = ContactList::where('user_id', $user->id)
            ->whereHas('subscribers', function ($q) {
                $q->whereNotNull('phone');
            })
            ->count();

        $smsCampaigns = Message::where('user_id', $user->id)
            ->where('channel', 'sms')
            ->count();

        if ($listsWithPhones > 0 && $smsCampaigns === 0) {
            // Estimate phone count
            $phoneCount = Subscriber::whereNotNull('phone')
                ->whereHas('contactLists', function ($q) use ($user) {
                    $q->where('contact_lists.user_id', $user->id);
                })
                ->count();

            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_INFO,
                'category' => CampaignAuditIssue::CATEGORY_REVENUE,
                'issue_key' => CampaignAuditIssue::ISSUE_SMS_MISSING,
                'message' => "{$phoneCount} subscribers have phone numbers but you're not using SMS – ~12% conversion opportunity lost",
                'recommendation' => 'Add SMS follow-up after email clicks. Multi-channel campaigns improve conversion by 12-15%. SMS has 98% open rate.',
                'impact_score' => 12.0,
                'context' => [
                    'subscribers_with_phones' => $phoneCount,
                    'lists_with_phones' => $listsWithPhones,
                ],
                'is_fixable' => false,
            ]);
        }

        // Check for abandoned flows (clicks but no follow-up)
        $messagesWithClicks = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->whereHas('queueEntries')
            ->get();

        $messagesWithoutFollowUp = 0;
        foreach ($messagesWithClicks as $message) {
            $clickCount = EmailClick::where('message_id', $message->id)->count();
            // If message has clicks but is not part of an automation sequence
            if ($clickCount > 10 && !$message->trigger_type) {
                $messagesWithoutFollowUp++;
            }
        }

        if ($messagesWithoutFollowUp > 3) {
            $audit->issues()->create([
                'severity' => CampaignAuditIssue::SEVERITY_WARNING,
                'category' => CampaignAuditIssue::CATEGORY_REVENUE,
                'issue_key' => CampaignAuditIssue::ISSUE_NO_FOLLOW_UP,
                'message' => "{$messagesWithoutFollowUp} high-engagement messages have no follow-up automation",
                'recommendation' => 'Create click-triggered automations to nurture engaged subscribers. Clickers are 3x more likely to convert.',
                'impact_score' => 10.0,
                'is_fixable' => false,
            ]);
        }
    }

    /**
     * Run AI-powered deep analysis (for full audits only)
     */
    protected function runAiAnalysis(CampaignAudit $audit, User $user, int $lookbackDays = 30): void
    {
        try {
            $integration = $this->aiService->getDefaultIntegration();
            if (!$integration) {
                return; // Skip AI analysis if no integration configured
            }

            // Get recent message subjects for AI analysis
            $recentSubjects = Message::where('user_id', $user->id)
                ->whereIn('status', ['sent', 'draft', 'scheduled'])
                ->where('created_at', '>=', now()->subDays($lookbackDays))
                ->limit(10)
                ->pluck('subject')
                ->filter()
                ->toArray();

            if (count($recentSubjects) < 3) {
                return; // Not enough data for AI analysis
            }

            $prompt = $this->buildAiAnalysisPrompt($recentSubjects, $audit);
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 1000,
                'temperature' => 0.3,
            ]);

            $insights = $this->parseAiInsights($response);

            foreach ($insights as $insight) {
                $audit->issues()->create([
                    'severity' => $insight['severity'] ?? CampaignAuditIssue::SEVERITY_INFO,
                    'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                    'issue_key' => 'ai_insight',
                    'message' => $insight['message'] ?? 'AI-detected improvement opportunity',
                    'recommendation' => $insight['recommendation'] ?? null,
                    'impact_score' => $insight['impact'] ?? 5.0,
                    'is_fixable' => false,
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('AI analysis failed during audit', [
                'audit_id' => $audit->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the audit if AI analysis fails
        }
    }

    /**
     * Build prompt for AI analysis
     */
    protected function buildAiAnalysisPrompt(array $subjects, CampaignAudit $audit): string
    {
        $subjectsList = implode("\n", array_map(fn ($s) => "- {$s}", $subjects));
        $existingIssues = $audit->issues()->count();
        $dateContext = AiService::getDateContext();

        return <<<PROMPT
{$dateContext}

You are an email marketing expert analyzing a user's campaign strategy.

RECENT EMAIL SUBJECTS:
{$subjectsList}

CONTEXT:
- Already detected {$existingIssues} issues through rule-based analysis
- User is looking for additional strategic insights

ANALYZE:
1. Subject line patterns and effectiveness
2. Potential content gaps
3. Strategic recommendations

RESPOND IN JSON FORMAT ONLY:
[
  {
    "severity": "warning|info",
    "message": "Brief issue description",
    "recommendation": "Actionable fix",
    "impact": 5.0
  }
]

Return 1-3 insights maximum. Only include genuinely valuable suggestions.
PROMPT;
    }

    /**
     * Parse AI response into insights array
     */
    protected function parseAiInsights(string $response): array
    {
        try {
            // Extract JSON from response
            if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
                $insights = json_decode($matches[0], true);
                if (is_array($insights)) {
                    return array_slice($insights, 0, 3); // Max 3 insights
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse AI insights', ['response' => $response]);
        }

        return [];
    }

    /**
     * Calculate spam score for content
     */
    protected function calculateSpamScore(string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        $score = 0;
        $content = strip_tags($content);

        // Spam patterns with their point values
        $spamPatterns = [
            '/FREE/i' => 5,
            '/URGENT/i' => 5,
            '/ACT NOW/i' => 8,
            '/LIMITED TIME/i' => 5,
            '/LAST CHANCE/i' => 5,
            '/!!!+/' => 10,
            '/\$\$\$/' => 10,
            '/100% FREE/i' => 15,
            '/WINNER/i' => 10,
            '/GUARANTEED/i' => 8,
            '/CLICK HERE/i' => 5,
            '/BUY NOW/i' => 5,
            '/ORDER NOW/i' => 5,
            '/DON\'T MISS/i' => 3,
            '/[A-Z]{6,}/' => 3, // All caps words (6+ chars)
            '/\b(viagra|casino|lottery|prize)\b/i' => 20,
        ];

        foreach ($spamPatterns as $pattern => $points) {
            if (preg_match_all($pattern, $content, $matches)) {
                $score += count($matches[0]) * $points;
            }
        }

        return min(100, $score);
    }

    /**
     * Count consecutive promotional emails
     */
    protected function countConsecutivePromotionalEmails(User $user): int
    {
        // Simple heuristic: check last 10 messages for promo patterns
        $recentMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $consecutivePromos = 0;
        $promoPatterns = ['/buy/i', '/sale/i', '/offer/i', '/discount/i', '/deal/i', '/% off/i'];

        foreach ($recentMessages as $message) {
            $isPromo = false;
            $content = ($message->subject ?? '') . ' ' . ($message->content ?? '');

            foreach ($promoPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $isPromo = true;
                    break;
                }
            }

            if ($isPromo) {
                $consecutivePromos++;
            } else {
                break; // Stop counting at first non-promo
            }
        }

        return $consecutivePromos;
    }

    /**
     * Calculate overall score based on issues
     */
    protected function calculateOverallScore(CampaignAudit $audit): void
    {
        $criticalCount = $audit->issues()->where('severity', CampaignAuditIssue::SEVERITY_CRITICAL)->count();
        $warningCount = $audit->issues()->where('severity', CampaignAuditIssue::SEVERITY_WARNING)->count();
        $infoCount = $audit->issues()->where('severity', CampaignAuditIssue::SEVERITY_INFO)->count();

        // Start at 100, deduct for issues
        $score = 100;
        $score -= $criticalCount * CampaignAuditIssue::SEVERITY_WEIGHTS[CampaignAuditIssue::SEVERITY_CRITICAL];
        $score -= $warningCount * CampaignAuditIssue::SEVERITY_WEIGHTS[CampaignAuditIssue::SEVERITY_WARNING];
        $score -= $infoCount * CampaignAuditIssue::SEVERITY_WEIGHTS[CampaignAuditIssue::SEVERITY_INFO];

        // Calculate estimated revenue loss based on impact scores
        $totalImpact = $audit->issues()->sum('impact_score');
        // Simple estimation: impact score * $50 per point
        $estimatedRevenueLoss = $totalImpact * 50;

        $fixableCount = $audit->issues()->where('is_fixable', true)->count();

        $audit->update([
            'overall_score' => max(0, min(100, $score)),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'estimated_revenue_loss' => $estimatedRevenueLoss,
            'summary' => [
                'total_issues' => $criticalCount + $warningCount + $infoCount,
                'fixable_issues' => $fixableCount,
                'categories' => $audit->issues()
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
            ],
        ]);
    }

    /**
     * Generate AI-powered professional summary of the audit
     */
    protected function generateAiSummary(CampaignAudit $audit, User $user, string $language = 'en'): ?string
    {
        try {
            // Get user's preferred AI integration or fallback to default
            $integrationId = $user->settings['campaign_advisor']['ai_integration_id'] ?? null;

            if ($integrationId) {
                $integration = \App\Models\AiIntegration::find($integrationId);
                // Fallback to default if selected integration is not active
                if (!$integration || !$integration->is_active) {
                    $integration = $this->aiService->getDefaultIntegration();
                }
            } else {
                $integration = $this->aiService->getDefaultIntegration();
            }

            if (!$integration) {
                return null;
            }

            $languageNames = [
                'en' => 'English',
                'pl' => 'Polish',
                'de' => 'German',
                'es' => 'Spanish',
                'fr' => 'French',
                'it' => 'Italian',
                'pt' => 'Portuguese',
                'nl' => 'Dutch',
            ];

            $languageName = $languageNames[$language] ?? 'English';

            // Get issues grouped by category
            $issuesByCategory = $audit->issues()
                ->selectRaw('category, severity, COUNT(*) as count')
                ->groupBy('category', 'severity')
                ->get()
                ->groupBy('category')
                ->toArray();

            $issuesSummary = collect($issuesByCategory)->map(function ($items, $category) {
                $counts = collect($items)->pluck('count', 'severity')->toArray();
                return "{$category}: " . json_encode($counts);
            })->implode(', ');

            // Get top recommendations
            $topRecommendations = $audit->recommendations()
                ->orderBy('priority', 'desc')
                ->limit(3)
                ->pluck('title')
                ->toArray();

            // Build top priorities list
            $prioritiesList = '';
            if (!empty($topRecommendations)) {
                foreach ($topRecommendations as $rec) {
                    $prioritiesList .= "- {$rec}\n";
                }
            } else {
                $prioritiesList = "- No specific recommendations generated yet\n";
            }

            $dateContext = AiService::getDateContext();

            $prompt = <<<PROMPT
{$dateContext}

You are a professional email marketing consultant. Write a professional executive summary of this campaign audit.

IMPORTANT RULES:
1. Write the ENTIRE response in {$languageName} language.
2. Use INFORMAL, friendly tone - address the reader directly with informal "you" (in Polish: "Ty", not "Państwa" or "Pan/Pani").
3. Be encouraging but honest about issues.
4. Do NOT use formal greetings like "Dear..." or "Dear Sir/Madam".

AUDIT DATA:
- Overall Health Score: {$audit->overall_score}/100
- Score Level: {$audit->score_label}
- Critical Issues: {$audit->critical_count}
- Warnings: {$audit->warning_count}
- Informational: {$audit->info_count}
- Messages Analyzed: {$audit->messages_analyzed}
- Lists Analyzed: {$audit->lists_analyzed}
- Automations Analyzed: {$audit->automations_analyzed}
- Estimated Revenue Loss: \${$audit->estimated_revenue_loss}

ISSUES BY CATEGORY: {$issuesSummary}

TOP PRIORITIES:
{$prioritiesList}

Write a professional 3-4 paragraph executive summary that:
1. Opens with an overall assessment of the email marketing health
2. Highlights the most critical findings and their business impact
3. Provides actionable priorities for improvement
4. Ends with an encouraging but realistic outlook

Keep the tone professional yet friendly, consultative, and data-driven. Speak directly to the reader using "you/your".
Write in {$languageName} language only. Remember: use informal "you" form.
PROMPT;

            // Use max_tokens_large from integration or default to 8000
            $maxTokens = $integration->max_tokens_large ?? 8000;

            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => $maxTokens,
                'temperature' => 0.5,
            ]);

            return trim($response);

        } catch (\Exception $e) {
            Log::warning('AI summary generation failed', [
                'audit_id' => $audit->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
