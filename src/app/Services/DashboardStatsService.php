<?php

namespace App\Services;

use App\Models\AiIntegration;
use App\Models\ContactList;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers behind the dashboard: one period (7, 30 or 90 days ending today), the same period
 * before it for comparison, and a value per day for the charts.
 *
 * Days are the user's calendar days: timestamps are stored in the application timezone and
 * shifted by the user's current UTC offset before they are grouped.
 */
class DashboardStatsService
{
    public const PERIODS = [7, 30, 90];

    public const DEFAULT_PERIOD = 30;

    public function forUser(?User $user, int $days = self::DEFAULT_PERIOD): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
        $timezone = $this->userTimezone($user);

        // Period boundaries are midnights in the user's timezone, compared in the stored one
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = $today->subDays($days - 1);
        $previousFrom = $from->subDays($days);
        $until = $today->addDay();

        $stored = fn (CarbonImmutable $moment) => $moment->setTimezone(config('app.timezone'))->toDateTimeString();
        $current = [$stored($from), $stored($until)];
        $previous = [$stored($previousFrom), $stored($from)];
        $offset = $this->offsetMinutes($timezone);

        $subscribersTotal = Subscriber::whereHas('contactLists', function ($q) {
            $q->where('contact_list_subscriber.status', 'active');
        })->count();

        $newSubscribers = $this->countBetween(Subscriber::query(), 'created_at', $current);
        $newSubscribersBefore = $this->countBetween(Subscriber::query(), 'created_at', $previous);

        $sent = $this->sentBetween($current);
        $sentBefore = $this->sentBetween($previous);

        $openRate = $this->rate($this->uniqueEventsBetween(EmailOpen::class, $current), $sent);
        $openRateBefore = $this->rate($this->uniqueEventsBetween(EmailOpen::class, $previous), $sentBefore);
        $clickRate = $this->rate($this->uniqueEventsBetween(EmailClick::class, $current), $sent);
        $clickRateBefore = $this->rate($this->uniqueEventsBetween(EmailClick::class, $previous), $sentBefore);

