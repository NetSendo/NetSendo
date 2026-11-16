<?php

namespace App\Services\Segmentation;

use App\Models\CustomField;
use App\Models\Message;
use App\Models\MessageFieldFilter;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a segment's conditions into a subscriber query.
 *
 * Conditions (each an array with `type`):
 * - on_list / not_on_list {list_id}: active member of the list, or not;
 * - has_tag / not_has_tag {tag_id};
 * - field {custom_field_id, operator, values[]}: the same operators as the
 *   message audience field filters (MessageFieldFilter);
 * - opened_within / not_opened_within / clicked_within / not_clicked_within
 *   {days}: last open or click (subscribers.last_opened_at / last_clicked_at);
 * - opened_message / not_opened_message / clicked_message /
 *   not_clicked_message {message_id};
 * - joined_within / joined_before {days}: when the subscriber was added.
 *
 * A segment always means deliverable subscribers of its account who are an
 * active member of at least one list; a message narrows its own list
 * audience with apply().
 */
class SegmentQueryService
{
    public const TYPES = [
        'on_list', 'not_on_list',
        'has_tag', 'not_has_tag',
        'field',
        'opened_within', 'not_opened_within', 'clicked_within', 'not_clicked_within',
        'opened_message', 'not_opened_message', 'clicked_message', 'not_clicked_message',
        'joined_within', 'joined_before',
    ];

    private const DAY_TYPES = [
        'opened_within', 'not_opened_within', 'clicked_within', 'not_clicked_within',
        'joined_within', 'joined_before',
    ];

    private const MESSAGE_TYPES = ['opened_message', 'not_opened_message', 'clicked_message', 'not_clicked_message'];

    public const MAX_CONDITIONS = 20;

    public function __construct(protected SubscriberFieldFilterService $fieldFilters)
    {
    }

    /**
     * Everyone in the segment.
     */
    public function query(Segment $segment): Builder
    {
        return $this->apply($this->baseQuery((int) $segment->user_id), $segment->conditions ?? [], $segment->match);
    }

    public function count(Segment $segment): int
    {
        return $this->query($segment)->count();
    }

    /**
     * Count and remember it on the segment (shown on the segment list).
     */
    public function refreshCount(Segment $segment): int
    {
        $count = $this->count($segment);
        $segment->forceFill(['subscribers_count' => $count, 'counted_at' => now()])->saveQuietly();

        return $count;
    }

    public function matchesSubscriber(Segment $segment, int $subscriberId): bool
    {
        return $this->query($segment)->whereKey($subscriberId)->exists();
    }

    /**
     * Deliverable subscribers of an account with at least one active membership.
     */
    public function baseQuery(int $userId): Builder
    {
        $query = Subscriber::query()
            ->where('subscribers.user_id', $userId)
            ->where('subscribers.is_active_global', true)
            ->whereHas('contactLists', fn ($lists) => $lists->where('contact_list_subscriber.status', 'active'));

        return Subscriber::applyStatusFilter($query, Subscriber::STATUS_ACTIVE);
    }

    /**
     * Narrow a subscriber query to the conditions. No usable condition means
     * no narrowing.
     */
    public function apply(Builder $query, array $conditions, ?string $match = Segment::MATCH_ALL): Builder
    {
        $conditions = array_values(array_filter($conditions, fn ($c) => is_array($c) && in_array($c['type'] ?? null, self::TYPES, true)));

        if ($conditions === []) {
            return $query;
        }

        $fields = CustomField::whereIn('id', collect($conditions)->pluck('custom_field_id')->filter()->all())->get()->keyBy('id');

        return $query->where(function (Builder $group) use ($conditions, $match, $fields) {
            foreach ($conditions as $index => $condition) {
                $boolean = ($match === Segment::MATCH_ANY && $index > 0) ? 'or' : 'and';
                $group->where(fn (Builder $one) => $this->applyOne($one, $condition, $fields), null, null, $boolean);
            }
        });
    }

    protected function applyOne(Builder $query, array $condition, $fields): void
    {
        $type = $condition['type'];
        $since = in_array($type, self::DAY_TYPES, true) ? now()->subDays(max(1, (int) ($condition['days'] ?? 0))) : null;

        match ($type) {
            'on_list' => $query->whereHas('contactLists', fn ($l) => $l
                ->where('contact_lists.id', (int) $condition['list_id'])
                ->where('contact_list_subscriber.status', 'active')),
            'not_on_list' => $query->whereDoesntHave('contactLists', fn ($l) => $l
                ->where('contact_lists.id', (int) $condition['list_id'])
                ->where('contact_list_subscriber.status', 'active')),
            'has_tag' => $query->whereHas('tags', fn ($t) => $t->where('tags.id', (int) $condition['tag_id'])),
            'not_has_tag' => $query->whereDoesntHave('tags', fn ($t) => $t->where('tags.id', (int) $condition['tag_id'])),
            'field' => $this->applyField($query, $condition, $fields),
            'opened_within' => $query->where('subscribers.last_opened_at', '>=', $since),
            'not_opened_within' => $query->where(fn ($q) => $q->whereNull('subscribers.last_opened_at')->orWhere('subscribers.last_opened_at', '<', $since)),
            'clicked_within' => $query->where('subscribers.last_clicked_at', '>=', $since),
            'not_clicked_within' => $query->where(fn ($q) => $q->whereNull('subscribers.last_clicked_at')->orWhere('subscribers.last_clicked_at', '<', $since)),
            'opened_message' => $this->whereEngaged($query, 'email_opens', (int) $condition['message_id'], true),
            'not_opened_message' => $this->whereEngaged($query, 'email_opens', (int) $condition['message_id'], false),
            'clicked_message' => $this->whereEngaged($query, 'email_clicks', (int) $condition['message_id'], true),
            'not_clicked_message' => $this->whereEngaged($query, 'email_clicks', (int) $condition['message_id'], false),
            'joined_within' => $query->where('subscribers.created_at', '>=', $since),
            'joined_before' => $query->where('subscribers.created_at', '<', $since),
        };
    }

