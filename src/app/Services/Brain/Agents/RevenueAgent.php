<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\RevenueEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 2 — Revenue Agent (read-only).
 *
 * Answers "how much money did we make and which campaigns made it" from the
 * unified revenue_events ledger: totals, per-campaign attribution with RPM
 * (revenue per 1000 delivered), and subscriber lifetime value.
 */
class RevenueAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'revenue';
    }

    public function getLabel(): string
    {
        return __('brain.revenue.label');
    }

    public function getCapabilities(): array
    {
        return [
            'revenue_summary',
            'campaign_revenue_attribution',
            'rpm_analysis',
            'subscriber_ltv',
        ];
    }

    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);
        $langInstruction = $this->getLanguageInstruction($user);

        $actionsBlock = \App\Services\Brain\Tools\ToolRegistry::promptSection('revenue');

        $prompt = <<<PROMPT
You are a marketing revenue analyst. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}
{$knowledgeContext}

{$langInstruction}

Create an analysis plan in JSON:
{"title":"","description":"","steps":[{"action_type":"","title":"","description":"","config":{}}]}

{$actionsBlock}

Revenue agent is read-only — never modifies data.
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.3], $user, 'analytics');
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'revenue_analysis',
                $data['title'] ?? __('brain.revenue.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('RevenueAgent plan failed', ['error' => $e->getMessage()]);
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
                if (!empty($result['message'])) {
                    $messages[] = $result['message'];
                }
            } catch (\Exception $e) {
                $messages[] = "⚠️ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => implode("\n\n---\n\n", $messages) ?: __('brain.revenue.done'),
        ];
    }

    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $summary = $this->buildRevenueContext($user, 30);
        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = "You are a marketing revenue analyst. Revenue data (last 30 days):\n{$summary}\n"
            . "Question: {$intent['intent']}\n{$knowledgeContext}\n\n{$langInstruction}\n\n"
            . "Provide analysis with numbers and concrete recommendations to increase e-mail marketing revenue. Use emoji.";

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.5], $user, 'analytics');

        return ['type' => 'advice', 'message' => $response];
    }

    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'revenue_summary' => $this->revenueSummary($step, $user),
            'campaign_revenue' => $this->campaignRevenue($step, $user),
            'subscriber_ltv' => $this->subscriberLtv($step, $user),
            default => $this->failUnknownAction($step),
        };
    }

    // === Step Executors ===

    protected function revenueSummary(AiActionPlanStep $step, User $user): array
    {
        $days = (int) ($step->config['days'] ?? 30);
        $events = RevenueEvent::forUser($user->id)->recent($days);

        $totalCount = (clone $events)->count();
        $byCurrency = (clone $events)
            ->select('currency', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('currency')->get();
        $bySource = (clone $events)
            ->select('source', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('source')->orderByDesc('total')->get();
        $attributedTotal = (clone $events)->attributed()->sum('amount');

        if ($totalCount === 0) {
            return ['status' => 'completed', 'data' => ['count' => 0], 'message' => __('brain.revenue.no_events', ['days' => $days])];
        }

        $msg = __('brain.revenue.summary_header', ['days' => $days]) . "\n";
        foreach ($byCurrency as $row) {
            $msg .= "💰 " . number_format($row->total / 100, 2) . " {$row->currency} ({$row->cnt} tx)\n";
        }
        $msg .= "🎯 " . __('brain.revenue.attributed', ['amount' => number_format($attributedTotal / 100, 2)]) . "\n";
        $msg .= __('brain.revenue.by_source') . "\n";
        foreach ($bySource as $row) {
            $msg .= "  • {$row->source}: " . number_format($row->total / 100, 2) . " ({$row->cnt})\n";
        }

        return [
            'status' => 'completed',
            'data' => [
                'days' => $days,
                'count' => $totalCount,
                'by_currency' => $byCurrency->toArray(),
                'by_source' => $bySource->toArray(),
                'attributed_minor' => (int) $attributedTotal,
            ],
            'message' => $msg,
        ];
    }

    protected function campaignRevenue(AiActionPlanStep $step, User $user): array
    {
        $days = (int) ($step->config['days'] ?? 30);
        $limit = min(20, (int) ($step->config['limit'] ?? 10));

        $rows = RevenueEvent::forUser($user->id)
            ->recent($days)
            ->attributed()
            ->select('attributed_message_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('attributed_message_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return ['status' => 'completed', 'data' => [], 'message' => __('brain.revenue.no_attributed', ['days' => $days])];
        }

        $msg = __('brain.revenue.campaign_header', ['days' => $days]) . "\n";
        $data = [];

        foreach ($rows as $row) {
            $message = Message::find($row->attributed_message_id);
            $delivered = $message
                ? $message->queueEntries()->where('status', MessageQueueEntry::STATUS_SENT)->count()
                : 0;
            $rpm = $delivered > 0 ? round(($row->total / 100) / $delivered * 1000, 2) : null;

            $subject = $message?->subject ?? "#{$row->attributed_message_id}";
            $msg .= "📧 \"{$subject}\": " . number_format($row->total / 100, 2)
                . " ({$row->cnt} tx, 📤 {$delivered}"
                . ($rpm !== null ? ", RPM: {$rpm}" : '') . ")\n";

            $data[] = [
                'message_id' => $row->attributed_message_id,
                'subject' => $subject,
                'revenue_minor' => (int) $row->total,
                'transactions' => (int) $row->cnt,
                'delivered' => $delivered,
                'rpm' => $rpm,
            ];
        }

        return ['status' => 'completed', 'data' => $data, 'message' => $msg];
    }

    protected function subscriberLtv(AiActionPlanStep $step, User $user): array
    {
        $limit = min(20, (int) ($step->config['limit'] ?? 10));

        $rows = RevenueEvent::forUser($user->id)
            ->whereNotNull('subscriber_id')
            ->select('subscriber_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('subscriber_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return ['status' => 'completed', 'data' => [], 'message' => __('brain.revenue.no_ltv')];
        }

        $msg = __('brain.revenue.ltv_header', ['count' => $rows->count()]) . "\n";
        foreach ($rows as $row) {
            $email = \App\Models\Subscriber::find($row->subscriber_id)?->email ?? "#{$row->subscriber_id}";
            $msg .= "👤 {$email}: " . number_format($row->total / 100, 2) . " ({$row->cnt} tx)\n";
        }

        return ['status' => 'completed', 'data' => $rows->toArray(), 'message' => $msg];
    }

    /**
     * Compact revenue context string for advise() prompts.
     */
    protected function buildRevenueContext(User $user, int $days): string
    {
        try {
            $total = RevenueEvent::forUser($user->id)->recent($days)->sum('amount');
            $count = RevenueEvent::forUser($user->id)->recent($days)->count();
            $attributed = RevenueEvent::forUser($user->id)->recent($days)->attributed()->sum('amount');

            return "Total: " . number_format($total / 100, 2) . " ({$count} transactions), "
                . "attributed to campaigns: " . number_format($attributed / 100, 2);
        } catch (\Exception $e) {
            return 'No revenue data available.';
        }
    }
}
