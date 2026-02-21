<?php

namespace App\Services\Brain;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainActivityLog;
use App\Models\AiPerformanceSnapshot;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Support\Facades\Log;

/**
 * PerformanceTracker — Closed-loop feedback system for the Brain.
 *
 * Reviews completed campaign actions, captures performance metrics,
 * and generates AI-powered lessons that feed back into future planning.
 *
 * Called during the CRON cycle AFTER situation analysis and BEFORE task generation.
 */
class PerformanceTracker
{
    /**
     * Minimum hours after sending before we review a campaign.
     * Gives enough time for opens/clicks to accumulate.
     */
    private const MIN_HOURS_AFTER_SEND = 24;

    /**
     * Maximum hours after sending to still capture performance.
     * Beyond this, the data is too stale for meaningful review.
     */
    private const MAX_HOURS_AFTER_SEND = 168; // 7 days

    /**
     * Industry average benchmarks (fallback when user has insufficient data).
     */
    private const INDUSTRY_BENCHMARKS = [
        'avg_open_rate' => 21.0,
        'avg_click_rate' => 2.6,
        'avg_unsubscribe_rate' => 0.26,
        'avg_bounce_rate' => 0.7,
    ];

    public function __construct(
        protected AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
    ) {}

    /**
     * Review all completed campaign plans that haven't been reviewed yet.
     *
     * Called from CRON pipeline between SituationAnalyzer and task generation.
     *
     * @return array{reviewed: int, lessons: array}
     */
    public function reviewCompletedCampaigns(User $user): array
    {
        $results = [
            'reviewed' => 0,
            'lessons' => [],
        ];

        // Find completed campaign plans that haven't been performance-reviewed
        $plans = $this->findReviewablePlans($user);

        if ($plans->isEmpty()) {
            return $results;
        }

        // Get user's historical benchmarks (or fall back to industry)
        $benchmarks = AiPerformanceSnapshot::getUserBenchmarks($user->id)
            ?? self::INDUSTRY_BENCHMARKS;

        foreach ($plans as $plan) {
            try {
                $snapshot = $this->capturePlanPerformance($plan, $user, $benchmarks);

                if ($snapshot) {
                    $results['reviewed']++;
                    $results['lessons'][] = [
                        'plan_id' => $plan->id,
                        'title' => $snapshot->campaign_title,
                        'open_rate' => $snapshot->open_rate,
                        'click_rate' => $snapshot->click_rate,
                        'above_average' => $snapshot->isAboveAverage(),
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('PerformanceTracker: review failed', [
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($results['reviewed'] > 0) {
            AiBrainActivityLog::logEvent($user->id, 'performance_review', 'completed', null, [
                'reviewed_count' => $results['reviewed'],
                'above_average_count' => collect($results['lessons'])->where('above_average', true)->count(),
            ]);
        }

        return $results;
    }

    /**
     * Find completed campaign plans eligible for performance review.
     */
    protected function findReviewablePlans(User $user)
    {
        $now = now();

        // Get completed campaign plans from the last 7 days
        $plans = AiActionPlan::forUser($user->id)
            ->where('agent_type', 'campaign')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $now->copy()->subHours(self::MAX_HOURS_AFTER_SEND))
            ->where('completed_at', '<=', $now->copy()->subHours(self::MIN_HOURS_AFTER_SEND))
            ->with('steps')
            ->get();

        // Filter out plans that already have a performance snapshot
        $reviewedPlanIds = AiPerformanceSnapshot::forUser($user->id)
            ->whereIn('ai_action_plan_id', $plans->pluck('id'))
            ->pluck('ai_action_plan_id')
            ->toArray();

        return $plans->reject(fn($plan) => in_array($plan->id, $reviewedPlanIds));
    }

    /**
     * Capture performance metrics for a single completed plan.
     */
    protected function capturePlanPerformance(
        AiActionPlan $plan,
        User $user,
        array $benchmarks,
    ): ?AiPerformanceSnapshot {
        // Find the message_id from the plan's steps
        $messageId = $this->extractMessageId($plan);

        if (!$messageId) {
            Log::info('PerformanceTracker: No message_id found in plan', ['plan_id' => $plan->id]);
            return null;
        }

        $message = Message::find($messageId);
        if (!$message || $message->sent_count < 1) {
            return null;
        }

        // Gather metrics
        $metrics = $this->gatherMetrics($message);

        if ($metrics['sent_count'] < 1) {
            return null;
        }

        // Compare against benchmarks
        $comparison = $this->compareWithBenchmarks($metrics, $benchmarks);

        // Generate AI-powered lessons
        $lessons = $this->generateLessons($plan, $message, $metrics, $comparison, $user);

        // Save snapshot
        $snapshot = AiPerformanceSnapshot::create([
            'user_id' => $user->id,
            'ai_action_plan_id' => $plan->id,
            'message_id' => $message->id,
            'campaign_title' => $plan->title ?? $message->subject ?? 'Campaign',
            'agent_type' => 'campaign',
            'sent_count' => $metrics['sent_count'],
            'open_rate' => $metrics['open_rate'],
            'click_rate' => $metrics['click_rate'],
            'unsubscribe_rate' => $metrics['unsubscribe_rate'],
            'bounce_rate' => $metrics['bounce_rate'],
            'benchmark_comparison' => $comparison,
            'lessons_learned' => $lessons['summary'] ?? null,
            'what_worked' => $lessons['what_worked'] ?? [],
            'what_to_improve' => $lessons['what_to_improve'] ?? [],
            'review_status' => 'reviewed',
            'campaign_sent_at' => $message->send_at ?? $plan->completed_at,
            'captured_at' => now(),
        ]);

        // Save lessons to Knowledge Base for future context
        $this->saveToKnowledgeBase($user, $snapshot, $lessons);

        return $snapshot;
    }

    /**
     * Extract message_id from plan steps (campaign agent stores it in step results).
     */
    protected function extractMessageId(AiActionPlan $plan): ?int
    {
        // Check completed steps for message_id in results
        foreach ($plan->steps as $step) {
            $result = $step->result ?? [];

            if (!empty($result['message_id'])) {
                return (int) $result['message_id'];
            }
        }

        // Check step configs as fallback
        foreach ($plan->steps as $step) {
            $config = $step->config ?? [];

            if (!empty($config['message_id'])) {
                return (int) $config['message_id'];
            }
        }

        return null;
    }

    /**
     * Gather campaign performance metrics from message data.
     */
    protected function gatherMetrics(Message $message): array
    {
        $sentCount = max(1, $message->sent_count ?? 0);

        // Count unique opens
        $openCount = EmailOpen::where('message_id', $message->id)
            ->distinct('subscriber_id')
            ->count('subscriber_id');

        // Count unique clicks
        $clickCount = EmailClick::where('message_id', $message->id)
            ->distinct('subscriber_id')
            ->count('subscriber_id');

        // Count bounces and unsubscribes from queue entries
        $queueStats = MessageQueueEntry::where('message_id', $message->id)
            ->selectRaw("
                SUM(CASE WHEN status = 'failed' AND error_message LIKE '%bounce%' THEN 1 ELSE 0 END) as bounces,
                SUM(CASE WHEN status = 'skipped' AND error_message LIKE '%unsubscri%' THEN 1 ELSE 0 END) as unsubs
            ")
            ->first();

        $bounceCount = $queueStats->bounces ?? 0;
        $unsubCount = $queueStats->unsubs ?? 0;

        return [
            'sent_count' => $sentCount,
            'open_count' => $openCount,
            'click_count' => $clickCount,
            'bounce_count' => $bounceCount,
            'unsubscribe_count' => $unsubCount,
            'open_rate' => round(($openCount / $sentCount) * 100, 2),
            'click_rate' => round(($clickCount / $sentCount) * 100, 2),
            'bounce_rate' => round(($bounceCount / $sentCount) * 100, 2),
            'unsubscribe_rate' => round(($unsubCount / $sentCount) * 100, 2),
        ];
    }

    /**
     * Compare metrics against benchmarks.
     */
    protected function compareWithBenchmarks(array $metrics, array $benchmarks): array
    {
        $compare = function (float $value, float $benchmark, bool $lowerIsBetter = false): string {
            $threshold = $benchmark * 0.1; // 10% tolerance
            if ($lowerIsBetter) {
                if ($value < $benchmark - $threshold) return 'above'; // lower = better for unsub/bounce
                if ($value > $benchmark + $threshold) return 'below';
                return 'average';
            }
            if ($value > $benchmark + $threshold) return 'above';
            if ($value < $benchmark - $threshold) return 'below';
            return 'average';
        };

        return [
            'open_rate' => $compare($metrics['open_rate'], $benchmarks['avg_open_rate']),
            'click_rate' => $compare($metrics['click_rate'], $benchmarks['avg_click_rate']),
            'unsubscribe_rate' => $compare($metrics['unsubscribe_rate'], $benchmarks['avg_unsubscribe_rate'] ?? 0.26, true),
            'bounce_rate' => $compare($metrics['bounce_rate'], $benchmarks['avg_bounce_rate'] ?? 0.7, true),
        ];
    }

    /**
     * Generate AI-powered lessons from campaign performance.
     */
    protected function generateLessons(
        AiActionPlan $plan,
        Message $message,
        array $metrics,
        array $comparison,
        User $user,
    ): array {
        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return $this->generateFallbackLessons($metrics, $comparison);
        }

        $langInstruction = $this->getLanguageInstruction($user);
        $comparisonJson = json_encode($comparison);
        $metricsJson = json_encode($metrics);

        $prompt = <<<PROMPT
You are a marketing performance analyst. A campaign was executed by the AI Brain and here are the results.

CAMPAIGN INFO:
Title: {$plan->title}
Subject: {$message->subject}
Content preview: {$this->getContentPreview($message)}

METRICS:
{$metricsJson}

BENCHMARK COMPARISON:
{$comparisonJson}

{$langInstruction}

Analyze this campaign's performance and generate actionable lessons. Respond in JSON:
{
  "summary": "1-2 sentence overall assessment",
  "what_worked": ["list of things that worked well (max 3 items)"],
  "what_to_improve": ["list of specific improvements for next time (max 3 items)"],
  "style_notes": "any notes about content style/tone that should be remembered for future campaigns"
}

Be specific and actionable. Reference actual metrics. Don't be generic.
PROMPT;

        try {
            $response = $this->aiService->generateContent(
                AiService::prependDateContext($prompt, $user->timezone),
                $integration,
                ['max_tokens' => 2000, 'temperature' => 0.3]
            );

            return $this->parseJson($response) ?? $this->generateFallbackLessons($metrics, $comparison);
        } catch (\Exception $e) {
            Log::warning('PerformanceTracker: AI lesson generation failed', ['error' => $e->getMessage()]);
            return $this->generateFallbackLessons($metrics, $comparison);
        }
    }

    /**
     * Generate simple rule-based lessons as fallback (no AI needed).
     */
    protected function generateFallbackLessons(array $metrics, array $comparison): array
    {
        $worked = [];
        $improve = [];

        if ($comparison['open_rate'] === 'above') {
            $worked[] = "Open rate ({$metrics['open_rate']}%) was above average — subject line was effective.";
        } elseif ($comparison['open_rate'] === 'below') {
            $improve[] = "Open rate ({$metrics['open_rate']}%) was below average — try more compelling subject lines.";
        }

        if ($comparison['click_rate'] === 'above') {
            $worked[] = "Click rate ({$metrics['click_rate']}%) was above average — CTAs were effective.";
        } elseif ($comparison['click_rate'] === 'below') {
            $improve[] = "Click rate ({$metrics['click_rate']}%) was below average — improve CTA placement and copy.";
        }

        if ($comparison['unsubscribe_rate'] === 'below') {
            $improve[] = "Unsubscribe rate ({$metrics['unsubscribe_rate']}%) was higher than average — consider segmenting audience better.";
        } else {
            $worked[] = "Low unsubscribe rate ({$metrics['unsubscribe_rate']}%) indicates content relevance.";
        }

        $overallGood = collect($comparison)->filter(fn($v) => $v === 'above' || $v === 'average')->count();
        $summary = $overallGood >= 3
            ? "Campaign performed well overall with {$metrics['open_rate']}% open rate and {$metrics['click_rate']}% CTR."
            : "Campaign has room for improvement — {$metrics['open_rate']}% open rate, {$metrics['click_rate']}% CTR.";

        return [
            'summary' => $summary,
            'what_worked' => $worked ?: ['Campaign was delivered successfully.'],
            'what_to_improve' => $improve ?: ['Continue monitoring performance trends.'],
        ];
    }

    /**
     * Save performance insights to Knowledge Base.
     */
    protected function saveToKnowledgeBase(User $user, AiPerformanceSnapshot $snapshot, array $lessons): void
    {
        try {
            $content = "📊 Campaign Performance Review — {$snapshot->campaign_title}\n";
            $content .= "Date: " . ($snapshot->campaign_sent_at?->format('Y-m-d') ?? 'N/A') . "\n\n";
            $content .= "Metrics: OR {$snapshot->open_rate}%, CTR {$snapshot->click_rate}%, ";
            $content .= "Unsub {$snapshot->unsubscribe_rate}%, Sent: {$snapshot->sent_count}\n\n";

            if (!empty($lessons['summary'])) {
                $content .= "Assessment: {$lessons['summary']}\n\n";
            }

            if (!empty($lessons['what_worked'])) {
                $content .= "✅ What worked:\n";
                foreach ($lessons['what_worked'] as $item) {
                    $content .= "- {$item}\n";
                }
                $content .= "\n";
            }

            if (!empty($lessons['what_to_improve'])) {
                $content .= "📈 To improve:\n";
                foreach ($lessons['what_to_improve'] as $item) {
                    $content .= "- {$item}\n";
                }
                $content .= "\n";
            }

            if (!empty($lessons['style_notes'])) {
                $content .= "🎨 Style notes: {$lessons['style_notes']}\n";
            }

            $this->knowledgeBase->addEntry(
                $user,
                'insights',
                "Campaign Review — {$snapshot->campaign_title}",
                $content,
                'performance_tracker'
            );

            // Extract performance patterns from above-benchmark campaigns
            $this->knowledgeBase->extractPerformancePatterns($user, $snapshot);
        } catch (\Exception $e) {
            Log::warning('PerformanceTracker: KB save failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get a compact performance summary for context injection into SituationAnalyzer.
     */
    public function getPerformanceContext(User $user): array
    {
        $snapshots = AiPerformanceSnapshot::forUser($user->id)
            ->recent(30)
            ->orderByDesc('captured_at')
            ->limit(5)
            ->get();

        if ($snapshots->isEmpty()) {
            return ['has_data' => false];
        }

        return [
            'has_data' => true,
            'recent_campaigns' => $snapshots->map(fn($s) => [
                'title' => $s->campaign_title,
                'open_rate' => $s->open_rate,
                'click_rate' => $s->click_rate,
                'above_average' => $s->isAboveAverage(),
                'date' => $s->campaign_sent_at?->format('Y-m-d'),
            ])->toArray(),
            'avg_open_rate' => round($snapshots->avg('open_rate'), 2),
            'avg_click_rate' => round($snapshots->avg('click_rate'), 2),
            'best_performing' => $snapshots->sortByDesc('open_rate')->first()?->campaign_title,
            'worst_performing' => $snapshots->sortBy('open_rate')->first()?->campaign_title,
        ];
    }

    /**
     * Get content preview for the AI prompt.
     */
    protected function getContentPreview(Message $message): string
    {
        $content = strip_tags($message->content ?? '');
        return mb_substr($content, 0, 300) . (mb_strlen($content) > 300 ? '...' : '');
    }

    /**
     * Get language instruction for AI prompts.
     */
    protected function getLanguageInstruction(User $user): string
    {
        $settings = \App\Models\AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        $langName = \App\Models\AiBrainSettings::getLanguageName($langCode);
        return "IMPORTANT: Respond in {$langName}.";
    }

    /**
     * Parse JSON from AI response (handles markdown code blocks).
     */
    protected function parseJson(string $response): ?array
    {
        $response = preg_replace('/```(?:json)?\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
}
