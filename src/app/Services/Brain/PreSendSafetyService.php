<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use App\Models\User;
use App\Services\Deliverability\SpamTriggerAnalyzer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 — Pre-Send Safety Pipeline.
 *
 * Every Brain-initiated campaign send must pass these checks before the
 * message enters the real send pipeline. Strategy limits (max_sends_per_week,
 * preferred_send_hours, excluded_days, max_audience_per_campaign) are advisory
 * in prompts elsewhere — here they are enforced in code.
 */
class PreSendSafetyService
{
    public function __construct(
        protected SpamTriggerAnalyzer $spamAnalyzer,
        protected DeliverabilityGuardService $deliverabilityGuard,
    ) {}

    /**
     * Run all blocking pre-send checks.
     *
     * @return array{allowed: bool, violations: string[], warnings: string[]}
     */
    public function check(User $user, Message $message, int $recipientsCount): array
    {
        $strategy = AiBrainSettings::getForUser($user->id)->getStrategyForAgent('campaign');
        $violations = [];
        $warnings = [];

        // 0. Deliverability circuit breaker — reputation beats any single send
        $guard = $this->deliverabilityGuard->check($user);
        foreach ($guard['violations'] as $violation) {
            $violations[] = $violation;
        }
        foreach ($guard['warnings'] as $warning) {
            $warnings[] = $warning;
        }

        // 1. Spam-trigger analysis — block on critical (high severity) issues
        if (($message->channel ?? 'email') === 'email') {
            try {
                $analysis = $this->spamAnalyzer->analyze($message->subject ?? '', $message->content ?? '');
                $critical = $analysis->getCriticalIssues();

                if (!empty($critical)) {
                    $labels = collect($critical)
                        ->map(fn ($issue) => $issue['word'] ?? $issue['code'] ?? 'issue')
                        ->unique()
                        ->take(5)
                        ->join(', ');
                    $violations[] = __('brain.campaign.safety_spam', ['issues' => $labels]);
                }
            } catch (\Exception $e) {
                Log::warning('Brain pre-send spam analysis failed', ['error' => $e->getMessage()]);
                $warnings[] = __('brain.campaign.safety_spam_check_failed');
            }
        }

        // 2. Weekly send limit (broadcasts scheduled/sent this week)
        $maxPerWeek = (int) ($strategy['max_sends_per_week'] ?? 5);
        if ($maxPerWeek > 0) {
            $sentThisWeek = Message::where('user_id', $user->id)
                ->where('type', 'broadcast')
                ->whereIn('status', ['scheduled', 'sent'])
                ->where('id', '!=', $message->id)
                ->whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();

            if ($sentThisWeek >= $maxPerWeek) {
                $violations[] = __('brain.campaign.safety_weekly_limit', [
                    'sent' => $sentThisWeek,
                    'max' => $maxPerWeek,
                ]);
            }
        }

        // 3. Per-campaign audience cap
        $audienceCap = (int) ($strategy['max_audience_per_campaign'] ?? 0);
        if ($audienceCap > 0 && $recipientsCount > $audienceCap) {
            $violations[] = __('brain.campaign.safety_audience_cap', [
                'count' => $recipientsCount,
                'max' => $audienceCap,
            ]);
        }

        return [
            'allowed' => empty($violations),
            'violations' => $violations,
            'warnings' => $warnings,
        ];
    }

    /**
     * Move a requested send time into the user's allowed send window
     * (preferred_send_hours + excluded_days). Returns the adjusted time —
     * unchanged if it already falls inside the window.
     */
    public function adjustToSendWindow(User $user, Carbon $sendAt): Carbon
    {
        $strategy = AiBrainSettings::getForUser($user->id)->getStrategyForAgent('campaign');

        $excludedDays = array_map('strtolower', (array) ($strategy['excluded_days'] ?? []));
        $hours = $strategy['preferred_send_hours'] ?? [];
        $startHour = max(0, min(23, (int) ($hours['start'] ?? 9)));
        $endHour = max($startHour + 1, min(24, (int) ($hours['end'] ?? 17)));

        $adjusted = $sendAt->copy();

        // Bounded loop: at most 8 day-shifts (covers any excluded-days config)
        for ($i = 0; $i < 8; $i++) {
            if ($adjusted->hour < $startHour) {
                $adjusted->setTime($startHour, 0);
            } elseif ($adjusted->hour >= $endHour) {
                $adjusted->addDay()->setTime($startHour, 0);
            }

            if (in_array(strtolower($adjusted->englishDayOfWeek), $excludedDays, true)) {
                $adjusted->addDay()->setTime($startHour, 0);
                continue;
            }

            break;
        }

        return $adjusted;
    }

    /**
     * Skip planned queue entries whose subscriber e-mail is on the user's
     * suppression list. The platform send pipeline does NOT consult the
     * suppression list, so Brain enforces it here.
     *
     * @return int Number of entries skipped
     */
    public function applySuppression(Message $message): int
    {
        $userId = $message->user_id;

        $suppressedEmails = SuppressionList::where('user_id', $userId)->pluck('email');
        if ($suppressedEmails->isEmpty()) {
            return 0;
        }

        $suppressedSubscriberIds = Subscriber::where('user_id', $userId)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), $suppressedEmails->all())
            ->pluck('id');

        if ($suppressedSubscriberIds->isEmpty()) {
            return 0;
        }

        $skipped = 0;
        $message->queueEntries()
            ->whereIn('subscriber_id', $suppressedSubscriberIds)
            ->where('status', \App\Models\MessageQueueEntry::STATUS_PLANNED)
            ->each(function ($entry) use (&$skipped) {
                $entry->markAsSkipped('suppression_list');
                $skipped++;
            });

        if ($skipped > 0) {
            Log::info('Brain pre-send: suppressed recipients skipped', [
                'message_id' => $message->id,
                'skipped' => $skipped,
            ]);
        }

        return $skipped;
    }
}
