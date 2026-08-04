<?php

namespace App\Services\Lists;

use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * List hygiene: find what is hurting deliverability on a list, then act on it.
 *
 * Everything here is read-first — analyze() never writes, and clean() defaults
 * to a dry run — because the destructive variants (delete, suppress) cannot be
 * undone by the caller and an agent should always be able to look before it
 * leaps.
 */
class ListHygieneService
{
    /**
     * Issue categories, in the order they are reported.
     */
    public const CATEGORIES = [
        'missing_contact',
        'invalid_syntax',
        'typo_domain',
        'disposable_domain',
        'role_address',
        'duplicate',
        'hard_bounced',
        'soft_bounce_risk',
        'unsubscribed',
        'unconfirmed',
        'suppressed',
        'globally_inactive',
        'never_engaged',
        'dormant',
    ];

    /**
     * Actions clean() can apply to matched members.
     */
    public const ACTIONS = ['unsubscribe', 'remove', 'delete', 'tag', 'suppress'];

    /** Upper bound on rows examined in one call. */
    public const MAX_SCAN = 50000;

    /** Weight of each category when computing the 0–100 health score. */
    private const SCORE_WEIGHTS = [
        'invalid_syntax' => 25,
        'hard_bounced' => 25,
        'typo_domain' => 15,
        'disposable_domain' => 15,
        'soft_bounce_risk' => 12,
        'suppressed' => 20,
        'duplicate' => 10,
        'never_engaged' => 10,
        'dormant' => 8,
        'unconfirmed' => 8,
        'role_address' => 5,
        'missing_contact' => 10,
        'globally_inactive' => 5,
        'unsubscribed' => 0, // expected on any healthy list, not a defect
    ];

    public function __construct(
        protected EmailValidator $validator,
    ) {}

    // ========================================================================
    // Analysis
    // ========================================================================