        return [
            'period' => [
                'days' => $days,
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'timezone' => $timezone,
            ],
            'subscribers' => [
                'total' => $subscribersTotal,
                'formatted' => number_format($subscribersTotal, 0, ',', ' '),
                'new' => $newSubscribers,
                'new_previous' => $newSubscribersBefore,
                'trend' => $this->percentChange($newSubscribers, $newSubscribersBefore),
            ],
            'emails_sent' => [
                'total' => $sent,
                'formatted' => number_format($sent, 0, ',', ' '),
                'previous' => $sentBefore,
                'trend' => $this->percentChange($sent, $sentBefore),
            ],
            // Rate trends are differences in percentage points, not a percentage of a percentage
            'open_rate' => [
                'value' => $openRate,
                'formatted' => $openRate . '%',
                'previous' => $openRateBefore,
                'trend' => round($openRate - $openRateBefore, 1),
            ],
            'click_rate' => [
                'value' => $clickRate,
                'formatted' => $clickRate . '%',
                'previous' => $clickRateBefore,
                'trend' => round($clickRate - $clickRateBefore, 1),
            ],
            'contact_lists_count' => ContactList::count(),
            'ai_integrations_count' => AiIntegration::count(),
            'mailboxes_count' => Mailbox::count(),
            'queue_pending' => MessageQueueEntry::where('status', MessageQueueEntry::STATUS_QUEUED)->count(),
            'activity_chart' => $this->dailySeries($from, $days, $current, $offset),
            'recent_campaigns' => $this->recentCampaigns(),
            'scheduled' => $this->scheduled(),
        ];
    }

    private function userTimezone(?User $user): string
    {
        $timezone = $user?->timezone ?: config('app.timezone');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone');
    }

    /** Minutes to add to a stored timestamp to get the user's wall clock (current offset). */
    private function offsetMinutes(string $timezone): int
    {
        return Carbon::now($timezone)->utcOffset() - Carbon::now(config('app.timezone'))->utcOffset();
    }

    /** SQL for "the user's calendar day of this stored timestamp". The offset is an integer we computed. */
    private function dayExpression(string $column, int $offsetMinutes): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "date({$column}, '{$offsetMinutes} minutes')"
            : "DATE(DATE_ADD({$column}, INTERVAL {$offsetMinutes} MINUTE))";
    }

    private function countBetween($query, string $column, array $range): int
    {
        return $query->where($column, '>=', $range[0])->where($column, '<', $range[1])->count();
    }

    private function sentBetween(array $range): int
    {
        return (int) DB::table('cron_job_logs')
            ->where('started_at', '>=', $range[0])
            ->where('started_at', '<', $range[1])
            ->sum('emails_sent');
    }

    /**
     * Each recipient of a message counts once, however often they opened or clicked it:
     * counting every event gave rates over 100%.
     */
    private function uniqueEventsBetween(string $model, array $range): int
    {
        return DB::query()
            ->fromSub(
                $model::query()
                    ->select('message_id', 'subscriber_id')
                    ->where('created_at', '>=', $range[0])
                    ->where('created_at', '<', $range[1])
                    ->distinct(),
                'unique_events'
            )
            ->count();
    }

    /** Opens in this period of mail sent before it still count, hence the cap. */
    private function rate(int $events, int $sent): float
    {
        return $sent > 0 ? (float) min(100, round(($events / $sent) * 100, 1)) : 0.0;
    }

    private function percentChange(int $now, int $before): float
    {
        if ($before > 0) {
            return round((($now - $before) / $before) * 100, 1);
        }

        return $now > 0 ? 100.0 : 0.0;
    }

    /** One row per day of the period, days without activity included. */
    private function dailySeries(CarbonImmutable $from, int $days, array $range, int $offset): array
    {
        $perDay = function ($query, string $column, string $aggregate) use ($range, $offset) {
            return $query
                ->where($column, '>=', $range[0])
                ->where($column, '<', $range[1])
                ->selectRaw($this->dayExpression($column, $offset) . " as day, {$aggregate} as total")
                ->groupBy('day')
                ->pluck('total', 'day');
        };

        $uniquePerDay = function (string $model) use ($range, $offset) {
            $day = $this->dayExpression('created_at', $offset);

            return DB::query()
                ->fromSub(
                    $model::query()
                        ->selectRaw("{$day} as day, message_id, subscriber_id")
                        ->where('created_at', '>=', $range[0])
                        ->where('created_at', '<', $range[1])
                        ->distinct(),
                    'unique_events'
                )
                ->selectRaw('day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        };

        $emails = $perDay(DB::table('cron_job_logs'), 'started_at', 'COALESCE(SUM(emails_sent), 0)');
        $subscribers = $perDay(Subscriber::query(), 'created_at', 'COUNT(*)');
        $opens = $uniquePerDay(EmailOpen::class);
        $clicks = $uniquePerDay(EmailClick::class);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'emails' => (int) ($emails[$day] ?? 0),
                'subscribers' => (int) ($subscribers[$day] ?? 0),
                'opens' => (int) ($opens[$day] ?? 0),
                'clicks' => (int) ($clicks[$day] ?? 0),
            ];
        }

        return $series;
    }

    private function recentCampaigns(): Collection
    {
        $messages = Message::with('contactLists:id,name')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $ids = $messages->pluck('id');
        $opens = $this->uniqueRecipientsPerMessage(EmailOpen::class, $ids);
        $clicks = $this->uniqueRecipientsPerMessage(EmailClick::class, $ids);

        return $messages->map(function (Message $message) use ($opens, $clicks) {
            $sent = (int) $message->sent_count;
            $messageOpens = (int) ($opens[$message->id] ?? 0);
            $messageClicks = (int) ($clicks[$message->id] ?? 0);

            return [
                'id' => $message->id,
                'name' => $message->subject,
                'channel' => $message->channel ?? 'email',
                'type' => $message->type,
                'status' => $message->status,
                'date' => ($message->scheduled_at ?? $message->created_at)?->toIso8601String(),
                'lists' => $message->contactLists->pluck('name')->values(),
                'sent' => $sent,
                'opens' => $messageOpens,
                'clicks' => $messageClicks,
                'open_rate' => $sent > 0 ? $this->rate($messageOpens, $sent) : null,
                'click_rate' => $sent > 0 ? $this->rate($messageClicks, $sent) : null,
            ];
        });
    }

    private function uniqueRecipientsPerMessage(string $model, Collection $messageIds): Collection
    {
        if ($messageIds->isEmpty()) {
            return collect();
        }

        return $model::query()
            ->whereIn('message_id', $messageIds)
            ->selectRaw('message_id, COUNT(DISTINCT subscriber_id) as total')
            ->groupBy('message_id')
            ->pluck('total', 'message_id');
    }

    /** Broadcasts waiting for their send time, soonest first. */
    private function scheduled(): Collection
    {
        return Message::with('contactLists:id,name')
            ->where('status', 'scheduled')
            ->where('type', 'broadcast')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->limit(4)
            ->get()
            ->map(fn (Message $message) => [
                'id' => $message->id,
                'name' => $message->subject,
                'channel' => $message->channel ?? 'email',
                'scheduled_at' => $message->scheduled_at->toIso8601String(),
                'recipients' => $message->planned_recipients_count,
                'lists' => $message->contactLists->pluck('name')->values(),
            ]);
    }
}
