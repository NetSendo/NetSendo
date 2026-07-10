<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\User;
use App\Services\Brain\DeliverabilityGuardService;
use App\Services\Deliverability\InboxPassportService;
use App\Services\Deliverability\SpamTriggerAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 3 — Deliverability Agent (read-only).
 *
 * Watches the asset every revenue playbook depends on: sender reputation.
 * Reports domain auth (SPF/DKIM/DMARC), mailbox blacklist status, recent
 * bounce/complaint health, and can spam-check a draft before sending.
 */
class DeliverabilityAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'deliverability';
    }

    public function getLabel(): string
    {
        return __('brain.deliverability.label');
    }

    public function getCapabilities(): array
    {
        return [
            'deliverability_status',
            'bounce_health',
            'spam_check_message',
            'inbox_placement_estimate',
        ];
    }

    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);
        $langInstruction = $this->getLanguageInstruction($user);

        $actionsBlock = \App\Services\Brain\Tools\ToolRegistry::promptSection('deliverability');

        $prompt = <<<PROMPT
You are an email deliverability expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}
{$knowledgeContext}

{$langInstruction}

Create a deliverability check plan in JSON:
{"title":"","description":"","steps":[{"action_type":"","title":"","description":"","config":{}}]}

{$actionsBlock}

Deliverability agent is read-only — never modifies data.
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.3], $user, 'analytics');
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'deliverability_check',
                $data['title'] ?? __('brain.deliverability.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('DeliverabilityAgent plan failed', ['error' => $e->getMessage()]);
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
            'message' => implode("\n\n---\n\n", $messages) ?: __('brain.deliverability.done'),
        ];
    }

    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $statusReport = $this->buildStatusContext($user);
        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = "You are an email deliverability expert. Current sender health:\n{$statusReport}\n"
            . "Question: {$intent['intent']}\n{$knowledgeContext}\n\n{$langInstruction}\n\n"
            . "Provide analysis and concrete steps to improve inbox placement. Use emoji.";

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.5], $user, 'analytics');

        return ['type' => 'advice', 'message' => $response];
    }

    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'deliverability_status' => $this->deliverabilityStatus($step, $user),
            'bounce_health' => $this->bounceHealth($step, $user),
            'spam_check_message' => $this->spamCheckMessage($step, $user),
            default => $this->failUnknownAction($step),
        };
    }

    // === Step Executors ===

    protected function deliverabilityStatus(AiActionPlanStep $step, User $user): array
    {
        $msg = __('brain.deliverability.status_header') . "\n";
        $data = ['domains' => [], 'mailboxes' => []];

        try {
            $domains = DomainConfiguration::forUser($user->id)->get();
            if ($domains->isEmpty()) {
                $msg .= __('brain.deliverability.no_domains') . "\n";
            }
            foreach ($domains as $domain) {
                $icon = match ($domain->overall_status) {
                    'safe' => '🟢',
                    'warning' => '🟡',
                    'critical' => '🔴',
                    default => '⚪',
                };
                $msg .= "{$icon} {$domain->domain}: SPF {$domain->spf_status} | DKIM {$domain->dkim_status} | DMARC {$domain->dmarc_status}\n";
                $data['domains'][] = [
                    'domain' => $domain->domain,
                    'overall' => $domain->overall_status,
                    'spf' => $domain->spf_status,
                    'dkim' => $domain->dkim_status,
                    'dmarc' => $domain->dmarc_status,
                ];
            }
        } catch (\Exception $e) {
            $msg .= __('brain.deliverability.no_domains') . "\n";
        }

        try {
            $mailboxes = Mailbox::where('user_id', $user->id)->get();
            foreach ($mailboxes as $mailbox) {
                $rep = $mailbox->reputation_overall ?? 'unchecked';
                $icon = match ($rep) {
                    'clean' => '🟢',
                    'warning' => '🟡',
                    'critical' => '🔴',
                    default => '⚪',
                };
                $msg .= "{$icon} 📬 {$mailbox->from_email}: " . __('brain.deliverability.reputation') . " {$rep}\n";
                $data['mailboxes'][] = ['from_email' => $mailbox->from_email, 'reputation' => $rep];
            }
        } catch (\Exception $e) {
            // mailboxes table variations
        }

        return ['status' => 'completed', 'data' => $data, 'message' => $msg];
    }

    protected function bounceHealth(AiActionPlanStep $step, User $user): array
    {
        $guard = app(DeliverabilityGuardService::class)->check($user);
        $stats = $guard['stats'];

        $msg = __('brain.deliverability.health_header') . "\n";
        $msg .= ($guard['healthy'] ? '🟢 ' . __('brain.deliverability.healthy') : '🔴 ' . __('brain.deliverability.unhealthy')) . "\n";
        $msg .= "📤 " . __('brain.deliverability.delivered_48h', ['count' => $stats['delivered_48h'] ?? 0]) . "\n";
        $msg .= "⛔ " . __('brain.deliverability.bounces_48h', [
            'count' => $stats['bounces_48h'] ?? 0,
            'rate' => $stats['bounce_rate_48h'] ?? '—',
        ]) . "\n";

        if (($stats['complaints_48h'] ?? 0) > 0) {
            $msg .= "🚨 " . __('brain.deliverability.complaints_48h', ['count' => $stats['complaints_48h']]) . "\n";
        }
        foreach ($guard['violations'] as $violation) {
            $msg .= "  🛑 {$violation}\n";
        }
        foreach ($guard['warnings'] as $warning) {
            $msg .= "  ⚠️ {$warning}\n";
        }

        return ['status' => 'completed', 'data' => $guard, 'message' => $msg];
    }

    protected function spamCheckMessage(AiActionPlanStep $step, User $user): array
    {
        $messageId = $step->config['message_id'] ?? null;

        $message = $messageId
            ? Message::where('user_id', $user->id)->find($messageId)
            : Message::where('user_id', $user->id)->where('status', 'draft')->latest()->first();

        if (!$message) {
            return ['status' => 'failed', 'message' => __('brain.deliverability.no_message')];
        }

        $analysis = app(SpamTriggerAnalyzer::class)->analyze($message->subject ?? '', $message->content ?? '');
        $critical = $analysis->getCriticalIssues();
        $allIssues = $analysis->getAllIssues();

        // Cheap headless inbox-placement heuristic (content only, no persistence)
        $placement = null;
        try {
            $placement = app(InboxPassportService::class)->analyzeContentOnly($message->subject ?? '', $message->content ?? '');
        } catch (\Exception $e) {
            // optional signal
        }

        $msg = __('brain.deliverability.spam_check_header', ['subject' => mb_substr($message->subject ?? '', 0, 60)]) . "\n";
        $msg .= "📊 " . __('brain.deliverability.content_score', ['score' => $analysis->contentScore]) . "\n";

        if ($placement) {
            $msg .= "📥 " . __('brain.deliverability.predicted_folder', ['folder' => $placement['predicted_folder'] ?? '?']) . "\n";
        }

        if (empty($allIssues)) {
            $msg .= "🟢 " . __('brain.deliverability.no_issues');
        } else {
            $msg .= (empty($critical) ? '🟡 ' : '🔴 ') . __('brain.deliverability.issues_found', [
                'total' => count($allIssues),
                'critical' => count($critical),
            ]) . "\n";
            foreach (array_slice($allIssues, 0, 8) as $issue) {
                $label = $issue['word'] ?? $issue['code'] ?? 'issue';
                $msg .= "  • [{$issue['severity']}] {$label}\n";
            }
        }

        return [
            'status' => 'completed',
            'data' => [
                'message_id' => $message->id,
                'content_score' => $analysis->contentScore,
                'critical_count' => count($critical),
                'issue_count' => count($allIssues),
                'predicted_folder' => $placement['predicted_folder'] ?? null,
            ],
            'message' => $msg,
        ];
    }

    /**
     * Compact status string for advise() prompts.
     */
    protected function buildStatusContext(User $user): string
    {
        $parts = [];

        try {
            $guard = app(DeliverabilityGuardService::class)->check($user);
            $parts[] = 'Send health: ' . ($guard['healthy'] ? 'OK' : 'DEGRADED (' . implode('; ', $guard['violations']) . ')');
            $parts[] = 'Stats 48h: ' . json_encode($guard['stats']);
        } catch (\Exception $e) {
            // ignore
        }

        try {
            $domains = DomainConfiguration::forUser($user->id)
                ->get()
                ->map(fn ($d) => "{$d->domain}={$d->overall_status}")
                ->join(', ');
            $parts[] = 'Domains: ' . ($domains ?: 'none configured');
        } catch (\Exception $e) {
            // ignore
        }

        return implode("\n", $parts) ?: 'No deliverability data.';
    }
}
