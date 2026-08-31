<?php

namespace App\Services\Lists;

use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming export of subscribers straight to the browser.
 *
 * The two exports that already existed serve someone else: ExportSubscribersCsv
 * queues a job and mails a link (fixed 8 columns, no filters), and
 * ListExportService answers an API caller with rows in a JSON body. Neither can
 * be clicked from the subscriber table, and neither round-trips — what comes out
 * cannot be edited and put back.
 *
 * This one does. Every column it writes is a column SubscriberFileImportService
 * can read back, header names are the technical field names the importer maps
 * one-to-one, and the `netsendo_id` column carries identity so a re-import
 * updates the same contact even when the address itself was corrected in the
 * spreadsheet.
 *
 * Rows are streamed in id-ordered chunks: a 200k-row export costs one chunk of
 * memory, not the whole list.
 */
class SubscriberExportService
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_CSV_EXCEL = 'csv_excel';
    public const FORMAT_TSV = 'tsv';
    public const FORMAT_JSON = 'json';
    public const FORMAT_NDJSON = 'ndjson';

    public const FORMATS = [
        self::FORMAT_CSV,
        self::FORMAT_CSV_EXCEL,
        self::FORMAT_TSV,
        self::FORMAT_JSON,
        self::FORMAT_NDJSON,
    ];

    /** Version of the on-disk shape, so a future format change stays detectable. */
    public const FORMAT_VERSION = 1;

    /** Rows loaded (and hydrated with tags/fields/lists) per pass. */
    private const CHUNK = 500;

    /**
     * Columns an export can contain. `cf:<name>` custom-field columns are added
     * on top of these per account.
     */
    public const COLUMNS = [
        'netsendo_id',
        'email',
        'phone',
        'first_name',
        'last_name',
        'gender',
        'language',
        'timezone',
        'source',
        'status',
        'list_status',
        'lists',
        'tags',
        'subscribed_at',
        'confirmed_at',
        'unsubscribed_at',
        'opens_count',
        'clicks_count',
        'last_opened_at',
        'last_clicked_at',
        'ip_address',
        'created_at',
    ];

    /**
     * Ready-made column sets. `custom` is resolved from the caller's field list.
     *
     * `netsendo` is the one that round-trips: it carries identity, every
     * editable attribute, list memberships with their per-list status, tags and
     * all custom fields.
     */
    public const PRESETS = [
        'basic' => ['email', 'first_name', 'last_name'],
        'contact' => ['email', 'phone', 'first_name', 'last_name', 'gender', 'language'],
        'marketing' => [
            'email', 'first_name', 'last_name', 'lists', 'tags', 'list_status',
            'subscribed_at', 'opens_count', 'clicks_count', 'last_opened_at', 'last_clicked_at',
        ],
        'netsendo' => [
            'netsendo_id', 'email', 'phone', 'first_name', 'last_name', 'gender', 'language',
            'timezone', 'source', 'status', 'list_status', 'lists', 'tags',
            'subscribed_at', 'confirmed_at', 'unsubscribed_at',
        ],
        'full' => self::COLUMNS,
        'custom' => [],
    ];

    /** Presets that always carry every custom field of the account. */
    private const PRESETS_WITH_CUSTOM_FIELDS = ['netsendo', 'full'];

    /**
     * Statuses recognised as a suffix in the `lists` column, so a list whose
     * name itself contains a colon is not mangled on the way back in.
     */
    public const MEMBERSHIP_STATUSES = ['active', 'unsubscribed', 'bounced', 'pending', 'inactive'];

    /**
     * Build the download.
     *
     * @param array $options {
     *     preset: basic|contact|marketing|netsendo|full|custom,
     *     fields: string[]            custom preset only,
     *     format: csv|csv_excel|tsv|json|ndjson,
     *     scope: filtered|selected,
     *     ids: int[]                  scope=selected,
     *     search: ?string, list_id: ?int, list_type: ?string,   scope=filtered
     *     membership: active|all|unsubscribed,
     *     date_format: iso|local,
     *     sort_by: ?string, sort_order: ?string
     * }
     */
    public function download(User $user, array $options = []): StreamedResponse
    {
        $format = in_array($options['format'] ?? null, self::FORMATS, true)
            ? $options['format']
            : self::FORMAT_CSV;

        $fields = $this->resolveFields($user, $options);
        $listId = $this->resolveListId($user, $options);
        $filename = $this->filename($user, $listId, $format);

        $headers = [
            'Content-Type' => $this->contentType($format),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            // Streamed body: no length is known up front, and a proxy must not
            // buffer it or a large export looks like a hung request.
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ];

        return response()->stream(function () use ($user, $options, $fields, $listId, $format) {
            $handle = fopen('php://output', 'w');

            match ($format) {
                self::FORMAT_JSON => $this->writeJson($handle, $user, $options, $fields, $listId),
                self::FORMAT_NDJSON => $this->writeNdjson($handle, $user, $options, $fields, $listId),
                default => $this->writeDelimited($handle, $user, $options, $fields, $listId, $format),
            };

            fclose($handle);
        }, 200, $headers);
    }

    // ========================================================================
    // Writers
    // ========================================================================

    private function writeDelimited(
        $handle,
        User $user,
        array $options,
        array $fields,
        ?int $listId,
        string $format
    ): void {
        $delimiter = $format === self::FORMAT_TSV ? "\t" : ($format === self::FORMAT_CSV_EXCEL ? ';' : ',');

        if ($format === self::FORMAT_CSV_EXCEL) {
            // Excel reads a CSV as the system code page unless the file opens
            // with a BOM; without it Polish characters arrive mojibaked.
            fwrite($handle, "\xEF\xBB\xBF");
        }

        // Escape is passed explicitly as "" — standard CSV has no backslash
        // escaping, and PHP 8.4+ deprecates relying on the legacy default.
        fputcsv($handle, $fields, $delimiter, '"', '');

        $this->eachRecord($user, $options, $fields, $listId, function (array $record) use ($handle, $delimiter) {
            fputcsv(
                $handle,
                array_map(fn ($value) => $this->scalarize($value), array_values($record)),
                $delimiter,
                '"',
                ''
            );
        });
    }

    private function writeJson($handle, User $user, array $options, array $fields, ?int $listId): void
    {
        // The envelope is written by hand so the records can stream instead of
        // being collected into one array first.
        fwrite($handle, "{\n  \"netsendo_export\": " . json_encode([
            'format_version' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'account' => $user->email,
            'list_id' => $listId,
            'fields' => $fields,
            'filters' => [
                'scope' => $options['scope'] ?? 'filtered',
                'search' => $options['search'] ?? null,
                'list_type' => $options['list_type'] ?? null,
                'membership' => $options['membership'] ?? 'active',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ",\n  \"subscribers\": [");

        $first = true;
        $this->eachRecord($user, $options, $fields, $listId, function (array $record) use ($handle, &$first) {
            fwrite($handle, ($first ? "\n    " : ",\n    ") . json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $first = false;
        });

        fwrite($handle, ($first ? '' : "\n  ") . "]\n}\n");
    }

    private function writeNdjson($handle, User $user, array $options, array $fields, ?int $listId): void
    {
        $this->eachRecord($user, $options, $fields, $listId, function (array $record) use ($handle) {
            fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        });
    }

    // ========================================================================
    // Row production
    // ========================================================================

    /**
     * Walk the matching subscribers in id order, hydrating one chunk at a time.
     */
    private function eachRecord(User $user, array $options, array $fields, ?int $listId, callable $emit): void
    {
        $dateFormat = ($options['date_format'] ?? 'iso') === 'local' ? 'local' : 'iso';
        $customFieldIds = $this->customFieldIdsByColumn($user, $fields);
        $wantsLists = in_array('lists', $fields, true);
        $wantsTags = in_array('tags', $fields, true);
        $wantsMembership = $listId !== null && $this->needsMembership($fields);

        $lastId = 0;

        while (true) {
            $rows = $this->baseQuery($user, $options, $listId)
                ->where('subscribers.id', '>', $lastId)
                ->orderBy('subscribers.id')
                ->limit(self::CHUNK)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $lastId = $rows->last()->id;
            $ids = $rows->pluck('id')->all();

            $listsBySubscriber = $wantsLists ? $this->listsFor($user, $ids) : [];
            $tagsBySubscriber = $wantsTags ? $this->tagsFor($ids) : [];
            $valuesBySubscriber = empty($customFieldIds) ? [] : $this->customValuesFor($ids, $customFieldIds);
            $membershipBySubscriber = $wantsMembership ? $this->membershipFor($listId, $ids) : [];

            foreach ($rows as $row) {
                $emit($this->buildRecord(
                    $row,
                    $fields,
                    $customFieldIds,
                    $listsBySubscriber[$row->id] ?? [],
                    $tagsBySubscriber[$row->id] ?? [],
                    $valuesBySubscriber[$row->id] ?? [],
                    $membershipBySubscriber[$row->id] ?? null,
                    $dateFormat
                ));
            }

            if ($rows->count() < self::CHUNK) {
                break;
            }
        }
    }

    /**
     * Subscribers the export may see, narrowed by the caller's selection.
     *
     * Visibility mirrors SubscriberController@index exactly: a subscriber shows
     * up only through a list the user can actually reach, so a team member never
     * exports an audience they cannot open in the interface.
     */
    private function baseQuery(User $user, array $options, ?int $listId)
    {
        $accessibleListIds = $user->accessibleLists()->pluck('id');
        $membership = in_array($options['membership'] ?? 'active', ['active', 'all', 'unsubscribed'], true)
            ? ($options['membership'] ?? 'active')
            : 'active';

        $query = DB::table('subscribers')
            ->whereNull('subscribers.deleted_at')
            ->whereExists(function ($q) use ($accessibleListIds, $listId, $membership) {
                $q->select(DB::raw(1))
                    ->from('contact_list_subscriber as visible')
                    ->whereColumn('visible.subscriber_id', 'subscribers.id')
                    ->whereIn('visible.contact_list_id', $accessibleListIds);

                if ($listId !== null) {
                    $q->where('visible.contact_list_id', $listId);
                }

                if ($membership === 'active') {
                    $q->where('visible.status', 'active');
                } elseif ($membership === 'unsubscribed') {
                    $q->whereIn('visible.status', ['unsubscribed', 'bounced']);
                }
            })
            ->select('subscribers.*');

        if (($options['scope'] ?? 'filtered') === 'selected') {
            $ids = array_values(array_filter(array_map('intval', $options['ids'] ?? [])));

            // An empty selection must export nothing, never everything.
            $query->whereIn('subscribers.id', $ids ?: [0]);

            return $query;
        }

        if (!empty($options['search'])) {
            $term = $options['search'];
            $query->where(function ($q) use ($term) {
                $q->where('subscribers.email', 'like', '%' . $term . '%')
                    ->orWhere('subscribers.first_name', 'like', '%' . $term . '%')
                    ->orWhere('subscribers.last_name', 'like', '%' . $term . '%')
                    ->orWhere('subscribers.phone', 'like', '%' . $term . '%');

                if (is_numeric($term)) {
                    $q->orWhere('subscribers.id', (int) $term);
                }
            });
        }

        if (!empty($options['list_type'])) {
            $query->whereExists(function ($q) use ($options, $accessibleListIds) {
                $q->select(DB::raw(1))
                    ->from('contact_list_subscriber as typed')
                    ->join('contact_lists', 'contact_lists.id', '=', 'typed.contact_list_id')
                    ->whereColumn('typed.subscriber_id', 'subscribers.id')
                    ->whereIn('typed.contact_list_id', $accessibleListIds)
                    ->where('contact_lists.type', $options['list_type']);
            });
        }

        return $query;
    }

    private function buildRecord(
        object $row,
        array $fields,
        array $customFieldIds,
        array $lists,
        array $tags,
        array $values,
        ?object $membership,
        string $dateFormat
    ): array {
        $record = [];

        foreach ($fields as $field) {
            if (isset($customFieldIds[$field])) {
                $record[$field] = $values[$customFieldIds[$field]] ?? '';
                continue;
            }

            $record[$field] = match ($field) {
                'netsendo_id' => $row->id,
                'status' => $row->is_active_global ? 'active' : 'inactive',
                'list_status' => $membership?->status ?? '',
                'lists' => $this->encodeLists($lists),
                'tags' => implode('|', $tags),
                'subscribed_at' => $this->date($membership->subscribed_at ?? $row->subscribed_at, $dateFormat),
                'confirmed_at' => $this->date($membership->confirmed_at ?? $row->confirmed_at, $dateFormat),
                'unsubscribed_at' => $this->date($membership->unsubscribed_at ?? null, $dateFormat),
                'last_opened_at' => $this->date($row->last_opened_at, $dateFormat),
                'last_clicked_at' => $this->date($row->last_clicked_at, $dateFormat),
                'created_at' => $this->date($row->created_at, $dateFormat),
                default => $row->{$field} ?? '',
            };
        }

        return $record;
    }

    // ========================================================================
    // Related data, loaded per chunk
    // ========================================================================

    /** @return array<int, array<int, array{name: string, status: string}>> */
    private function listsFor(User $user, array $ids): array
    {
        $accessibleListIds = $user->accessibleLists()->pluck('id');

        $rows = DB::table('contact_list_subscriber as pivot')
            ->join('contact_lists', 'contact_lists.id', '=', 'pivot.contact_list_id')
            ->whereIn('pivot.subscriber_id', $ids)
            ->whereIn('pivot.contact_list_id', $accessibleListIds)
            ->orderBy('contact_lists.name')
            ->get(['pivot.subscriber_id', 'pivot.status', 'contact_lists.name']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->subscriber_id][] = ['name' => $row->name, 'status' => $row->status];
        }

        return $out;
    }

    /** @return array<int, string[]> */
    private function tagsFor(array $ids): array
    {
        $rows = DB::table('subscriber_tag')
            ->join('tags', 'tags.id', '=', 'subscriber_tag.tag_id')
            ->whereIn('subscriber_tag.subscriber_id', $ids)
            ->orderBy('tags.name')
            ->get(['subscriber_tag.subscriber_id', 'tags.name']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->subscriber_id][] = $row->name;
        }

        return $out;
    }

    /** @return array<int, array<int, string>> */
    private function customValuesFor(array $ids, array $customFieldIds): array
    {
        $rows = DB::table('subscriber_field_values')
            ->whereIn('subscriber_id', $ids)
            ->whereIn('custom_field_id', array_values($customFieldIds))
            ->get(['subscriber_id', 'custom_field_id', 'value']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->subscriber_id][$row->custom_field_id] = $row->value;
        }

        return $out;
    }

    /** @return array<int, object> */
    private function membershipFor(int $listId, array $ids): array
    {
        return DB::table('contact_list_subscriber')
            ->where('contact_list_id', $listId)
            ->whereIn('subscriber_id', $ids)
            ->get(['subscriber_id', 'status', 'subscribed_at', 'confirmed_at', 'unsubscribed_at'])
            ->keyBy('subscriber_id')
            ->all();
    }

    // ========================================================================
    // Field resolution
    // ========================================================================

    /**
     * The final ordered column list for this export.
     */
    public function resolveFields(User $user, array $options): array
    {
        $preset = $options['preset'] ?? 'netsendo';
        $customColumns = $this->customFields($user)->map(fn ($f) => 'cf:' . $f->name)->all();

        if ($preset === 'custom') {
            $allowed = array_merge(self::COLUMNS, $customColumns);
            $fields = array_values(array_intersect($options['fields'] ?? [], $allowed));

            // A selection that maps to nothing usable would produce an empty
            // file; fall back to the identity columns instead.
            return $fields ?: self::PRESETS['basic'];
        }

        $fields = self::PRESETS[$preset] ?? self::PRESETS['netsendo'];

        if (in_array($preset, self::PRESETS_WITH_CUSTOM_FIELDS, true)) {
            $fields = array_merge($fields, $customColumns);
        }

        return array_values($fields);
    }

    /**
     * Map `cf:<name>` columns onto custom field ids for the current account.
     *
     * @return array<string, int>
     */
    private function customFieldIdsByColumn(User $user, array $fields): array
    {
        $byName = $this->customFields($user)->keyBy(fn ($f) => 'cf:' . $f->name);
        $out = [];

        foreach ($fields as $field) {
            if (isset($byName[$field])) {
                $out[$field] = $byName[$field]->id;
            }
        }

        return $out;
    }

    private function customFields(User $user)
    {
        return CustomField::where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'label', 'type']);
    }

    /**
     * Columns the export offers, ready for the picker in the interface.
     */
    public function columnCatalogue(User $user): array
    {
        return [
            'standard' => self::COLUMNS,
            'custom_fields' => $this->customFields($user)
                ->map(fn ($f) => ['key' => 'cf:' . $f->name, 'name' => $f->name, 'label' => $f->label])
                ->values()
                ->all(),
            'presets' => array_keys(self::PRESETS),
            'formats' => self::FORMATS,
        ];
    }

    // ========================================================================
    // Encoding helpers
    // ========================================================================

    /**
     * `Newsletter:active|Klienci:unsubscribed`
     *
     * The status is a suffix rather than its own column because a subscriber
     * can sit on many lists with a different status on each, and a spreadsheet
     * has one cell. The importer only treats the last colon-separated token as
     * a status when it is a known one, so a list literally named "Oferta: lato"
     * survives the round trip.
     */
    private function encodeLists(array $lists): string
    {
        return implode('|', array_map(
            fn ($entry) => $entry['name'] . ':' . $entry['status'],
            $lists
        ));
    }

    private function date(mixed $value, string $dateFormat): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);
        } catch (\Throwable $e) {
            return (string) $value;
        }

        if ($dateFormat === 'local') {
            return $date->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
        }

        return $date->toIso8601String();
    }

    private function scalarize(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if (is_array($value)) {
            return implode('|', $value);
        }

        return (string) $value;
    }

    private function needsMembership(array $fields): bool
    {
        return (bool) array_intersect(
            $fields,
            ['list_status', 'subscribed_at', 'confirmed_at', 'unsubscribed_at']
        );
    }

    /**
     * The list the export is scoped to.
     *
     * A list the caller cannot reach resolves to 0 rather than null: null means
     * "no list asked for" and would widen the export to every accessible list,
     * which is the opposite of what a denied request should do.
     */
    private function resolveListId(User $user, array $options): ?int
    {
        $listId = (int) ($options['list_id'] ?? 0);

        if ($listId <= 0) {
            return null;
        }

        return $user->accessibleLists()->where('contact_lists.id', $listId)->exists() ? $listId : 0;
    }

    private function filename(User $user, ?int $listId, string $format): string
    {
        $extension = match ($format) {
            self::FORMAT_JSON => 'json',
            self::FORMAT_NDJSON => 'ndjson',
            self::FORMAT_TSV => 'tsv',
            default => 'csv',
        };

        $slug = 'all';

        if ($listId !== null) {
            $name = ContactList::where('id', $listId)->value('name');
            $slug = $name ? \Illuminate\Support\Str::slug($name) : 'list-' . $listId;
        }

        return sprintf('netsendo_subscribers_%s_%s.%s', $slug ?: 'list', now()->format('Y-m-d_His'), $extension);
    }

    private function contentType(string $format): string
    {
        return match ($format) {
            self::FORMAT_JSON => 'application/json; charset=UTF-8',
            self::FORMAT_NDJSON => 'application/x-ndjson; charset=UTF-8',
            self::FORMAT_TSV => 'text/tab-separated-values; charset=UTF-8',
            default => 'text/csv; charset=UTF-8',
        };
    }
}