    protected function applyField(Builder $query, array $condition, $fields): void
    {
        $field = $fields->get((int) ($condition['custom_field_id'] ?? 0));

        if (!$field) {
            // The field was deleted: nobody matches rather than everybody.
            $query->whereRaw('1 = 0');

            return;
        }

        $filter = new MessageFieldFilter([
            'custom_field_id' => $field->id,
            'operator' => $condition['operator'] ?? MessageFieldFilter::OP_ANY_OF,
            'values' => array_values((array) ($condition['values'] ?? [])),
        ]);
        $filter->setRelation('customField', $field);

        if (!$filter->isUsable()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $this->fieldFilters->applyFilters($query, [$filter]);
    }

    protected function whereEngaged(Builder $query, string $table, int $messageId, bool $did): void
    {
        $exists = fn ($sub) => $sub->select(DB::raw(1))
            ->from($table)
            ->whereColumn("{$table}.subscriber_id", 'subscribers.id')
            ->where("{$table}.message_id", $messageId);

        $did ? $query->whereExists($exists) : $query->whereNotExists($exists);
    }

    /**
     * Check and normalise conditions sent by the panel or the API: known
     * types, the values each needs, and lists, tags, messages and fields of
     * the user's own account.
     *
     * @throws ValidationException
     */
    public function sanitize(array $conditions, User $user, string $attribute = 'conditions'): array
    {
        if (count($conditions) > self::MAX_CONDITIONS) {
            throw ValidationException::withMessages([$attribute => __('segments.validation.too_many', ['max' => self::MAX_CONDITIONS])]);
        }

        $listIds = $user->accessibleLists()->pluck('contact_lists.id')->map(fn ($id) => (int) $id)->all();
        $clean = [];

        foreach (array_values($conditions) as $index => $condition) {
            $key = "{$attribute}.{$index}";
            $type = is_array($condition) ? ($condition['type'] ?? null) : null;

            if (!in_array($type, self::TYPES, true)) {
                throw ValidationException::withMessages([$key => __('segments.validation.unknown_type')]);
            }

            $row = ['type' => $type];

            if (in_array($type, ['on_list', 'not_on_list'], true)) {
                $id = (int) ($condition['list_id'] ?? 0);
                $this->require(in_array($id, $listIds, true), $key, 'list');
                $row['list_id'] = $id;
            } elseif (in_array($type, ['has_tag', 'not_has_tag'], true)) {
                $id = (int) ($condition['tag_id'] ?? 0);
                $this->require(Tag::where('user_id', $user->id)->whereKey($id)->exists(), $key, 'tag');
                $row['tag_id'] = $id;
            } elseif (in_array($type, self::MESSAGE_TYPES, true)) {
                $id = (int) ($condition['message_id'] ?? 0);
                $this->require(Message::where('user_id', $user->id)->whereKey($id)->exists(), $key, 'message');
                $row['message_id'] = $id;
            } elseif (in_array($type, self::DAY_TYPES, true)) {
                $days = (int) ($condition['days'] ?? 0);
                $this->require($days >= 1 && $days <= 3650, $key, 'days');
                $row['days'] = $days;
            } elseif ($type === 'field') {
                $field = CustomField::where('user_id', $user->id)->find((int) ($condition['custom_field_id'] ?? 0));
                $this->require((bool) $field, $key, 'field');
                $operator = $condition['operator'] ?? MessageFieldFilter::OP_ANY_OF;
                $this->require(in_array($operator, MessageFieldFilter::operatorsForType($field->type), true), $key, 'operator');
                $values = array_values(array_filter(
                    array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) ($condition['values'] ?? [])),
                    fn ($v) => $v !== ''
                ));
                $needs = !MessageFieldFilter::needsValue($operator) ? 0 : ($operator === MessageFieldFilter::OP_BETWEEN ? 2 : 1);
                $this->require(count($values) >= $needs, $key, 'value');
                $row += ['custom_field_id' => $field->id, 'operator' => $operator, 'values' => $values];
            }

            $clean[] = $row;
        }

        return $clean;
    }

    private function require(bool $ok, string $key, string $what): void
    {
        if (!$ok) {
            throw ValidationException::withMessages([$key => __("segments.validation.{$what}")]);
        }
    }
}
