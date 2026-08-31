<?php

namespace App\Services\Segmentation;

use App\Models\CustomField;
use App\Models\Message;
use App\Models\MessageFieldFilter;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns custom-field conditions into subscriber queries.
 *
 * Values live in subscriber_field_values (one row per subscriber+field), so
 * every condition becomes an EXISTS / NOT EXISTS against that table. Negative
 * operators are deliberately expressed as "has no matching row", which also
 * catches subscribers who never filled the field in — "city is not Kraków"
 * should reach people with no city at all.
 */
class SubscriberFieldFilterService
{
    /**
     * Constrain a subscriber query to the rows matching the given filters.
     *
     * Filters are combined with AND (match = all) or OR (match = any); values
     * inside one filter are always OR-ed. Filters missing the values their
     * operator needs are skipped instead of matching everyone.
     *
     * @param  Builder  $query  a Subscriber query
     * @param  Collection<int, MessageFieldFilter>|iterable  $filters
     */
    public function applyFilters(Builder $query, $filters, string $match = MessageFieldFilter::MATCH_ALL): Builder
    {
        $usable = $this->usableFilters($filters);

        if ($usable->isEmpty()) {
            return $query;
        }

        return $query->where(function ($group) use ($usable, $match) {
            foreach ($usable->values() as $index => $filter) {
                $boolean = ($match === MessageFieldFilter::MATCH_ANY && $index > 0) ? 'or' : 'and';
                $this->applyFilter($group, $filter, $boolean);
            }
        });
    }

    /**
     * Whether one subscriber satisfies the filters. Runs the very same query as
     * the bulk path so a single signup can never be judged by different rules
     * than a broadcast.
     *
     * @param  Collection<int, MessageFieldFilter>|iterable  $filters
     */
    public function matchesSubscriber(int $subscriberId, $filters, string $match = MessageFieldFilter::MATCH_ALL): bool
    {
        if ($this->usableFilters($filters)->isEmpty()) {
            return true;
        }

        $query = Subscriber::query()->whereKey($subscriberId);

        return $this->applyFilters($query, $filters, $match)->exists();
    }

    /**
     * Drop filters that carry no usable value — an operator waiting for a value
     * must never widen the audience.
     *
     * @param  Collection<int, MessageFieldFilter>|iterable  $filters
     * @return Collection<int, MessageFieldFilter>
     */
    public function usableFilters($filters): Collection
    {
        return collect($filters)->filter(fn (MessageFieldFilter $filter) => $filter->isUsable())->values();
    }

    /**
     * Emails to drop from an audience.
     *
     * Excluded lists alone drop everybody on them. Combined with exclude-side
     * filters they drop only the people on those lists who also match ("everyone
     * from list X, but only the ones from Kraków"). Filters set without any
     * excluded list are applied to the audience itself, so an exclusion never
     * reaches beyond the message's own lists.
     *
     * An empty result means "nothing is excluded" — never "exclude everyone".
     *
     * @param  Collection<int, MessageFieldFilter>|iterable  $excludeFilters
     * @return array<int, string>
     */
    public function excludedEmails(
        array $includedListIds,
        array $excludedListIds,
        $excludeFilters,
        string $match = MessageFieldFilter::MATCH_ALL
    ): array {
        $usable = $this->usableFilters($excludeFilters);

        if (empty($excludedListIds) && $usable->isEmpty()) {
            return [];
        }

        $scopeListIds = !empty($excludedListIds) ? $excludedListIds : $includedListIds;

        $query = Subscriber::whereHas('contactLists', function ($q) use ($scopeListIds, $excludedListIds) {
            $q->whereIn('contact_lists.id', $scopeListIds);

            // Excluded lists drop a subscriber whatever their status there
            // (that is what an exclusion list is for); the audience-scoped
            // fallback must only consider live memberships.
            if (empty($excludedListIds)) {
                $q->where('contact_list_subscriber.status', 'active');
            }
        });

        if ($usable->isNotEmpty()) {
            $this->applyFilters($query, $usable, $match);
        }

        return $query->pluck('email')->all();
    }

