<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\EmailClick;
use App\Models\FunnelGoalConversion;
use App\Models\PolarTransaction;
use App\Models\RevenueEvent;
use App\Models\StripeTransaction;
use App\Models\Subscriber;
use App\Models\TpayTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 2 — revenue unification + attribution.
 *
 * syncForUser() incrementally imports completed payments from the platform's
 * revenue silos into revenue_events, then attributes each event to the
 * campaign (last e-mail click within the attribution window) or funnel
 * that produced it.
 *
 * Sources intentionally NOT imported (to avoid double counting):
 * - pixel purchase events (analytics-grade, mirror gateway payments)
 * - affiliate conversions (mirror gateway payments via entity_type)
 */
class RevenueEventService
{
    /** Default last-click attribution window in days. */
    public const DEFAULT_ATTRIBUTION_WINDOW_DAYS = 7;

    /**
     * Import new revenue from all sources for a user, then attribute it.
     *
     * @return array{imported: int, attributed: int}
     */
    public function syncForUser(User $user): array
    {
        $imported = 0;
        $imported += $this->syncGateway($user, StripeTransaction::class, RevenueEvent::SOURCE_STRIPE);
        $imported += $this->syncGateway($user, PolarTransaction::class, RevenueEvent::SOURCE_POLAR);
        $imported += $this->syncGateway($user, TpayTransaction::class, RevenueEvent::SOURCE_TPAY);
        $imported += $this->syncFunnelGoals($user);

        $attributed = $this->attributePending($user);

        if ($imported > 0 || $attributed > 0) {
            Log::info('RevenueEventService: sync complete', [
                'user_id' => $user->id,
                'imported' => $imported,
                'attributed' => $attributed,
            ]);
        }

        return ['imported' => $imported, 'attributed' => $attributed];
    }

