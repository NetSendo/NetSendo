<?php

namespace App\Services\Lists;

use App\Models\ContactList;
use App\Models\Subscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only view of what is happening on a list: the raw event feed, the
 * aggregate engagement picture, and the timeline for a single contact.
 *
 * Events are assembled from the subscription pivot (signups, confirmations,
 * unsubscribes), the tracking tables (opens, clicks) and the send queue, then
 * merged into one chronological stream.
 */
class ListActivityService
{
    public const EVENT_TYPES = [
        'subscribed', 'resubscribed', 'confirmed', 'unsubscribed', 'bounced', 'sent', 'failed', 'opened', 'clicked',
    ];

    /**
     * Chronological event feed for a list.
     *
     * @param array $options {days: int, limit: int, types: string[]}
     */
    public function feed(ContactList $list, array $options = []): array
    {
        $days = max(1, min((int) ($options['days'] ?? 30), 365));
        $limit = max(1, min((int) ($options['limit'] ?? 50), 500));
        $types = array_values(array_intersect($options['types'] ?? self::EVENT_TYPES, self::EVENT_TYPES));
        $since = now()->subDays($days);

        if (empty($types)) {
            $types = self::EVENT_TYPES;
        }

        $memberIds = $this->memberIdSubquery($list);
        $events = [];

        // --- subscription lifecycle (from the pivot) ------------------------
        $lifecycle = [
            'subscribed' => 'subscribed_at',
            'resubscribed' => 'resubscribed_at',
            'confirmed' => 'confirmed_at',
            'unsubscribed' => 'unsubscribed_at',
        ];

        foreach ($lifecycle as $type => $column) {
            if (!in_array($type, $types, true)) {
                continue;
            }

            $rows = DB::table('contact_list_subscriber as pivot')
                ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
                ->where('pivot.contact_list_id', $list->id)
                ->whereNotNull('pivot.' . $column)
                ->where('pivot.' . $column, '>=', $since)
                ->select([
                    'pivot.subscriber_id',
                    'pivot.' . $column . ' as occurred_at',
                    'pivot.source',
                    'pivot.status',
                    'subscribers.email',
                    'subscribers.first_name',
                    'subscribers.last_name',
                ])
                ->orderByDesc('pivot.' . $column)
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $events[] = [
                    'type' => $type,
                    'occurred_at' => (string) $row->occurred_at,
                    'subscriber_id' => $row->subscriber_id,
                    'email' => $row->email,
                    'name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: null,
                    'detail' => $row->source ? ['source' => $row->source] : null,
                ];
            }
        }

