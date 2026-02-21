<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiExecutionLog;
use App\Models\ContactList;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsAgent extends BaseAgent
{
    public function getName(): string { return 'analytics'; }
    public function getLabel(): string { return __('brain.analytics.label'); }

    public function getCapabilities(): array
    {
        return [
            'campaign_analytics', 'subscriber_analytics', 'monthly_report',
            'compare_campaigns', 'engagement_analysis', 'ai_usage_stats',
        ];
    }

    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are a marketing analytics expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}
{$knowledgeContext}

{$langInstruction}

Create an analysis plan in JSON:
{"title":"","description":"","steps":[{"action_type":"","title":"","description":"","config":{}}]}

Available action_types:
- fetch_campaign_stats: campaign statistics (config: {days: 30})
- fetch_subscriber_stats: subscriber statistics (config: {days: 30})
- generate_report: AI report (config: {type: "monthly|weekly|custom"})
- compare_performance: compare campaigns (config: {})
- analyze_trends: trend analysis (config: {days: 30})
- ai_usage_report: AI Brain usage (config: {days: 30})

Analytics agent is read-only — never modifies data.
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.3], $user, 'analytics');
            $data = $this->parseJson($response);
            if (!$data || empty($data['steps'])) return null;

            return $this->createPlan($user, $intent['intent'] ?? 'analytics',
                $data['title'] ?? __('brain.analytics.plan_title'), $data['description'] ?? null, $data['steps']);
        } catch (\Exception $e) {
            Log::error('AnalyticsAgent plan failed', ['error' => $e->getMessage()]);
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
                if (!empty($result['message'])) $messages[] = $result['message'];
            } catch (\Exception $e) {
                $messages[] = "⚠️ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => implode("\n\n---\n\n", $messages) ?: __('brain.analytics.analysis_done'),
        ];
    }

    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'fetch_campaign_stats' => $this->fetchCampaignStats($step, $user),
            'fetch_subscriber_stats' => $this->fetchSubscriberStats($step, $user),
            'generate_report' => $this->generateReport($step, $user),
            'compare_performance' => $this->comparePerformance($step, $user),
            'analyze_trends' => $this->analyzeTrends($step, $user),
            'ai_usage_report' => $this->aiUsageReport($step, $user),
            default => ['status' => 'completed', 'message' => "Action noted"],
        };
    }

    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $stats = $this->gatherQuickStats($user);
        $langInstruction = $this->getLanguageInstruction($user);
        $prompt = "You are an analytics expert. Statistics:\n{$stats}\nQuestion: {$intent['intent']}\n{$knowledgeContext}\n\n{$langInstruction}\n\nProvide analysis with numbers and recommendations. Use emoji.";
        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.5], $user, 'analytics');
        return ['type' => 'advice', 'message' => $response];
    }

    // === Step Executors ===

    protected function fetchCampaignStats(AiActionPlanStep $step, User $user): array
    {
        $days = $step->config['days'] ?? 30;
        $since = Carbon::now()->subDays($days);

        $totalSent = Message::where('user_id', $user->id)->where('status', 'sent')
            ->where('updated_at', '>=', $since)->count();
        $uniqueOpens = EmailOpen::whereHas('message', fn($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', $since)->distinct('subscriber_id')->count('subscriber_id');
        $uniqueClicks = EmailClick::whereHas('message', fn($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', $since)->distinct('subscriber_id')->count('subscriber_id');

        $subCount = Subscriber::where('user_id', $user->id)->active()->count();
        $openRate = $subCount > 0 ? round(($uniqueOpens / $subCount) * 100, 2) : 0;
        $clickRate = $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 2) : 0;

        $msg = __('brain.analytics.campaign_header', ['days' => $days]) . "\n" . __('brain.analytics.campaign_sent', ['count' => $totalSent]) . "\n" . __('brain.analytics.campaign_opens', ['count' => $uniqueOpens]) . "\n" . __('brain.analytics.campaign_clicks', ['count' => $uniqueClicks]) . "\n" . __('brain.analytics.campaign_rates', ['open_rate' => $openRate, 'click_rate' => $clickRate]);

        return ['status' => 'completed', 'data' => compact('totalSent', 'uniqueOpens', 'uniqueClicks', 'openRate', 'clickRate'), 'message' => $msg];
    }

    protected function fetchSubscriberStats(AiActionPlanStep $step, User $user): array
    {
        $days = $step->config['days'] ?? 30;
        $since = Carbon::now()->subDays($days);

        $total = Subscriber::where('user_id', $user->id)->count();
        $active = Subscriber::where('user_id', $user->id)->active()->count();
        $newSubs = Subscriber::where('user_id', $user->id)->where('created_at', '>=', $since)->count();
        $unsubs = Subscriber::where('user_id', $user->id)->where('status', 'unsubscribed')
            ->where('updated_at', '>=', $since)->count();
        $bounced = Subscriber::where('user_id', $user->id)->where('status', 'bounced')->count();
        $growth = $active > 0 ? round((($newSubs - $unsubs) / $active) * 100, 2) : 0;

        $msg = __('brain.analytics.subscriber_header', ['days' => $days]) . "\n" . __('brain.analytics.subscriber_total', ['total' => $total, 'active' => $active]) . "\n" . __('brain.analytics.subscriber_new', ['new' => $newSubs, 'unsubs' => $unsubs]) . "\n" . __('brain.analytics.subscriber_bounced', ['bounced' => $bounced, 'growth' => $growth]);

        return ['status' => 'completed', 'data' => compact('total', 'active', 'newSubs', 'unsubs', 'bounced', 'growth'), 'message' => $msg];
    }

    protected function generateReport(AiActionPlanStep $step, User $user): array
    {
        $plan = $step->plan;
        $completed = $plan->steps()->where('status', 'completed')->where('id', '!=', $step->id)->get();

        $ctx = $completed->map(fn($s) => "**{$s->title}**:\n" . json_encode($s->result['data'] ?? $s->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->join("\n\n");
        if (empty($ctx)) $ctx = $this->gatherQuickStats($user);

        $langInstruction = $this->getLanguageInstruction(User::find($step->plan->user_id));

        $prompt = "Generate a professional report based on:\n{$ctx}\n\n{$langInstruction}\n\nFormat: 1) Summary 2) Analysis 3) Trends 4) Recommendations. Use emoji.";
        $response = $this->callAi($prompt, ['max_tokens' => 6000, 'temperature' => 0.4]);

        return ['status' => 'completed', 'message' => $response];
    }

    protected function comparePerformance(AiActionPlanStep $step, User $user): array
    {
        $messages = Message::where('user_id', $user->id)->where('status', 'sent')
            ->orderByDesc('updated_at')->limit(5)->get();

        if ($messages->isEmpty()) return ['status' => 'completed', 'message' => __('brain.analytics.no_campaigns')];

        $cmp = __('brain.analytics.compare_header') . "\n\n";
        foreach ($messages as $msg) {
            $opens = EmailOpen::where('message_id', $msg->id)->distinct('subscriber_id')->count('subscriber_id');
            $clicks = EmailClick::where('message_id', $msg->id)->distinct('subscriber_id')->count('subscriber_id');
            $ctor = $opens > 0 ? round(($clicks / $opens) * 100, 1) : 0;
            $cmp .= "📧 **{$msg->name}** ({$msg->updated_at->format('d.m')}): 👁️{$opens} 🖱️{$clicks} CTOR:{$ctor}%\n";
        }

        return ['status' => 'completed', 'message' => $cmp];
    }

    protected function analyzeTrends(AiActionPlanStep $step, User $user): array
    {
        $days = $step->config['days'] ?? 14;
        $half = intdiv($days, 2);

        $recentOpens = EmailOpen::whereHas('message', fn($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', Carbon::now()->subDays($half))->count();
        $prevOpens = EmailOpen::whereHas('message', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [Carbon::now()->subDays($days), Carbon::now()->subDays($half)])->count();

        $recentSubs = Subscriber::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays($half))->count();
        $prevSubs = Subscriber::where('user_id', $user->id)
            ->whereBetween('created_at', [Carbon::now()->subDays($days), Carbon::now()->subDays($half)])->count();

        $opensTrend = $prevOpens > 0 ? round((($recentOpens - $prevOpens) / $prevOpens) * 100, 1) : 0;
        $subsTrend = $prevSubs > 0 ? round((($recentSubs - $prevSubs) / $prevSubs) * 100, 1) : 0;
        $icon = fn($v) => $v > 0 ? '📈' : ($v < 0 ? '📉' : '➡️');

        $msg = __('brain.analytics.trends_header', ['days' => $days]) . "\n{$icon($opensTrend)} " . __('brain.analytics.trends_opens', ['recent' => $recentOpens, 'previous' => $prevOpens, 'pct' => $opensTrend]) . "\n{$icon($subsTrend)} " . __('brain.analytics.trends_subs', ['recent' => $recentSubs, 'previous' => $prevSubs, 'pct' => $subsTrend]);

        return ['status' => 'completed', 'message' => $msg];
    }

    protected function aiUsageReport(AiActionPlanStep $step, User $user): array
    {
        $days = $step->config['days'] ?? 30;
        $since = Carbon::now()->subDays($days);

        $logs = AiExecutionLog::where('user_id', $user->id)->where('created_at', '>=', $since);
        $total = $logs->count();
        $tokensIn = $logs->sum('tokens_input');
        $tokensOut = $logs->sum('tokens_output');
        $success = (clone $logs)->where('status', 'success')->count();
        $errors = (clone $logs)->where('status', 'error')->count();
        $avgMs = round((clone $logs)->avg('duration_ms') ?? 0);

        $byAgent = AiExecutionLog::where('user_id', $user->id)->where('created_at', '>=', $since)
            ->selectRaw('agent_type, COUNT(*) as cnt, SUM(tokens_input+tokens_output) as tkns')
            ->groupBy('agent_type')->get();

        $msg = __('brain.analytics.ai_usage_header', ['days' => $days]) . "\n" . __('brain.analytics.ai_usage_exec', ['total' => $total, 'success' => $success, 'errors' => $errors]) . "\n" . __('brain.analytics.ai_usage_tokens', ['tokens' => number_format($tokensIn + $tokensOut), 'avg_ms' => $avgMs]);
        foreach ($byAgent as $row) {
            $msg .= "\n  • {$row->agent_type}: {$row->cnt}x, " . number_format($row->tkns) . " tkn";
        }

        return ['status' => 'completed', 'message' => $msg];
    }

    protected function gatherQuickStats(User $user): string
    {
        $subs = Subscriber::where('user_id', $user->id)->count();
        $active = Subscriber::where('user_id', $user->id)->active()->count();
        $lists = ContactList::where('user_id', $user->id)->count();
        $sent = Message::where('user_id', $user->id)->where('status', 'sent')->count();
        return __('brain.analytics.quick_stats', ['subs' => $subs, 'active' => $active, 'lists' => $lists, 'sent' => $sent]);
    }
}
