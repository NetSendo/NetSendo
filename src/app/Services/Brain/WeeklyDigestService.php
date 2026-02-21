<?php

namespace App\Services\Brain;

use App\Models\AiActionPlan;
use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\AiExecutionLog;
use App\Models\AiGoal;
use App\Models\AiPerformanceSnapshot;
use App\Models\ContactList;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeeklyDigestService
{
    public function __construct(
        protected AiService $aiService,
    ) {}

    /**
     * Generate a weekly digest for a user.
     * Returns structured data including metrics, comparisons, AI insights, and formatted report.
     */
    public function generateDigest(User $user, string $period = 'week'): array
    {
        $days = $period === 'month' ? 30 : 7;
        $since = Carbon::now()->subDays($days);
        $previousStart = Carbon::now()->subDays($days * 2);
        $previousEnd = $since;

        // Gather all metrics
        $current = $this->gatherMetrics($user, $since, now());
        $previous = $this->gatherMetrics($user, $previousStart, $previousEnd);

        // Calculate trends (% change)
        $trends = $this->calculateTrends($current, $previous);

        // Get top campaigns from PerformanceTracker
        $topCampaigns = $this->getTopCampaigns($user, $since);

        // Get goal progress
        $goalProgress = $this->getGoalProgress($user);

        // Brain activity summary
        $brainActivity = $this->getBrainActivity($user, $since);

        // Build context for AI report
        $context = [
            'period' => $period,
            'days' => $days,
            'current' => $current,
            'previous' => $previous,
            'trends' => $trends,
            'top_campaigns' => $topCampaigns,
            'goal_progress' => $goalProgress,
            'brain_activity' => $brainActivity,
        ];

        // Generate AI-powered strategic report
        $aiReport = $this->generateAiReport($user, $context);

        // Log the digest
        AiBrainActivityLog::create([
            'user_id' => $user->id,
            'event_type' => 'weekly_digest',
            'status' => 'completed',
            'metadata' => [
                'period' => $period,
                'metrics' => $current,
                'trends' => $trends,
                'ai_report' => $aiReport,
            ],
        ]);

        return [
            'period' => $period,
            'days' => $days,
            'generated_at' => now()->toISOString(),
            'metrics' => $current,
            'previous_metrics' => $previous,
            'trends' => $trends,
            'top_campaigns' => $topCampaigns,
            'goal_progress' => $goalProgress,
            'brain_activity' => $brainActivity,
            'ai_report' => $aiReport,
        ];
    }

    /**
     * Gather all metrics for a given period.
     */
    protected function gatherMetrics(User $user, Carbon $from, Carbon $to): array
    {
        return [
            'campaigns' => $this->getCampaignMetrics($user, $from, $to),
            'subscribers' => $this->getSubscriberMetrics($user, $from, $to),
            'crm' => $this->getCrmMetrics($user, $from, $to),
            'ai_usage' => $this->getAiUsageMetrics($user, $from, $to),
        ];
    }

    protected function getCampaignMetrics(User $user, Carbon $from, Carbon $to): array
    {
        $sent = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $uniqueOpens = EmailOpen::whereHas('message', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [$from, $to])
            ->distinct('subscriber_id')
            ->count('subscriber_id');

        $uniqueClicks = EmailClick::whereHas('message', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [$from, $to])
            ->distinct('subscriber_id')
            ->count('subscriber_id');

        $activeSubscribers = Subscriber::where('user_id', $user->id)->active()->count();
        $openRate = $activeSubscribers > 0 ? round(($uniqueOpens / $activeSubscribers) * 100, 2) : 0;
        $clickRate = $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 2) : 0;

        return [
            'sent' => $sent,
            'opens' => $uniqueOpens,
            'clicks' => $uniqueClicks,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
        ];
    }

    protected function getSubscriberMetrics(User $user, Carbon $from, Carbon $to): array
    {
        $total = Subscriber::where('user_id', $user->id)->count();
        $active = Subscriber::where('user_id', $user->id)->active()->count();
        $newSubs = Subscriber::where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $unsubs = Subscriber::where('user_id', $user->id)
            ->where('status', 'unsubscribed')
            ->whereBetween('updated_at', [$from, $to])
            ->count();
        $bounced = Subscriber::where('user_id', $user->id)
            ->where('status', 'bounced')
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'new' => $newSubs,
            'unsubscribed' => $unsubs,
            'bounced' => $bounced,
            'net_growth' => $newSubs - $unsubs - $bounced,
        ];
    }

    protected function getCrmMetrics(User $user, Carbon $from, Carbon $to): array
    {
        try {
            $newContacts = CrmContact::where('user_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->count();
            $totalContacts = CrmContact::where('user_id', $user->id)->count();

            $newDeals = CrmDeal::where('user_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->count();
            $wonDeals = CrmDeal::where('user_id', $user->id)
                ->where('status', 'won')
                ->whereBetween('updated_at', [$from, $to])
                ->count();
            $wonValue = CrmDeal::where('user_id', $user->id)
                ->where('status', 'won')
                ->whereBetween('updated_at', [$from, $to])
                ->sum('value');
            $openDealsValue = CrmDeal::where('user_id', $user->id)
                ->where('status', 'open')
                ->sum('value');

            return [
                'new_contacts' => $newContacts,
                'total_contacts' => $totalContacts,
                'new_deals' => $newDeals,
                'won_deals' => $wonDeals,
                'won_value' => round($wonValue, 2),
                'open_pipeline_value' => round($openDealsValue, 2),
            ];
        } catch (\Exception $e) {
            return [
                'new_contacts' => 0,
                'total_contacts' => 0,
                'new_deals' => 0,
                'won_deals' => 0,
                'won_value' => 0,
                'open_pipeline_value' => 0,
            ];
        }
    }

    protected function getAiUsageMetrics(User $user, Carbon $from, Carbon $to): array
    {
        $logs = AiExecutionLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to]);

        $total = $logs->count();
        $success = (clone $logs)->where('status', 'success')->count();
        $errors = (clone $logs)->where('status', 'error')->count();
        $tokensTotal = (clone $logs)->selectRaw('COALESCE(SUM(tokens_input), 0) + COALESCE(SUM(tokens_output), 0) as total')->value('total');

        $plans = AiActionPlan::where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to]);
        $plansCreated = $plans->count();
        $plansCompleted = (clone $plans)->where('status', 'completed')->count();

        return [
            'executions' => $total,
            'success' => $success,
            'errors' => $errors,
            'tokens' => (int) $tokensTotal,
            'plans_created' => $plansCreated,
            'plans_completed' => $plansCompleted,
        ];
    }

    /**
     * Calculate trend percentages between current and previous periods.
     */
    protected function calculateTrends(array $current, array $previous): array
    {
        $calc = function ($cur, $prev) {
            if ($prev == 0) return $cur > 0 ? 100 : 0;
            return round((($cur - $prev) / $prev) * 100, 1);
        };

        return [
            'campaigns_sent' => $calc($current['campaigns']['sent'], $previous['campaigns']['sent']),
            'open_rate' => round(($current['campaigns']['open_rate'] ?? 0) - ($previous['campaigns']['open_rate'] ?? 0), 2),
            'click_rate' => round(($current['campaigns']['click_rate'] ?? 0) - ($previous['campaigns']['click_rate'] ?? 0), 2),
            'subscriber_growth' => $calc($current['subscribers']['new'], $previous['subscribers']['new']),
            'unsubscribes' => $calc($current['subscribers']['unsubscribed'], $previous['subscribers']['unsubscribed']),
            'crm_new_contacts' => $calc($current['crm']['new_contacts'], $previous['crm']['new_contacts']),
            'crm_deals_won' => $calc($current['crm']['won_deals'], $previous['crm']['won_deals']),
            'ai_executions' => $calc($current['ai_usage']['executions'], $previous['ai_usage']['executions']),
        ];
    }

    /**
     * Get top performing campaigns from performance snapshots.
     */
    protected function getTopCampaigns(User $user, Carbon $since): array
    {
        try {
            return AiPerformanceSnapshot::forUser($user->id)
                ->where('captured_at', '>=', $since)
                ->orderByDesc('open_rate')
                ->limit(3)
                ->get()
                ->map(fn($s) => [
                    'title' => $s->campaign_title,
                    'open_rate' => $s->open_rate,
                    'click_rate' => $s->click_rate,
                    'sent_count' => $s->sent_count,
                    'above_average' => $s->isAboveAverage(),
                    'what_worked' => $s->what_worked,
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get goal progress summary.
     */
    protected function getGoalProgress(User $user): array
    {
        try {
            $goals = AiGoal::forUser($user->id)->get();

            return [
                'total' => $goals->count(),
                'active' => $goals->where('status', 'active')->count(),
                'completed' => $goals->where('status', 'completed')->count(),
                'paused' => $goals->where('status', 'paused')->count(),
                'recent_completed' => $goals->where('status', 'completed')
                    ->sortByDesc('updated_at')
                    ->take(3)
                    ->map(fn($g) => $g->title)
                    ->values()
                    ->toArray(),
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'completed' => 0, 'paused' => 0, 'recent_completed' => []];
        }
    }

    /**
     * Get Brain activity summary for the period.
     */
    protected function getBrainActivity(User $user, Carbon $since): array
    {
        $activities = AiBrainActivityLog::forUser($user->id)
            ->where('created_at', '>=', $since)
            ->get();

        $situationAnalyses = $activities->where('event_type', 'situation_analysis')
            ->where('status', 'completed')
            ->count();

        $performanceReviews = $activities->where('event_type', 'performance_review')
            ->count();

        return [
            'cron_cycles' => $activities->where('event_type', 'cron_cycle')->count(),
            'situation_analyses' => $situationAnalyses,
            'performance_reviews' => $performanceReviews,
            'total_events' => $activities->count(),
        ];
    }

    /**
     * Generate AI-powered strategic report from gathered data.
     */
    protected function generateAiReport(User $user, array $context): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $lang = $settings->preferred_language ?? app()->getLocale();

        $periodLabel = $context['period'] === 'month' ? 'monthly' : 'weekly';
        $dataJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
You are a senior marketing strategist preparing a {$periodLabel} performance digest.

METRICS DATA:
{$dataJson}

Generate a concise, actionable {$periodLabel} report containing:

1. **📊 Executive Summary** — 2-3 sentences summarizing overall performance
2. **📈 Key Wins** — What went well this {$context['period']} (with specific numbers)
3. **⚠️ Areas of Concern** — What needs attention (declining metrics, high unsubscribes, etc.)
4. **🎯 Strategic Recommendations** — 3-5 specific, actionable next steps based on data
5. **🔮 Focus for Next {$periodLabel}** — Priority actions for the coming period

RULES:
- Use specific numbers from the data, not vague statements
- Compare with previous period trends where meaningful
- If campaign performance data is available, reference specific campaigns
- If goal data exists, comment on goal progress
- Keep it concise — this is a digest, not a full report
- Use emoji for quick scanning
- Respond in language code: {$lang}

FORMAT: Clean markdown text. No JSON wrapper.
PROMPT;

        try {
            return $this->aiService->chat(
                $prompt,
                '',
                ['max_tokens' => 4000, 'temperature' => 0.5],
                $user,
                'analytics'
            );
        } catch (\Exception $e) {
            Log::warning('Weekly digest AI report generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackReport($context);
        }
    }

    /**
     * Fallback report when AI is unavailable.
     */
    protected function generateFallbackReport(array $context): string
    {
        $c = $context['current']['campaigns'];
        $s = $context['current']['subscribers'];
        $t = $context['trends'];
        $icon = fn($v) => $v > 0 ? '📈' : ($v < 0 ? '📉' : '➡️');

        $lines = [
            "📊 **Performance Digest**\n",
            "**Campaigns**: {$c['sent']} sent | OR: {$c['open_rate']}% | CTR: {$c['click_rate']}%",
            "{$icon($t['campaigns_sent'])} Campaigns {$t['campaigns_sent']}% vs previous period",
            "{$icon($t['open_rate'])} Open rate {$t['open_rate']}pp change\n",
            "**Subscribers**: {$s['total']} total | +{$s['new']} new | -{$s['unsubscribed']} unsubs",
            "Net growth: {$s['net_growth']}",
        ];

        $crm = $context['current']['crm'];
        if ($crm['total_contacts'] > 0) {
            $lines[] = "\n**CRM**: {$crm['new_contacts']} new contacts | {$crm['won_deals']} deals won";
        }

        return implode("\n", $lines);
    }

    /**
     * Send digest via Telegram.
     */
    public function sendViaTelegram(User $user, array $digest): bool
    {
        $settings = AiBrainSettings::getForUser($user->id);

        if (!$settings->isTelegramConnected() || empty($settings->telegram_chat_id)) {
            return false;
        }

        try {
            $telegram = app(TelegramBotService::class);

            $periodEmoji = $digest['period'] === 'month' ? '📅' : '📆';
            $periodLabel = $digest['period'] === 'month' ? 'Monthly' : 'Weekly';

            // Build message
            $lines = ["{$periodEmoji} *Brain {$periodLabel} Digest*\n"];

            // Quick stats header
            $c = $digest['metrics']['campaigns'];
            $s = $digest['metrics']['subscribers'];
            $t = $digest['trends'];

            $icon = fn($v) => $v > 0 ? '📈' : ($v < 0 ? '📉' : '➡️');

            $lines[] = "📧 *Campaigns*: {$c['sent']} sent | OR: {$c['open_rate']}% | CTR: {$c['click_rate']}%";
            $lines[] = "{$icon($t['campaigns_sent'])} {$t['campaigns_sent']}% vs prev period";
            $lines[] = "";
            $lines[] = "👥 *Subscribers*: {$s['total']} (+{$s['new']}, -{$s['unsubscribed']})";
            $lines[] = "{$icon($t['subscriber_growth'])} Growth: {$t['subscriber_growth']}%";

            // CRM (if data exists)
            $crm = $digest['metrics']['crm'];
            if ($crm['total_contacts'] > 0) {
                $lines[] = "";
                $lines[] = "💼 *CRM*: +{$crm['new_contacts']} contacts | {$crm['won_deals']} deals won";
                if ($crm['won_value'] > 0) {
                    $lines[] = "💰 Won: " . number_format($crm['won_value'], 0) . " | Pipeline: " . number_format($crm['open_pipeline_value'], 0);
                }
            }

            // Top campaigns
            if (!empty($digest['top_campaigns'])) {
                $lines[] = "";
                $lines[] = "🏆 *Top campaigns*:";
                foreach ($digest['top_campaigns'] as $camp) {
                    $badge = $camp['above_average'] ? '🟢' : '🟡';
                    $lines[] = "  {$badge} {$camp['title']}: OR {$camp['open_rate']}%, CTR {$camp['click_rate']}%";
                }
            }

            // AI report (truncated for Telegram)
            if (!empty($digest['ai_report'])) {
                $lines[] = "";
                $report = $digest['ai_report'];
                // Telegram limit ~4096 chars, be conservative
                if (strlen($report) > 2000) {
                    $report = mb_substr($report, 0, 1997) . '...';
                }
                $lines[] = $report;
            }

            $message = implode("\n", $lines);

            // Telegram 4096 char limit — split if needed
            if (strlen($message) > 4000) {
                $parts = str_split($message, 3900);
                foreach ($parts as $part) {
                    $telegram->sendMessage($settings->telegram_chat_id, $part, $user);
                }
            } else {
                $telegram->sendMessage($settings->telegram_chat_id, $message, $user);
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('Weekly digest Telegram delivery failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if a digest should be sent (based on last digest date and settings).
     */
    public function shouldSendDigest(User $user, string $period = 'week'): bool
    {
        $lastDigest = AiBrainActivityLog::forUser($user->id)
            ->where('event_type', 'weekly_digest')
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->first();

        if (!$lastDigest) {
            return true; // Never sent before
        }

        $daysSinceLastDigest = $lastDigest->created_at->diffInDays(now());
        $threshold = $period === 'month' ? 25 : 5; // Allow some flex (5 days for weekly, 25 for monthly)

        return $daysSinceLastDigest >= $threshold;
    }

    /**
     * Get last digest data for the user (for API/frontend).
     */
    public function getLastDigest(User $user): ?array
    {
        $lastDigest = AiBrainActivityLog::forUser($user->id)
            ->where('event_type', 'weekly_digest')
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->first();

        if (!$lastDigest) {
            return null;
        }

        return [
            'generated_at' => $lastDigest->created_at->toISOString(),
            'period' => $lastDigest->metadata['period'] ?? 'week',
            'metrics' => $lastDigest->metadata['metrics'] ?? [],
            'trends' => $lastDigest->metadata['trends'] ?? [],
            'ai_report' => $lastDigest->metadata['ai_report'] ?? '',
        ];
    }
}