        // --- bounced members (no dedicated timestamp, use pivot update) -----
        if (in_array('bounced', $types, true)) {
            $rows = DB::table('contact_list_subscriber as pivot')
                ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
                ->where('pivot.contact_list_id', $list->id)
                ->where('pivot.status', 'bounced')
                ->where('pivot.updated_at', '>=', $since)
                ->select([
                    'pivot.subscriber_id',
                    'pivot.updated_at as occurred_at',
                    'pivot.soft_bounce_count',
                    'subscribers.email',
                ])
                ->orderByDesc('pivot.updated_at')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $events[] = [
                    'type' => 'bounced',
                    'occurred_at' => (string) $row->occurred_at,
                    'subscriber_id' => $row->subscriber_id,
                    'email' => $row->email,
                    'name' => null,
                    'detail' => ['soft_bounce_count' => (int) $row->soft_bounce_count],
                ];
            }
        }

        // --- opens / clicks -------------------------------------------------
        foreach ([['opened', 'email_opens', 'opened_at'], ['clicked', 'email_clicks', 'clicked_at']] as [$type, $table, $column]) {
            if (!in_array($type, $types, true)) {
                continue;
            }

            $query = DB::table($table)
                ->join('subscribers', 'subscribers.id', '=', $table . '.subscriber_id')
                ->leftJoin('messages', 'messages.id', '=', $table . '.message_id')
                ->whereIn($table . '.subscriber_id', $memberIds)
                ->where($table . '.' . $column, '>=', $since)
                ->select([
                    $table . '.subscriber_id',
                    $table . '.' . $column . ' as occurred_at',
                    $table . '.message_id',
                    'messages.subject',
                    'subscribers.email',
                ])
                ->orderByDesc($table . '.' . $column)
                ->limit($limit);

            if ($type === 'clicked') {
                $query->addSelect($table . '.url');
            }

            foreach ($query->get() as $row) {
                $events[] = [
                    'type' => $type,
                    'occurred_at' => (string) $row->occurred_at,
                    'subscriber_id' => $row->subscriber_id,
                    'email' => $row->email,
                    'name' => null,
                    'detail' => array_filter([
                        'message_id' => $row->message_id,
                        'subject' => $row->subject ?? null,
                        'url' => $row->url ?? null,
                    ]),
                ];
            }
        }

        // --- deliveries -----------------------------------------------------
        foreach ([['sent', 'sent'], ['failed', 'failed']] as [$type, $status]) {
            if (!in_array($type, $types, true)) {
                continue;
            }

            $column = $status === 'sent' ? 'sent_at' : 'updated_at';

            $rows = DB::table('message_queue_entries as q')
                ->join('subscribers', 'subscribers.id', '=', 'q.subscriber_id')
                ->leftJoin('messages', 'messages.id', '=', 'q.message_id')
                ->whereIn('q.subscriber_id', $this->memberIdSubquery($list))
                ->where('q.status', $status)
                ->where('q.' . $column, '>=', $since)
                ->select([
                    'q.subscriber_id',
                    'q.' . $column . ' as occurred_at',
                    'q.message_id',
                    'q.error_message',
                    'messages.subject',
                    'subscribers.email',
                ])
                ->orderByDesc('q.' . $column)
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $events[] = [
                    'type' => $type,
                    'occurred_at' => (string) $row->occurred_at,
                    'subscriber_id' => $row->subscriber_id,
                    'email' => $row->email,
                    'name' => null,
                    'detail' => array_filter([
                        'message_id' => $row->message_id,
                        'subject' => $row->subject ?? null,
                        'error' => $row->error_message ?? null,
                    ]),
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        $counts = [];
        foreach ($events as $event) {
            $counts[$event['type']] = ($counts[$event['type']] ?? 0) + 1;
        }

        return [
            'list' => ['id' => $list->id, 'name' => $list->name],
            'window_days' => $days,
            'since' => $since->toISOString(),
            'event_counts' => $counts,
            'events' => array_slice($events, 0, $limit),
            'note' => count($events) > $limit
                ? 'More events exist in this window; narrow "types" or shorten "days" for full coverage.'
                : null,
        ];
    }

    /**
     * Aggregate engagement and growth for a list.
     */
    public function engagement(ContactList $list, array $options = []): array
    {
        $days = max(1, min((int) ($options['days'] ?? 30), 365));
        $since = now()->subDays($days);
        $memberIds = $this->memberIdSubquery($list);

        $statusCounts = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->groupBy('pivot.status')
            ->select('pivot.status', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'status')
            ->all();

        $members = array_sum($statusCounts);
        $active = (int) ($statusCounts['active'] ?? 0);

        // Growth in the window
        $added = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $list->id)
            ->where('subscribed_at', '>=', $since)
            ->count();

        $lost = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $list->id)
            ->where('unsubscribed_at', '>=', $since)
            ->count();

        // Deliveries and engagement in the window
        $sent = DB::table('message_queue_entries')
            ->whereIn('subscriber_id', $this->memberIdSubquery($list))
            ->where('status', 'sent')
            ->where('sent_at', '>=', $since)
            ->count();

        $opens = DB::table('email_opens')
            ->whereIn('subscriber_id', $this->memberIdSubquery($list))
            ->where('opened_at', '>=', $since);

        $totalOpens = (clone $opens)->count();
        $uniqueOpeners = (clone $opens)->distinct()->count('subscriber_id');

        $clicks = DB::table('email_clicks')
            ->whereIn('subscriber_id', $this->memberIdSubquery($list))
            ->where('clicked_at', '>=', $since);

        $totalClicks = (clone $clicks)->count();
        $uniqueClickers = (clone $clicks)->distinct()->count('subscriber_id');

        return [
            'list' => ['id' => $list->id, 'name' => $list->name, 'type' => $list->type],
            'window_days' => $days,
            'since' => $since->toISOString(),
            'audience' => [
                'members' => $members,
                'active' => $active,
                'unsubscribed' => (int) ($statusCounts['unsubscribed'] ?? 0),
                'bounced' => (int) ($statusCounts['bounced'] ?? 0),
                'by_status' => $statusCounts,
            ],
            'growth' => [
                'added' => $added,
                'lost' => $lost,
                'net' => $added - $lost,
                'churn_rate' => $members > 0 ? round(($lost / $members) * 100, 2) : 0.0,
                'daily' => $this->growthSeries($list, $days),
            ],
            'delivery' => [
                'sent' => $sent,
                'total_opens' => $totalOpens,
                'unique_openers' => $uniqueOpeners,
                'total_clicks' => $totalClicks,
                'unique_clickers' => $uniqueClickers,
                'open_rate' => $sent > 0 ? round(($uniqueOpeners / $sent) * 100, 2) : 0.0,
                'click_rate' => $sent > 0 ? round(($uniqueClickers / $sent) * 100, 2) : 0.0,
                'click_to_open_rate' => $uniqueOpeners > 0 ? round(($uniqueClickers / $uniqueOpeners) * 100, 2) : 0.0,
            ],
            'top_messages' => $this->topMessages($list, $since),
            'top_links' => $this->topLinks($list, $since),
            'most_engaged' => $this->mostEngaged($list),
            'inactive' => $this->inactiveBreakdown($list),
        ];
    }

    /**
     * Per-day signups and unsubscribes over the window.
     */
    private function growthSeries(ContactList $list, int $days): array
    {
        $since = now()->subDays($days)->startOfDay();

        $added = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $list->id)
            ->where('subscribed_at', '>=', $since)
            ->groupBy('day')
            ->select(DB::raw('DATE(subscribed_at) as day'), DB::raw('COUNT(*) as total'))
            ->pluck('total', 'day')
            ->all();

        $lost = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $list->id)
            ->where('unsubscribed_at', '>=', $since)
            ->groupBy('day')
            ->select(DB::raw('DATE(unsubscribed_at) as day'), DB::raw('COUNT(*) as total'))
            ->pluck('total', 'day')
            ->all();

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();

            $series[] = [
                'date' => $day,
                'added' => (int) ($added[$day] ?? 0),
                'lost' => (int) ($lost[$day] ?? 0),
            ];
        }

        return $series;
    }

    private function topMessages(ContactList $list, Carbon $since, int $limit = 5): array
    {
        $rows = DB::table('email_opens')
            ->join('messages', 'messages.id', '=', 'email_opens.message_id')
            ->whereIn('email_opens.subscriber_id', $this->memberIdSubquery($list))
            ->where('email_opens.opened_at', '>=', $since)
            ->groupBy('messages.id', 'messages.subject')
            ->select([
                'messages.id',
                'messages.subject',
                DB::raw('COUNT(*) as opens'),
                DB::raw('COUNT(DISTINCT email_opens.subscriber_id) as unique_openers'),
            ])
            ->orderByDesc('unique_openers')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'message_id' => $r->id,
            'subject' => $r->subject,
            'opens' => (int) $r->opens,
            'unique_openers' => (int) $r->unique_openers,
        ])->all();
    }

    private function topLinks(ContactList $list, Carbon $since, int $limit = 5): array
    {
        $rows = DB::table('email_clicks')
            ->whereIn('subscriber_id', $this->memberIdSubquery($list))
            ->where('clicked_at', '>=', $since)
            ->whereNotNull('url')
            ->groupBy('url')
            ->select([
                'url',
                DB::raw('COUNT(*) as clicks'),
                DB::raw('COUNT(DISTINCT subscriber_id) as unique_clickers'),
            ])
            ->orderByDesc('unique_clickers')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'url' => $r->url,
            'clicks' => (int) $r->clicks,
            'unique_clickers' => (int) $r->unique_clickers,
        ])->all();
    }

    private function mostEngaged(ContactList $list, int $limit = 10): array
    {
        $rows = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->where('pivot.status', 'active')
            ->whereNull('subscribers.deleted_at')
            ->select([
                'subscribers.id',
                'subscribers.email',
                'subscribers.first_name',
                'subscribers.last_name',
                'subscribers.opens_count',
                'subscribers.clicks_count',
                'subscribers.last_opened_at',
                'subscribers.last_clicked_at',
            ])
            ->orderByDesc(DB::raw('(subscribers.clicks_count * 2 + subscribers.opens_count)'))
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'subscriber_id' => $r->id,
            'email' => $r->email,
            'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: null,
            'opens' => (int) $r->opens_count,
            'clicks' => (int) $r->clicks_count,
            'last_opened_at' => $r->last_opened_at,
            'last_clicked_at' => $r->last_clicked_at,
        ])->all();
    }

    /**
     * How much of the active audience has gone quiet.
     */
    private function inactiveBreakdown(ContactList $list): array
    {
        $base = fn () => DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->where('pivot.status', 'active')
            ->whereNull('subscribers.deleted_at');

        $neverEngaged = $base()
            ->where('subscribers.opens_count', 0)
            ->where('subscribers.clicks_count', 0)
            ->count();

        $noActivity90 = $base()
            ->where(function ($q) {
                $q->whereNull('subscribers.last_opened_at')
                    ->orWhere('subscribers.last_opened_at', '<', now()->subDays(90));
            })
            ->where(function ($q) {
                $q->whereNull('subscribers.last_clicked_at')
                    ->orWhere('subscribers.last_clicked_at', '<', now()->subDays(90));
            })
            ->count();

        return [
            'never_engaged' => $neverEngaged,
            'no_activity_90_days' => $noActivity90,
        ];
    }

    /**
     * Full timeline for one contact, scoped to the account.
     */
    public function subscriberActivity(Subscriber $subscriber, array $options = []): array
    {
        $limit = max(1, min((int) ($options['limit'] ?? 50), 200));
        $days = max(1, min((int) ($options['days'] ?? 365), 1095));
        $since = now()->subDays($days);

        $subscriber->loadMissing(['contactLists']);

        // Queried through the relation, not `$subscriber->tags`: the legacy
        // `subscribers.tags` column shadows the relation on attribute access.
        $tags = $subscriber->tags()->get(['tags.id', 'tags.name']);

        $events = [];

        foreach ($subscriber->contactLists as $list) {
            foreach ([
                'subscribed' => $list->pivot->subscribed_at,
                'resubscribed' => $list->pivot->resubscribed_at,
                'confirmed' => $list->pivot->confirmed_at,
                'unsubscribed' => $list->pivot->unsubscribed_at,
            ] as $type => $timestamp) {
                if ($timestamp === null || (string) $timestamp < $since->toDateTimeString()) {
                    continue;
                }

                $events[] = [
                    'type' => $type,
                    'occurred_at' => (string) $timestamp,
                    'detail' => ['list_id' => $list->id, 'list_name' => $list->name],
                ];
            }
        }

        $opens = DB::table('email_opens')
            ->leftJoin('messages', 'messages.id', '=', 'email_opens.message_id')
            ->where('email_opens.subscriber_id', $subscriber->id)
            ->where('email_opens.opened_at', '>=', $since)
            ->orderByDesc('email_opens.opened_at')
            ->limit($limit)
            ->get(['email_opens.opened_at', 'email_opens.message_id', 'messages.subject']);

        foreach ($opens as $row) {
            $events[] = [
                'type' => 'opened',
                'occurred_at' => (string) $row->opened_at,
                'detail' => ['message_id' => $row->message_id, 'subject' => $row->subject],
            ];
        }

        $clicks = DB::table('email_clicks')
            ->leftJoin('messages', 'messages.id', '=', 'email_clicks.message_id')
            ->where('email_clicks.subscriber_id', $subscriber->id)
            ->where('email_clicks.clicked_at', '>=', $since)
            ->orderByDesc('email_clicks.clicked_at')
            ->limit($limit)
            ->get(['email_clicks.clicked_at', 'email_clicks.message_id', 'email_clicks.url', 'messages.subject']);

        foreach ($clicks as $row) {
            $events[] = [
                'type' => 'clicked',
                'occurred_at' => (string) $row->clicked_at,
                'detail' => ['message_id' => $row->message_id, 'subject' => $row->subject, 'url' => $row->url],
            ];
        }

        $sends = DB::table('message_queue_entries as q')
            ->leftJoin('messages', 'messages.id', '=', 'q.message_id')
            ->where('q.subscriber_id', $subscriber->id)
            ->whereIn('q.status', ['sent', 'failed'])
            ->where('q.updated_at', '>=', $since)
            ->orderByDesc('q.updated_at')
            ->limit($limit)
            ->get(['q.status', 'q.sent_at', 'q.updated_at', 'q.message_id', 'q.error_message', 'messages.subject']);

        foreach ($sends as $row) {
            $events[] = [
                'type' => $row->status === 'sent' ? 'sent' : 'failed',
                'occurred_at' => (string) ($row->sent_at ?? $row->updated_at),
                'detail' => array_filter([
                    'message_id' => $row->message_id,
                    'subject' => $row->subject,
                    'error' => $row->error_message,
                ]),
            ];
        }

        usort($events, fn ($a, $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        $pending = DB::table('message_queue_entries')
            ->where('subscriber_id', $subscriber->id)
            ->whereIn('status', ['planned', 'queued'])
            ->count();

        return [
            'subscriber' => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'phone' => $subscriber->phone,
                'name' => trim(($subscriber->first_name ?? '') . ' ' . ($subscriber->last_name ?? '')) ?: null,
                'is_active_global' => (bool) $subscriber->is_active_global,
                'source' => $subscriber->source,
                'language' => $subscriber->language,
                'opens_count' => (int) $subscriber->opens_count,
                'clicks_count' => (int) $subscriber->clicks_count,
                'last_opened_at' => $subscriber->last_opened_at?->toISOString(),
                'last_clicked_at' => $subscriber->last_clicked_at?->toISOString(),
                'created_at' => $subscriber->created_at?->toISOString(),
            ],
            'tags' => $tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
            'lists' => $subscriber->contactLists->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'status' => $l->pivot->status,
                'source' => $l->pivot->source,
                'subscribed_at' => $l->pivot->subscribed_at,
                'confirmed_at' => $l->pivot->confirmed_at,
                'unsubscribed_at' => $l->pivot->unsubscribed_at,
            ])->all(),
            'pending_messages' => $pending,
            'window_days' => $days,
            'events' => array_slice($events, 0, $limit),
        ];
    }

    /**
     * Subquery of subscriber ids on this list — reused instead of pulling ids
     * into PHP, which would break on large lists.
     */
    private function memberIdSubquery(ContactList $list): \Closure
    {
        return function ($query) use ($list) {
            $query->select('subscriber_id')
                ->from('contact_list_subscriber')
                ->where('contact_list_id', $list->id);
        };
    }
}
