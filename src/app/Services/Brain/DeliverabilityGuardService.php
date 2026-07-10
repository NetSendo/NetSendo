<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 3 — deliverability circuit breaker.
 *
 * Sender reputation is worth more than any single campaign. Before Brain
 * sends anything, this guard checks the recent send health and trips when:
 * - the rolling bounce rate exceeds the threshold (default 5%)
 * - the complaint rate exceeds the threshold (default 0.1%)
 * - a mailbox domain is listed on a critical blacklist
 *
 * Domain SPF/DKIM/DMARC problems surface as warnings (they degrade rather
 * than immediately burn reputation). Thresholds are user-overridable in
 * Brain preferences.
 */
class DeliverabilityGuardService
{
    public const WINDOW_HOURS = 48;
    public const MIN_SAMPLE = 50; // below this many recent sends, rates are noise
    public const DEFAULT_MAX_BOUNCE_RATE = 5.0;      // %
    public const DEFAULT_MAX_COMPLAINT_RATE = 0.1;   // %

    /**
     * @return array{healthy: bool, violations: string[], warnings: string[], stats: array}
     */
    public function check(User $user): array
    {
        $violations = [];
        $warnings = [];
        $stats = [];

        $prefs = [];
        try {
            $prefs = AiBrainSettings::getForUser($user->id)->preferences ?? [];
        } catch (\Exception $e) {
            // defaults apply
        }

        $maxBounceRate = (float) ($prefs['deliverability_max_bounce_rate'] ?? self::DEFAULT_MAX_BOUNCE_RATE);
        $maxComplaintRate = (float) ($prefs['deliverability_max_complaint_rate'] ?? self::DEFAULT_MAX_COMPLAINT_RATE);
        $since = now()->subHours(self::WINDOW_HOURS);

        // 1. Rolling bounce rate from the send queue
        try {
            $row = MessageQueueEntry::whereHas('message', fn ($q) => $q->where('user_id', $user->id))
                ->where('updated_at', '>=', $since)
                ->selectRaw("
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = 'failed' AND error_message LIKE '%bounce%' THEN 1 ELSE 0 END) as bounces
                ")
                ->first();

            $delivered = (int) ($row->delivered ?? 0);
            $bounces = (int) ($row->bounces ?? 0);
            $sample = $delivered + $bounces;

            $stats['delivered_48h'] = $delivered;
            $stats['bounces_48h'] = $bounces;

            if ($sample >= self::MIN_SAMPLE) {
                $bounceRate = round(($bounces / $sample) * 100, 2);
                $stats['bounce_rate_48h'] = $bounceRate;

                if ($bounceRate > $maxBounceRate) {
                    $violations[] = __('brain.deliverability.bounce_spike', [
                        'rate' => $bounceRate,
                        'max' => $maxBounceRate,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('DeliverabilityGuard: bounce check failed', ['error' => $e->getMessage()]);
        }

        // 2. Complaint rate (subscribers marked complained in the window)
        try {
            $complaints = Subscriber::where('user_id', $user->id)
                ->where('status', 'complained')
                ->where('updated_at', '>=', $since)
                ->count();
            $stats['complaints_48h'] = $complaints;

            $delivered = (int) ($stats['delivered_48h'] ?? 0);
            if ($complaints > 0 && $delivered >= self::MIN_SAMPLE) {
                $complaintRate = round(($complaints / $delivered) * 100, 3);
                $stats['complaint_rate_48h'] = $complaintRate;

                if ($complaintRate > $maxComplaintRate) {
                    $violations[] = __('brain.deliverability.complaint_spike', [
                        'rate' => $complaintRate,
                        'max' => $maxComplaintRate,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('DeliverabilityGuard: complaint check failed', ['error' => $e->getMessage()]);
        }

        // 3. Mailbox blacklist status (persisted by MailboxReputationService)
        try {
            $criticalMailboxes = Mailbox::where('user_id', $user->id)
                ->where('reputation_overall', 'critical')
                ->pluck('from_email');

            if ($criticalMailboxes->isNotEmpty()) {
                $violations[] = __('brain.deliverability.blacklisted', [
                    'mailboxes' => $criticalMailboxes->take(3)->join(', '),
                ]);
            }
        } catch (\Exception $e) {
            // mailbox reputation columns may not exist
        }

        // 4. Domain auth status (SPF/DKIM/DMARC) — warning, not a hard block
        try {
            $criticalDomains = DomainConfiguration::forUser($user->id)
                ->where('overall_status', 'critical')
                ->pluck('domain');

            if ($criticalDomains->isNotEmpty()) {
                $warnings[] = __('brain.deliverability.domain_critical', [
                    'domains' => $criticalDomains->take(3)->join(', '),
                ]);
            }
        } catch (\Exception $e) {
            // domain configuration table may not exist
        }

        return [
            'healthy' => empty($violations),
            'violations' => $violations,
            'warnings' => $warnings,
            'stats' => $stats,
        ];
    }
}