    /**
     * Active subscribers of the given lists, minus the excluded emails, narrowed
     * by the include-side filters. The single definition of a list audience,
     * shared by the send path and the "how many will this reach" estimate.
     *
     * @param  Collection<int, MessageFieldFilter>|iterable  $includeFilters
     */
    public function audienceQuery(
        array $includedListIds,
        array $excludedEmails,
        $includeFilters,
        string $match = MessageFieldFilter::MATCH_ALL
    ): Builder {
        $query = Subscriber::whereHas('contactLists', function ($q) use ($includedListIds) {
            $q->whereIn('contact_lists.id', $includedListIds)
                ->where('contact_list_subscriber.status', 'active');
        });

        if (!empty($excludedEmails)) {
            $query->whereNotIn('email', $excludedEmails);
        }

        return $this->applyFilters($query, $includeFilters, $match);
    }

    /**
     * How many people a list selection reaches, before and after the filters,
     * so the operator sees what each condition costs them.
     *
     * Counts distinct emails, matching the deduplication the send path applies.
     *
     * @return array{base: int, total: int, excluded: int}
     */
    public function estimate(
        array $includedListIds,
        array $excludedListIds,
        $includeFilters,
        string $includeMatch,
        $excludeFilters,
        string $excludeMatch
    ): array {
        if (empty($includedListIds)) {
            return ['base' => 0, 'total' => 0, 'excluded' => 0];
        }

        $base = $this->audienceQuery($includedListIds, [], [], $includeMatch)
            ->distinct()
            ->count('subscribers.email');

        $excludedEmails = $this->excludedEmails($includedListIds, $excludedListIds, $excludeFilters, $excludeMatch);

        $total = $this->audienceQuery($includedListIds, $excludedEmails, $includeFilters, $includeMatch)
            ->distinct()
            ->count('subscribers.email');

        return [
            'base' => $base,
            'total' => $total,
            'excluded' => max(0, $base - $total),
        ];
    }