    /**
     * Incrementally import completed transactions from a gateway table.
     * Gateways store MINOR units already; source_id = numeric row id.
     */
    protected function syncGateway(User $user, string $modelClass, string $source): int
    {
        try {
            $lastImportedId = (int) RevenueEvent::forUser($user->id)
                ->where('source', $source)
                ->max('source_id');

            $rows = $modelClass::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where('id', '>', $lastImportedId)
                ->orderBy('id')
                ->limit(500)
                ->get();

            $count = 0;
            foreach ($rows as $row) {
                RevenueEvent::firstOrCreate(
                    ['source' => $source, 'source_id' => (string) $row->id],
                    [
                        'user_id' => $user->id,
                        'subscriber_id' => $row->subscriber_id,
                        'customer_email' => $row->customer_email ? strtolower($row->customer_email) : null,
                        'amount' => (int) $row->amount,
                        'currency' => strtoupper($row->currency ?? 'PLN'),
                        'occurred_at' => $row->created_at,
                        'metadata' => ['gateway_status' => $row->status],
                    ]
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("RevenueEventService: {$source} sync failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Import purchase-type funnel goal conversions (value in MAJOR units).
     * These carry their own funnel attribution.
     */
    protected function syncFunnelGoals(User $user): int
    {
        try {
            $lastImportedId = (int) RevenueEvent::forUser($user->id)
                ->where('source', RevenueEvent::SOURCE_FUNNEL_GOAL)
                ->max('source_id');

            $conversions = FunnelGoalConversion::where('goal_type', 'purchase')
                ->where('value', '>', 0)
                ->where('id', '>', $lastImportedId)
                ->whereHas('funnel', fn ($q) => $q->where('user_id', $user->id))
                ->orderBy('id')
                ->limit(500)
                ->get();

            $count = 0;
            foreach ($conversions as $conversion) {
                RevenueEvent::firstOrCreate(
                    ['source' => RevenueEvent::SOURCE_FUNNEL_GOAL, 'source_id' => (string) $conversion->id],
                    [
                        'user_id' => $user->id,
                        'subscriber_id' => $conversion->subscriber_id,
                        'customer_email' => $conversion->subscriber?->email
                            ? strtolower($conversion->subscriber->email)
                            : null,
                        'amount' => (int) round(((float) $conversion->value) * 100),
                        'currency' => 'PLN',
                        'occurred_at' => $conversion->converted_at ?? $conversion->created_at,
                        'attributed_funnel_id' => $conversion->funnel_id,
                        'attribution_type' => RevenueEvent::ATTRIBUTION_FUNNEL,
                        'attributed_at' => now(),
                        'metadata' => ['goal_type' => $conversion->goal_type, 'funnel_step_id' => $conversion->funnel_step_id],
                    ]
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning('RevenueEventService: funnel goal sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Record a revenue event directly at webhook intake (WooCommerce /
     * generic purchase webhook). Idempotent per (source, source_id).
     */
    public function recordWebhookRevenue(
        User $user,
        string $source,
        string $externalOrderId,
        float $amountMajor,
        string $currency,
        ?string $customerEmail = null,
        ?int $subscriberId = null,
        array $metadata = [],
    ): ?RevenueEvent {
        if ($amountMajor <= 0) {
            return null;
        }

        try {
            $event = RevenueEvent::firstOrCreate(
                ['source' => $source, 'source_id' => "{$user->id}:{$externalOrderId}"],
                [
                    'user_id' => $user->id,
                    'subscriber_id' => $subscriberId,
                    'customer_email' => $customerEmail ? strtolower($customerEmail) : null,
                    'amount' => (int) round($amountMajor * 100),
                    'currency' => strtoupper($currency ?: 'PLN'),
                    'occurred_at' => now(),
                    'metadata' => $metadata,
                ]
            );

            $this->attribute($event);

            return $event;
        } catch (\Exception $e) {
            Log::warning('RevenueEventService: webhook revenue record failed', [
                'user_id' => $user->id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Attribute all not-yet-attributed events for a user.
     */
    public function attributePending(User $user): int
    {
        $events = RevenueEvent::forUser($user->id)
            ->unattributed()
            ->orderBy('id')
            ->limit(500)
            ->get();

        $attributed = 0;
        foreach ($events as $event) {
            if ($this->attribute($event)) {
                $attributed++;
            }
        }

        return $attributed;
    }

    /**
     * Attribute one event: last e-mail click by the same subscriber within
     * the attribution window before the purchase wins the credit.
     *
     * @return bool true when attributed to a message
     */
    public function attribute(RevenueEvent $event): bool
    {
        if ($event->attribution_type !== null) {
            return $event->attributed_message_id !== null;
        }

        // Backfill subscriber by e-mail if missing
        if (!$event->subscriber_id && $event->customer_email) {
            $subscriberId = Subscriber::where('user_id', $event->user_id)
                ->whereRaw('LOWER(email) = ?', [$event->customer_email])
                ->value('id');
            if ($subscriberId) {
                $event->subscriber_id = $subscriberId;
            }
        }

        if (!$event->subscriber_id) {
            $event->fill(['attribution_type' => RevenueEvent::ATTRIBUTION_NONE, 'attributed_at' => now()])->save();
            return false;
        }

        $windowDays = $this->attributionWindowDays($event->user_id);

        $lastClick = EmailClick::where('subscriber_id', $event->subscriber_id)
            ->whereHas('message', fn ($q) => $q->where('user_id', $event->user_id))
            ->where('clicked_at', '<=', $event->occurred_at)
            ->where('clicked_at', '>=', $event->occurred_at->copy()->subDays($windowDays))
            ->orderByDesc('clicked_at')
            ->first();

        if ($lastClick) {
            $event->fill([
                'attributed_message_id' => $lastClick->message_id,
                'attribution_type' => RevenueEvent::ATTRIBUTION_LAST_CLICK,
                'attributed_at' => now(),
            ])->save();
            return true;
        }

        $event->fill(['attribution_type' => RevenueEvent::ATTRIBUTION_NONE, 'attributed_at' => now()])->save();
        return false;
    }

    /**
     * Attribution window in days (user-overridable in Brain preferences).
     */
    public function attributionWindowDays(int $userId): int
    {
        try {
            $settings = AiBrainSettings::getForUser($userId);
            $days = (int) (($settings->preferences ?? [])['attribution_window_days'] ?? 0);
            return $days > 0 ? min(90, $days) : self::DEFAULT_ATTRIBUTION_WINDOW_DAYS;
        } catch (\Exception $e) {
            return self::DEFAULT_ATTRIBUTION_WINDOW_DAYS;
        }
    }
}