    /**
     * Full hygiene report for a list.
     *
     * @param array $options {
     *     unconfirmed_after_days: int (default 14),
     *     never_engaged_after_days: int (default 90),
     *     dormant_after_days: int (default 180),
     *     soft_bounce_threshold: ?int (default: list setting or 3),
     *     sample_size: int (default 5),
     *     max_scan: int
     * }
     */
    public function analyze(ContactList $list, array $options = []): array
    {
        $scan = $this->scan($list, $options);
        $sampleSize = max(0, min((int) ($options['sample_size'] ?? 5), 50));

        $issues = [];
        foreach (self::CATEGORIES as $category) {
            $matches = $scan['matches'][$category] ?? [];

            $issues[$category] = [
                'count' => count($matches),
                'sample' => array_map(
                    fn ($m) => array_intersect_key($m, array_flip(['subscriber_id', 'email', 'phone', 'status', 'detail'])),
                    array_slice($matches, 0, $sampleSize)
                ),
            ];
        }

        $totals = $scan['totals'];
        $active = max(1, $totals['active']);

        return [
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'type' => $list->type,
                'double_opt_in' => $scan['double_opt_in'],
            ],
            'scanned' => $scan['scanned'],
            'truncated' => $scan['truncated'],
            'totals' => $totals,
            'engagement' => [
                'engaged' => $totals['active'] - count($scan['matches']['never_engaged'] ?? []) - count($scan['matches']['dormant'] ?? []),
                'never_engaged' => count($scan['matches']['never_engaged'] ?? []),
                'dormant' => count($scan['matches']['dormant'] ?? []),
                'engagement_rate' => round((($totals['active'] - count($scan['matches']['never_engaged'] ?? []) - count($scan['matches']['dormant'] ?? [])) / $active) * 100, 2),
            ],
            'issues' => $issues,
            'health_score' => $this->healthScore($issues, $totals),
            'thresholds' => $scan['thresholds'],
            'recommendations' => $this->recommendations($issues, $totals),
        ];
    }

    /**
     * Single pass over the list's members, classifying each row into every
     * category it belongs to. One scan feeds analyze(), clean() and verify().
     *
     * @return array{matches: array<string, array>, totals: array, scanned: int, truncated: bool, thresholds: array, double_opt_in: bool}
     */
    public function scan(ContactList $list, array $options = []): array
    {
        $maxScan = max(1, min((int) ($options['max_scan'] ?? self::MAX_SCAN), self::MAX_SCAN));
        $settings = $list->settings ?? [];

        $thresholds = [
            'unconfirmed_after_days' => (int) ($options['unconfirmed_after_days'] ?? 14),
            'never_engaged_after_days' => (int) ($options['never_engaged_after_days'] ?? 90),
            'dormant_after_days' => (int) ($options['dormant_after_days'] ?? 180),
            'soft_bounce_threshold' => (int) ($options['soft_bounce_threshold']
                ?? ($settings['advanced']['soft_bounce_threshold'] ?? 3)),
        ];

        $doubleOptIn = (bool) ($settings['subscription']['double_optin'] ?? false);
        $isSms = $list->type === 'sms';

        // Raw query rows carry datetimes as strings, so compare as strings.
        $unconfirmedBefore = now()->subDays($thresholds['unconfirmed_after_days'])->toDateTimeString();
        $neverEngagedBefore = now()->subDays($thresholds['never_engaged_after_days'])->toDateTimeString();
        $dormantBefore = now()->subDays($thresholds['dormant_after_days'])->toDateTimeString();

        $suppressed = SuppressionList::where('user_id', $list->user_id)
            ->limit(100000)
            ->pluck('email')
            ->flip();

        $matches = array_fill_keys(self::CATEGORIES, []);
        $totals = [
            'members' => 0,
            'active' => 0,
            'unsubscribed' => 0,
            'bounced' => 0,
            'other_status' => 0,
            'confirmed' => 0,
        ];

        $canonicalSeen = [];
        $scanned = 0;
        $truncated = false;

        DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->select([
                'pivot.subscriber_id',
                'pivot.status',
                'pivot.subscribed_at',
                'pivot.unsubscribed_at',
                'pivot.confirmed_at',
                'pivot.soft_bounce_count',
                'subscribers.email',
                'subscribers.phone',
                'subscribers.is_active_global',
                'subscribers.opens_count',
                'subscribers.clicks_count',
                'subscribers.last_opened_at',
                'subscribers.last_clicked_at',
            ])
            ->orderBy('pivot.subscriber_id')
            ->chunk(1000, function ($rows) use (
                &$matches, &$totals, &$canonicalSeen, &$scanned, &$truncated,
                $maxScan, $isSms, $doubleOptIn, $suppressed,
                $unconfirmedBefore, $neverEngagedBefore, $dormantBefore, $thresholds
            ) {
                foreach ($rows as $row) {
                    if ($scanned >= $maxScan) {
                        $truncated = true;
                        return false;
                    }
                    $scanned++;

                    $email = $this->validator->normalize($row->email);
                    $status = $row->status ?? 'active';

                    $totals['members']++;
                    match ($status) {
                        'active' => $totals['active']++,
                        'unsubscribed' => $totals['unsubscribed']++,
                        'bounced' => $totals['bounced']++,
                        default => $totals['other_status']++,
                    };

                    if ($row->confirmed_at !== null) {
                        $totals['confirmed']++;
                    }

                    $entry = [
                        'subscriber_id' => $row->subscriber_id,
                        'email' => $email,
                        'phone' => $row->phone,
                        'status' => $status,
                        'detail' => null,
                    ];

                    // --- identity / address quality -----------------------
                    if ($isSms ? empty($row->phone) : ($email === null)) {
                        $matches['missing_contact'][] = $entry;
                    }

                    if ($email !== null) {
                        if (!$this->validator->syntaxValid($email)) {
                            $matches['invalid_syntax'][] = $entry;
                        }

                        $suggestion = $this->validator->typoSuggestion($email);
                        if ($suggestion !== null) {
                            $matches['typo_domain'][] = ['detail' => 'suggested: ' . $suggestion] + $entry;
                        }

                        if ($this->validator->isDisposable($email)) {
                            $matches['disposable_domain'][] = $entry;
                        }

                        if ($this->validator->isRole($email)) {
                            $matches['role_address'][] = $entry;
                        }

                        $canonical = $this->validator->canonical($email);
                        if ($canonical !== null) {
                            if (isset($canonicalSeen[$canonical])) {
                                $matches['duplicate'][] = ['detail' => 'duplicate of subscriber #' . $canonicalSeen[$canonical]] + $entry;
                            } else {
                                $canonicalSeen[$canonical] = $row->subscriber_id;
                            }
                        }

                        if ($suppressed->has($email) && $status === 'active') {
                            $matches['suppressed'][] = $entry;
                        }
                    }

                    // --- delivery state -----------------------------------
                    if ($status === 'bounced') {
                        $matches['hard_bounced'][] = $entry;
                    } elseif ((int) ($row->soft_bounce_count ?? 0) >= $thresholds['soft_bounce_threshold']) {
                        $matches['soft_bounce_risk'][] = ['detail' => 'soft bounces: ' . (int) $row->soft_bounce_count] + $entry;
                    }

                    if ($status === 'unsubscribed') {
                        $matches['unsubscribed'][] = $entry;
                    }

                    if ($status === 'active' && !$row->is_active_global) {
                        $matches['globally_inactive'][] = $entry;
                    }

                    if ($doubleOptIn && $status === 'active' && $row->confirmed_at === null
                        && $row->subscribed_at !== null && (string) $row->subscribed_at < $unconfirmedBefore) {
                        $matches['unconfirmed'][] = $entry;
                    }

                    // --- engagement ---------------------------------------
                    if ($status === 'active') {
                        $opens = (int) ($row->opens_count ?? 0);
                        $clicks = (int) ($row->clicks_count ?? 0);
                        $lastTouch = max((string) $row->last_opened_at, (string) $row->last_clicked_at);

                        if ($opens === 0 && $clicks === 0) {
                            if ($row->subscribed_at !== null && (string) $row->subscribed_at < $neverEngagedBefore) {
                                $matches['never_engaged'][] = ['detail' => 'subscribed ' . $row->subscribed_at] + $entry;
                            }
                        } elseif ($lastTouch !== '' && $lastTouch < $dormantBefore) {
                            $matches['dormant'][] = ['detail' => 'last engagement ' . $lastTouch] + $entry;
                        }
                    }
                }

                return true;
            });

        return [
            'matches' => $matches,
            'totals' => $totals,
            'scanned' => $scanned,
            'truncated' => $truncated,
            'thresholds' => $thresholds,
            'double_opt_in' => $doubleOptIn,
        ];
    }

    private function healthScore(array $issues, array $totals): int
    {
        $members = max(1, $totals['members']);
        $score = 100.0;

        foreach (self::SCORE_WEIGHTS as $category => $weight) {
            if ($weight === 0) {
                continue;
            }

            $ratio = ($issues[$category]['count'] ?? 0) / $members;
            // A category at 20%+ of the list costs its full weight.
            $score -= $weight * min(1.0, $ratio * 5);
        }

        return (int) max(0, min(100, round($score)));
    }

    /**
     * Turn issue counts into concrete, ordered next steps.
     */
    private function recommendations(array $issues, array $totals): array
    {
        $members = max(1, $totals['members']);
        $recommendations = [];

        $rules = [
            ['hard_bounced', 'remove', 'critical', 'Hard bounces keep being targeted — remove them to protect sender reputation.'],
            ['invalid_syntax', 'remove', 'critical', 'Addresses that cannot receive mail will bounce on the next send.'],
            ['suppressed', 'unsubscribe', 'critical', 'These contacts are on the suppression list but still active here.'],
            ['typo_domain', 'remove', 'high', 'Misspelled provider domains bounce every time; fix or drop them.'],
            ['disposable_domain', 'remove', 'high', 'Throwaway mailboxes expire and turn into hard bounces.'],
            ['soft_bounce_risk', 'unsubscribe', 'high', 'Repeated soft bounces usually precede a hard bounce.'],
            ['duplicate', 'remove', 'medium', 'The same mailbox appears more than once — deduplicate to stop double sends.'],
            ['unconfirmed', 'remove', 'medium', 'Double opt-in was never completed; these are not confirmed consents.'],
            ['never_engaged', 'tag', 'medium', 'Segment and try a re-engagement campaign before removing.'],
            ['dormant', 'tag', 'low', 'Previously engaged, now silent — a win-back sequence is worth trying.'],
            ['role_address', 'tag', 'low', 'Shared inboxes raise complaint rates; review them manually.'],
            ['globally_inactive', 'remove', 'low', 'Globally deactivated contacts still counted as list members.'],
        ];

        foreach ($rules as [$category, $action, $severity, $description]) {
            $count = $issues[$category]['count'] ?? 0;

            if ($count === 0) {
                continue;
            }

            $recommendations[] = [
                'category' => $category,
                'count' => $count,
                'share_percent' => round(($count / $members) * 100, 2),
                'suggested_action' => $action,
                'severity' => $severity,
                'description' => $description,
            ];
        }

        return $recommendations;
    }

    // ========================================================================
    // Cleaning
    // ========================================================================

    /**
     * Apply an action to every member matching the selected categories.
     *
     * @param array $options {
     *     categories: string[],
     *     action: unsubscribe|remove|delete|tag|suppress,
     *     tag: ?string        required for action=tag,
     *     dry_run: bool       default true,
     *     limit: int          default 1000, max MAX_SCAN,
     *     reason: ?string
     *     ...plus every scan() threshold
     * }
     */
    public function clean(ContactList $list, array $options): array
    {
        $categories = array_values(array_intersect($options['categories'] ?? [], self::CATEGORIES));
        $action = $options['action'] ?? 'unsubscribe';
        $dryRun = $options['dry_run'] ?? true;
        $limit = max(1, min((int) ($options['limit'] ?? 1000), self::MAX_SCAN));
        $reason = $options['reason'] ?? 'list_hygiene';

        if (empty($categories)) {
            throw new \InvalidArgumentException(
                'No valid categories given. Available: ' . implode(', ', self::CATEGORIES)
            );
        }

        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Unknown action: ' . $action);
        }

        if ($action === 'tag' && empty($options['tag'])) {
            throw new \InvalidArgumentException('action=tag requires a "tag" name.');
        }

        $scan = $this->scan($list, $options);

        // Collect matched members, deduplicated across categories.
        $targets = [];
        $byCategory = [];

        foreach ($categories as $category) {
            $byCategory[$category] = 0;

            foreach ($scan['matches'][$category] ?? [] as $match) {
                $id = $match['subscriber_id'];
                $byCategory[$category]++;

                if (!isset($targets[$id])) {
                    $targets[$id] = ['email' => $match['email'], 'categories' => []];
                }

                $targets[$id]['categories'][] = $category;
            }
        }

        $matchedTotal = count($targets);
        $targets = array_slice($targets, 0, $limit, true);

        $result = [
            'list' => ['id' => $list->id, 'name' => $list->name],
            'action' => $action,
            'categories' => $categories,
            'dry_run' => $dryRun,
            'matched' => $matchedTotal,
            'limited_to' => count($targets),
            'affected' => 0,
            'failed' => 0,
            'by_category' => $byCategory,
            'sample' => array_map(
                fn ($id, $data) => ['subscriber_id' => $id, 'email' => $data['email'], 'categories' => array_values(array_unique($data['categories']))],
                array_slice(array_keys($targets), 0, 10),
                array_slice(array_values($targets), 0, 10)
            ),
            'errors' => [],
        ];

        if ($dryRun || empty($targets)) {
            return $result;
        }

        $tag = null;
        if ($action === 'tag') {
            $tag = Tag::firstOrCreate(
                ['user_id' => $list->user_id, 'name' => trim($options['tag'])],
                ['color' => '#f97316']
            );
        }

        foreach (array_keys($targets) as $subscriberId) {
            try {
                $subscriber = Subscriber::where('user_id', $list->user_id)->find($subscriberId);

                if (!$subscriber) {
                    continue;
                }

                match ($action) {
                    'unsubscribe' => $this->unsubscribeFromList($subscriber, $list, $reason),
                    'remove' => $this->removeFromList($subscriber, $list),
                    'delete' => $this->deleteSubscriber($subscriber, $list),
                    'suppress' => $this->suppressSubscriber($subscriber, $list, $reason),
                    'tag' => $subscriber->addTag($tag),
                };

                $result['affected']++;
            } catch (\Throwable $e) {
                $result['failed']++;

                if (count($result['errors']) < 20) {
                    $result['errors'][] = ['subscriber_id' => $subscriberId, 'error' => $e->getMessage()];
                }

                Log::error('List hygiene action failed', [
                    'list_id' => $list->id,
                    'subscriber_id' => $subscriberId,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('List hygiene clean applied', [
            'list_id' => $list->id,
            'action' => $action,
            'categories' => $categories,
            'affected' => $result['affected'],
        ]);

        return $result;
    }

    private function unsubscribeFromList(Subscriber $subscriber, ContactList $list, string $reason): void
    {
        $pivot = $subscriber->contactLists()->where('contact_list_id', $list->id)->first();

        if (!$pivot || $pivot->pivot->status === 'unsubscribed') {
            return;
        }

        $subscriber->contactLists()->updateExistingPivot($list->id, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        // Stop anything still queued for this list's sequences.
        $this->cancelPlannedMessages($subscriber, $list);

        event(new SubscriberUnsubscribed($subscriber, $list, $reason));
    }

    private function removeFromList(Subscriber $subscriber, ContactList $list): void
    {
        $this->cancelPlannedMessages($subscriber, $list);
        $subscriber->contactLists()->detach($list->id);
    }

    private function deleteSubscriber(Subscriber $subscriber, ContactList $list): void
    {
        $this->cancelPlannedMessages($subscriber, $list);
        MessageQueueEntry::where('subscriber_id', $subscriber->id)
            ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
            ->delete();

        $subscriber->delete();
    }

    private function suppressSubscriber(Subscriber $subscriber, ContactList $list, string $reason): void
    {
        if (!empty($subscriber->email)) {
            SuppressionList::suppress($list->user_id, $subscriber->email, $reason);
        }

        // Suppression is account-wide, so pull them off every list, not just this one.
        foreach ($subscriber->contactLists as $memberList) {
            if ($memberList->pivot->status !== 'unsubscribed') {
                $subscriber->contactLists()->updateExistingPivot($memberList->id, [
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => now(),
                ]);

                event(new SubscriberUnsubscribed($subscriber, $memberList, $reason));
            }
        }

        MessageQueueEntry::where('subscriber_id', $subscriber->id)
            ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
            ->delete();
    }

    /**
     * Drop not-yet-sent queue entries for messages that target this list, so a
     * cleaned contact does not receive a message that was already planned.
     */
    private function cancelPlannedMessages(Subscriber $subscriber, ContactList $list): void
    {
        MessageQueueEntry::where('subscriber_id', $subscriber->id)
            ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
            ->whereIn('message_id', function ($query) use ($list) {
                $query->select('message_id')
                    ->from('contact_list_message')
                    ->where('contact_list_id', $list->id);
            })
            ->delete();
    }

    // ========================================================================
    // Deduplication
    // ========================================================================

    /**
     * Merge subscribers on this list whose addresses resolve to the same
     * mailbox (case, dots and +tags folded). The oldest record wins; the
     * others hand over their list memberships, tags and custom values and are
     * then soft-deleted.
     */
    public function dedupe(ContactList $list, array $options = []): array
    {
        $dryRun = $options['dry_run'] ?? true;
        $limit = max(1, min((int) ($options['limit'] ?? 500), 5000));
        $strategy = $options['keep'] ?? 'oldest'; // oldest|most_engaged

        $rows = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->whereNotNull('subscribers.email')
            ->select([
                'subscribers.id',
                'subscribers.email',
                'subscribers.created_at',
                'subscribers.opens_count',
                'subscribers.clicks_count',
            ])
            ->limit(self::MAX_SCAN)
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $canonical = $this->validator->canonical($row->email);
            if ($canonical === null) {
                continue;
            }
            $groups[$canonical][] = $row;
        }

        $duplicateGroups = array_filter($groups, fn ($g) => count($g) > 1);

        $result = [
            'list' => ['id' => $list->id, 'name' => $list->name],
            'dry_run' => $dryRun,
            'duplicate_groups' => count($duplicateGroups),
            'duplicate_records' => array_sum(array_map(fn ($g) => count($g) - 1, $duplicateGroups)),
            'merged' => 0,
            'failed' => 0,
            'keep_strategy' => $strategy,
            'groups' => [],
            'errors' => [],
        ];

        $processed = 0;

        foreach ($duplicateGroups as $canonical => $group) {
            if ($processed >= $limit) {
                break;
            }
            $processed++;

            $sorted = collect($group)->sortBy(function ($row) use ($strategy) {
                return $strategy === 'most_engaged'
                    ? -(((int) $row->opens_count) + ((int) $row->clicks_count) * 2)
                    : (string) $row->created_at;
            })->values();

            $primary = $sorted->first();
            $duplicates = $sorted->slice(1);

            if (count($result['groups']) < 20) {
                $result['groups'][] = [
                    'canonical_email' => $canonical,
                    'keep' => ['id' => $primary->id, 'email' => $primary->email],
                    'merge' => $duplicates->map(fn ($d) => ['id' => $d->id, 'email' => $d->email])->all(),
                ];
            }

            if ($dryRun) {
                continue;
            }

            foreach ($duplicates as $duplicate) {
                try {
                    $this->mergeSubscribers((int) $primary->id, (int) $duplicate->id, $list->user_id);
                    $result['merged']++;
                } catch (\Throwable $e) {
                    $result['failed']++;

                    if (count($result['errors']) < 20) {
                        $result['errors'][] = ['subscriber_id' => $duplicate->id, 'error' => $e->getMessage()];
                    }

                    Log::error('List dedupe merge failed', [
                        'list_id' => $list->id,
                        'primary_id' => $primary->id,
                        'duplicate_id' => $duplicate->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Move everything worth keeping from $duplicateId onto $primaryId, then
     * soft-delete the duplicate.
     */
    private function mergeSubscribers(int $primaryId, int $duplicateId, int $userId): void
    {
        DB::transaction(function () use ($primaryId, $duplicateId, $userId) {
            $primary = Subscriber::where('user_id', $userId)->findOrFail($primaryId);
            $duplicate = Subscriber::where('user_id', $userId)->findOrFail($duplicateId);

            // Fill blanks on the primary record.
            $updates = [];
            foreach (['phone', 'first_name', 'last_name', 'gender', 'language', 'timezone', 'source'] as $field) {
                if (empty($primary->{$field}) && !empty($duplicate->{$field})) {
                    $updates[$field] = $duplicate->{$field};
                }
            }
            if (!empty($updates)) {
                $primary->update($updates);
            }

            // List memberships the primary is not on yet.
            $primaryListIds = $primary->contactLists()->pluck('contact_lists.id')->all();

            foreach ($duplicate->contactLists as $list) {
                if (in_array($list->id, $primaryListIds, true)) {
                    continue;
                }

                $primary->contactLists()->attach($list->id, [
                    'status' => $list->pivot->status,
                    'source' => $list->pivot->source,
                    'subscribed_at' => $list->pivot->subscribed_at,
                    'confirmed_at' => $list->pivot->confirmed_at,
                ]);
            }

            // Tags.
            $primary->tags()->syncWithoutDetaching($duplicate->tags()->pluck('tags.id')->all());

            // Custom field values the primary does not have.
            $primaryFieldIds = $primary->fieldValues()->pluck('custom_field_id')->all();

            foreach ($duplicate->fieldValues as $value) {
                if (!in_array($value->custom_field_id, $primaryFieldIds, true)) {
                    $primary->fieldValues()->create([
                        'custom_field_id' => $value->custom_field_id,
                        'value' => $value->value,
                    ]);
                }
            }

            // Keep the higher engagement counters.
            $primary->update([
                'opens_count' => max((int) $primary->opens_count, (int) $duplicate->opens_count),
                'clicks_count' => max((int) $primary->clicks_count, (int) $duplicate->clicks_count),
            ]);

            MessageQueueEntry::where('subscriber_id', $duplicate->id)
                ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                ->delete();

            $duplicate->contactLists()->detach();
            $duplicate->delete();
        });
    }

    // ========================================================================
    // Verification (DNS)
    // ========================================================================

    /**
     * Check deliverability of the list's domains: syntax + MX/A record.
     *
     * DNS lookups are done per distinct domain (cached for a day), so the cost
     * scales with the number of domains, not the number of addresses.
     */
    public function verify(ContactList $list, array $options = []): array
    {
        $limit = max(1, min((int) ($options['limit'] ?? 1000), 10000));
        $statusFilter = $options['status'] ?? 'active';
        $checkMx = $options['check_mx'] ?? true;
        $maxDomains = max(1, min((int) ($options['max_domains'] ?? 200), 1000));

        $query = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->whereNotNull('subscribers.email')
            ->select(['subscribers.id', 'subscribers.email'])
            ->orderBy('subscribers.id')
            ->limit($limit);

        if ($statusFilter !== 'all') {
            $query->where('pivot.status', $statusFilter);
        }

        $rows = $query->get();

        $verdicts = ['deliverable' => 0, 'risky' => 0, 'undeliverable' => 0, 'unknown' => 0];
        $domainCounts = [];
        $problems = [];

        foreach ($rows as $row) {
            $inspection = $this->validator->inspect($row->email);
            $domain = $this->validator->domain($row->email);

            if ($domain !== null) {
                $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;
            }

            $verdict = 'deliverable';

            if (!$inspection['valid']) {
                $verdict = 'undeliverable';
            } elseif (in_array('typo_domain', $inspection['issues'], true) || in_array('disposable_domain', $inspection['issues'], true)) {
                $verdict = 'undeliverable';
            } elseif (in_array('role_address', $inspection['issues'], true)) {
                $verdict = 'risky';
            }

            if ($verdict !== 'deliverable' && count($problems) < 100) {
                $problems[] = [
                    'subscriber_id' => $row->id,
                    'email' => $inspection['email'],
                    'verdict' => $verdict,
                    'issues' => $inspection['issues'],
                    'suggestion' => $inspection['suggestion'],
                ];
            }

            $verdicts[$verdict]++;
        }

        // MX lookups on the most common domains only — that covers the bulk of
        // the list without turning this into thousands of DNS queries.
        $domainResults = [];

        if ($checkMx) {
            arsort($domainCounts);

            foreach (array_slice($domainCounts, 0, $maxDomains, true) as $domain => $count) {
                $hasMx = $this->validator->hasMx($domain);

                $domainResults[] = [
                    'domain' => $domain,
                    'addresses' => $count,
                    'has_mx' => $hasMx,
                ];

                if (!$hasMx) {
                    $verdicts['deliverable'] = max(0, $verdicts['deliverable'] - $count);
                    $verdicts['undeliverable'] += $count;
                }
            }
        }

        return [
            'list' => ['id' => $list->id, 'name' => $list->name],
            'checked' => $rows->count(),
            'status_filter' => $statusFilter,
            'verdicts' => $verdicts,
            'deliverable_rate' => $rows->count() > 0
                ? round(($verdicts['deliverable'] / $rows->count()) * 100, 2)
                : 0.0,
            'domains' => array_slice($domainResults, 0, 50),
            'domains_without_mx' => array_values(array_filter($domainResults, fn ($d) => !$d['has_mx'])),
            'problems' => $problems,
        ];
    }
}
