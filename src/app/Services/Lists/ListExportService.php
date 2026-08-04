<?php

namespace App\Services\Lists;

use App\Models\ContactList;
use App\Models\CustomField;
use Illuminate\Support\Facades\DB;

/**
 * Synchronous, filterable export of a list's members.
 *
 * The existing queued export (ExportSubscribersCsv) mails a download link to a
 * human — useless to an API caller that needs the rows in the response. This
 * returns the data inline, capped, with a cursor so large lists can be walked
 * page by page.
 */
class ListExportService
{
    public const FORMATS = ['json', 'csv', 'ndjson'];

    /** Rows per call. Beyond this, callers page with next_cursor. */
    public const MAX_LIMIT = 5000;

    public const DEFAULT_FIELDS = [
        'id', 'email', 'phone', 'first_name', 'last_name', 'status', 'subscribed_at',
    ];

    public const AVAILABLE_FIELDS = [
        'id', 'email', 'phone', 'first_name', 'last_name', 'gender', 'language', 'timezone',
        'status', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at',
        'opens_count', 'clicks_count', 'last_opened_at', 'last_clicked_at', 'created_at',
        'tags', 'custom_fields',
    ];

    /**
     * @param array $options {
     *     format: json|csv|ndjson,
     *     fields: string[],
     *     status: active|unsubscribed|bounced|all,
     *     tag_ids: int[],
     *     subscribed_after: ?string, subscribed_before: ?string,
     *     engaged: ?bool          only contacts with at least one open/click,
     *     limit: int, cursor: ?int,
     *     delimiter: string       csv only
     * }
     */
    public function export(ContactList $list, array $options = []): array
    {
        $format = $options['format'] ?? 'json';
        $format = in_array($format, self::FORMATS, true) ? $format : 'json';
        $limit = max(1, min((int) ($options['limit'] ?? 500), self::MAX_LIMIT));
        $cursor = (int) ($options['cursor'] ?? 0);
        $status = $options['status'] ?? 'active';

        $fields = array_values(array_intersect(
            $options['fields'] ?? self::DEFAULT_FIELDS,
            self::AVAILABLE_FIELDS
        ));

        if (empty($fields)) {
            $fields = self::DEFAULT_FIELDS;
        }

        $query = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->where('subscribers.id', '>', $cursor)
            ->select([
                'subscribers.*',
                'pivot.status as membership_status',
                'pivot.source as membership_source',
                'pivot.subscribed_at as membership_subscribed_at',
                'pivot.confirmed_at as membership_confirmed_at',
                'pivot.unsubscribed_at as membership_unsubscribed_at',
            ])
            ->orderBy('subscribers.id')
            ->limit($limit + 1); // one extra row tells us whether more remain

        if ($status !== 'all') {
            $query->where('pivot.status', $status);
        }

        if (!empty($options['subscribed_after'])) {
            $query->where('pivot.subscribed_at', '>=', $options['subscribed_after']);
        }

        if (!empty($options['subscribed_before'])) {
            $query->where('pivot.subscribed_at', '<=', $options['subscribed_before']);
        }

        if (!empty($options['tag_ids'])) {
            $query->whereIn('subscribers.id', function ($q) use ($options) {
                $q->select('subscriber_id')
                    ->from('subscriber_tag')
                    ->whereIn('tag_id', $options['tag_ids']);
            });
        }

        if (array_key_exists('engaged', $options) && $options['engaged'] !== null) {
            if ($options['engaged']) {
                $query->where(function ($q) {
                    $q->where('subscribers.opens_count', '>', 0)
                        ->orWhere('subscribers.clicks_count', '>', 0);
                });
            } else {
                $query->where('subscribers.opens_count', 0)
                    ->where('subscribers.clicks_count', 0);
            }
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $needsTags = in_array('tags', $fields, true);
        $needsCustom = in_array('custom_fields', $fields, true);

        $tagsBySubscriber = [];
        $customBySubscriber = [];
        $ids = $rows->pluck('id')->all();

        if ($needsTags && !empty($ids)) {
            foreach (
                DB::table('subscriber_tag')
                    ->join('tags', 'tags.id', '=', 'subscriber_tag.tag_id')
                    ->whereIn('subscriber_tag.subscriber_id', $ids)
                    ->get(['subscriber_tag.subscriber_id', 'tags.name']) as $row
            ) {
                $tagsBySubscriber[$row->subscriber_id][] = $row->name;
            }
        }

        if ($needsCustom && !empty($ids)) {
            $fieldNames = CustomField::where('user_id', $list->user_id)->pluck('name', 'id');

            foreach (
                DB::table('subscriber_field_values')
                    ->whereIn('subscriber_id', $ids)
                    ->get(['subscriber_id', 'custom_field_id', 'value']) as $row
            ) {
                $name = $fieldNames[$row->custom_field_id] ?? null;
                if ($name !== null) {
                    $customBySubscriber[$row->subscriber_id][$name] = $row->value;
                }
            }
        }

        $records = $rows->map(function ($row) use ($fields, $tagsBySubscriber, $customBySubscriber) {
            $record = [];

            foreach ($fields as $field) {
                $record[$field] = match ($field) {
                    'status' => $row->membership_status,
                    'source' => $row->membership_source ?? $row->source,
                    'subscribed_at' => $row->membership_subscribed_at,
                    'confirmed_at' => $row->membership_confirmed_at,
                    'unsubscribed_at' => $row->membership_unsubscribed_at,
                    'tags' => $tagsBySubscriber[$row->id] ?? [],
                    'custom_fields' => $customBySubscriber[$row->id] ?? new \stdClass(),
                    default => $row->{$field} ?? null,
                };
            }

            return $record;
        })->all();

        $lastId = $rows->last()->id ?? null;

        $result = [
            'list' => ['id' => $list->id, 'name' => $list->name, 'type' => $list->type],
            'format' => $format,
            'fields' => $fields,
            'filters' => [
                'status' => $status,
                'tag_ids' => $options['tag_ids'] ?? [],
                'subscribed_after' => $options['subscribed_after'] ?? null,
                'subscribed_before' => $options['subscribed_before'] ?? null,
                'engaged' => $options['engaged'] ?? null,
            ],
            'count' => count($records),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? $lastId : null,
        ];

        if ($format === 'json') {
            $result['records'] = $records;
        } else {
            $result['data'] = $format === 'csv'
                ? $this->toCsv($records, $fields, $options['delimiter'] ?? ',')
                : $this->toNdjson($records);
        }

        return $result;
    }

    private function toCsv(array $records, array $fields, string $delimiter): string
    {
        $delimiter = $delimiter === 'tab' ? "\t" : substr($delimiter, 0, 1);
        $handle = fopen('php://temp', 'r+');

        // Explicit "" escape: standard CSV, and PHP 8.4+ deprecates the default.
        fputcsv($handle, $fields, $delimiter, '"', '');

        foreach ($records as $record) {
            fputcsv($handle, array_map(fn ($v) => $this->scalarize($v), array_values($record)), $delimiter, '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function toNdjson(array $records): string
    {
        return implode("\n", array_map(fn ($r) => json_encode($r, JSON_UNESCAPED_UNICODE), $records));
    }

    private function scalarize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode('|', $value);
        }

        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    /**
     * Field list for discovery, so a caller knows what it can ask for.
     */
    public function availableFields(ContactList $list): array
    {
        return [
            'standard' => self::AVAILABLE_FIELDS,
            'custom_fields' => CustomField::where('user_id', $list->user_id)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'label', 'type'])
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'label' => $f->label,
                    'type' => $f->type,
                ])->all(),
        ];
    }
}