    /**
     * Custom fields usable for the given lists: global ones plus the fields
     * defined for those lists only.
     *
     * @return Collection<int, CustomField>
     */
    public function availableFields(int $userId, array $listIds = []): Collection
    {
        return CustomField::where('user_id', $userId)
            ->where(function ($query) use ($listIds) {
                $query->where('scope', 'global');

                if (!empty($listIds)) {
                    $query->orWhere(function ($sub) use ($listIds) {
                        $sub->where('scope', 'list')->whereIn('contact_list_id', $listIds);
                    });
                }
            })
            ->with('contactList:id,name')
            ->orderBy('scope') // 'global' sorts before 'list'
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Values actually stored for a field, most common first, so the operator can
     * pick "Oświęcim" from what subscribers really have instead of typing it.
     *
     * @return array<int, array{value: string, count: int}>
     */
    public function fieldValues(CustomField $field, array $listIds = [], ?string $search = null, int $limit = 50): array
    {
        $query = DB::table('subscriber_field_values as sfv')
            ->join('subscribers as s', 's.id', '=', 'sfv.subscriber_id')
            ->where('sfv.custom_field_id', $field->id)
            ->whereNull('s.deleted_at')
            ->whereNotNull('sfv.value')
            ->where('sfv.value', '<>', '');

        if (!empty($listIds)) {
            $query->whereExists(function ($sub) use ($listIds) {
                $sub->select(DB::raw(1))
                    ->from('contact_list_subscriber as cls')
                    ->whereColumn('cls.subscriber_id', 's.id')
                    ->whereIn('cls.contact_list_id', $listIds)
                    ->where('cls.status', 'active');
            });
        } else {
            $query->where('s.user_id', $field->user_id);
        }

        if ($search !== null && $search !== '') {
            $query->where('sfv.value', 'like', '%' . $this->escapeLike($search) . '%');
        }

        return $query
            ->select('sfv.value', DB::raw('COUNT(DISTINCT sfv.subscriber_id) as subscribers_count'))
            ->groupBy('sfv.value')
            ->orderByDesc('subscribers_count')
            ->orderBy('sfv.value')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'count' => (int) $row->subscribers_count,
            ])
            ->all();
    }

    /**
     * Replace one side (include/exclude) of a message's filters.
     *
     * Rows referencing a field the user does not own are dropped rather than
     * saved, so a tampered payload cannot filter on someone else's data.
     *
     * @param  array<int, array{custom_field_id: int|string, operator?: string, values?: array}>  $rows
     */
    public function syncFilters(Message $message, string $mode, array $rows): void
    {
        $ownedFieldIds = CustomField::where('user_id', $message->user_id)
            ->pluck('id')
            ->all();

        $message->fieldFilters()->where('mode', $mode)->delete();

        foreach (array_values($rows) as $index => $row) {
            $fieldId = (int) ($row['custom_field_id'] ?? 0);

            if (!in_array($fieldId, $ownedFieldIds, true)) {
                continue;
            }

            $operator = $row['operator'] ?? MessageFieldFilter::OP_ANY_OF;

            if (!in_array($operator, MessageFieldFilter::OPERATORS, true)) {
                continue;
            }

            $values = array_values(array_filter(
                array_map(
                    fn ($value) => is_string($value) ? trim($value) : $value,
                    (array) ($row['values'] ?? [])
                ),
                fn ($value) => $value !== null && $value !== ''
            ));

            $filter = new MessageFieldFilter([
                'custom_field_id' => $fieldId,
                'mode' => $mode,
                'operator' => $operator,
                'values' => $values,
                'sort_order' => $index,
            ]);

            // A half-filled row would otherwise be stored and silently ignored
            // on every send; drop it at the door instead.
            if (!$filter->isUsable()) {
                continue;
            }

            $message->fieldFilters()->save($filter);
        }

        // The message may still hold the pre-sync collection; drop it so the
        // next audience calculation reads what was just written.
        $message->unsetRelation('fieldFilters');
    }

    /**
     * Add a single condition to the query group.
     */
    protected function applyFilter($query, MessageFieldFilter $filter, string $boolean = 'and'): void
    {
        $fieldId = $filter->custom_field_id;
        $values = $filter->cleanValues();
        $type = $filter->customField?->type ?? 'text';

        switch ($filter->operator) {
            case MessageFieldFilter::OP_IS_SET:
                $this->whereHasValue($query, $fieldId, fn ($q) => $q, $boolean);
                break;

            case MessageFieldFilter::OP_IS_EMPTY:
                $this->whereHasNoValue($query, $fieldId, fn ($q) => $q, $boolean);
                break;

            case MessageFieldFilter::OP_ANY_OF:
                $this->whereHasValue($query, $fieldId, fn ($q) => $q->whereIn('value', $values), $boolean);
                break;

            case MessageFieldFilter::OP_NONE_OF:
                $this->whereHasNoValue($query, $fieldId, fn ($q) => $q->whereIn('value', $values), $boolean);
                break;

            case MessageFieldFilter::OP_CONTAINS:
                $this->whereHasValue($query, $fieldId, fn ($q) => $this->orLike($q, $values, '%%%s%%'), $boolean);
                break;

            case MessageFieldFilter::OP_NOT_CONTAINS:
                $this->whereHasNoValue($query, $fieldId, fn ($q) => $this->orLike($q, $values, '%%%s%%'), $boolean);
                break;

            case MessageFieldFilter::OP_STARTS_WITH:
                $this->whereHasValue($query, $fieldId, fn ($q) => $this->orLike($q, $values, '%s%%'), $boolean);
                break;

            case MessageFieldFilter::OP_ENDS_WITH:
                $this->whereHasValue($query, $fieldId, fn ($q) => $this->orLike($q, $values, '%%%s'), $boolean);
                break;

            case MessageFieldFilter::OP_GT:
            case MessageFieldFilter::OP_GTE:
            case MessageFieldFilter::OP_LT:
            case MessageFieldFilter::OP_LTE:
                $operator = match ($filter->operator) {
                    MessageFieldFilter::OP_GT => '>',
                    MessageFieldFilter::OP_GTE => '>=',
                    MessageFieldFilter::OP_LT => '<',
                    default => '<=',
                };
                $expression = $this->castExpression($type);
                $this->whereHasValue(
                    $query,
                    $fieldId,
                    fn ($q) => $q->whereRaw("{$expression} {$operator} ?", [$this->castBinding($type, $values[0])]),
                    $boolean
                );
                break;

            case MessageFieldFilter::OP_BETWEEN:
                $expression = $this->castExpression($type);
                $this->whereHasValue(
                    $query,
                    $fieldId,
                    fn ($q) => $q->whereRaw("{$expression} BETWEEN ? AND ?", [
                        $this->castBinding($type, $values[0]),
                        $this->castBinding($type, $values[1]),
                    ]),
                    $boolean
                );
                break;
        }
    }

    /**
     * "Subscriber has a non-empty value for this field matching ..."
     */
    protected function whereHasValue($query, int $fieldId, callable $constraint, string $boolean): void
    {
        $callback = function ($q) use ($fieldId, $constraint) {
            $q->where('custom_field_id', $fieldId)
                ->whereNotNull('value')
                ->where('value', '<>', '');
            $constraint($q);
        };

        $boolean === 'or'
            ? $query->orWhereHas('fieldValues', $callback)
            : $query->whereHas('fieldValues', $callback);
    }

    /**
     * "Subscriber has no value for this field matching ..." — which also covers
     * subscribers who never filled the field in.
     */
    protected function whereHasNoValue($query, int $fieldId, callable $constraint, string $boolean): void
    {
        $callback = function ($q) use ($fieldId, $constraint) {
            $q->where('custom_field_id', $fieldId)
                ->whereNotNull('value')
                ->where('value', '<>', '');
            $constraint($q);
        };

        $boolean === 'or'
            ? $query->orWhereDoesntHave('fieldValues', $callback)
            : $query->whereDoesntHave('fieldValues', $callback);
    }

    /**
     * OR together a LIKE per value, e.g. contains "kra" OR contains "osw".
     */
    protected function orLike($query, array $values, string $pattern)
    {
        return $query->where(function ($q) use ($values, $pattern) {
            foreach ($values as $value) {
                $q->orWhere('value', 'like', sprintf($pattern, $this->escapeLike((string) $value)));
            }
        });
    }

    /**
     * Values are stored as text, so ordered comparisons need a cast.
     */
    protected function castExpression(string $type): string
    {
        $driver = DB::connection()->getDriverName();

        if ($type === 'date') {
            return match ($driver) {
                'mysql', 'mariadb' => 'CAST(subscriber_field_values.value AS DATE)',
                'pgsql' => 'subscriber_field_values.value::date',
                default => 'subscriber_field_values.value',
            };
        }

        return match ($driver) {
            'mysql', 'mariadb' => 'CAST(subscriber_field_values.value AS DECIMAL(20,6))',
            'pgsql' => 'subscriber_field_values.value::numeric',
            default => 'CAST(subscriber_field_values.value AS REAL)',
        };
    }

    /**
     * Dates compare as plain strings on SQLite, numbers always as floats.
     */
    protected function castBinding(string $type, $value)
    {
        return $type === 'date' ? (string) $value : (float) $value;
    }

    /**
     * Escape the LIKE wildcards so a value containing % or _ matches literally.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
