<?php

namespace App\Services\Lists;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\GenderService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

/**
 * File import for the subscriber screen — the read side of
 * SubscriberExportService.
 *
 * The importer this replaces split the file on "\n" and ran str_getcsv per
 * line, so a quoted field containing a newline (any address book exported from
 * a CRM has them) tore one contact into two broken rows. It also understood
 * five columns only: everything else the product stores — gender, timezone,
 * source, tags, list memberships, per-list status — could be exported by an API
 * caller and never put back.
 *
 * Two properties matter more than the extra columns:
 *
 *  1. **Identity.** With a `netsendo_id` column a re-import updates the same
 *     contact even when the address itself was corrected in the spreadsheet,
 *     instead of creating a second contact and orphaning the first.
 *
 *  2. **Idempotence.** SubscriberSignedUp resets a subscriber's autoresponder
 *     entries (CreateAutoresponderQueueEntries, reset_autoresponders_on_resubscription
 *     defaults to true), and the old importer fired it for every row — so
 *     re-uploading an unchanged file restarted every sequence on the list. By
 *     default the event now fires only where a membership was actually created
 *     or reactivated, which is what makes export → edit → import safe to repeat.
 */
class SubscriberFileImportService
{
    /** Rows processed in one request. Beyond this the file must be split. */
    public const MAX_ROWS = 50000;

    /** Rows inspected for the dry run. */
    public const PREVIEW_ROWS = 2000;

    public const UPDATE_FILL_EMPTY = 'fill_empty';
    public const UPDATE_OVERWRITE = 'overwrite';
    public const UPDATE_SKIP = 'skip';

    public const UPDATE_MODES = [self::UPDATE_FILL_EMPTY, self::UPDATE_OVERWRITE, self::UPDATE_SKIP];

    public const SIGNUP_NEW_ONLY = 'new_only';
    public const SIGNUP_ALL = 'all';
    public const SIGNUP_NONE = 'none';

    public const SIGNUP_MODES = [self::SIGNUP_NEW_ONLY, self::SIGNUP_ALL, self::SIGNUP_NONE];

    /** Columns a mapping may target, on top of `custom_field:<id>` and `ignore`. */
    public const MAPPABLE_FIELDS = [
        'netsendo_id', 'email', 'phone', 'first_name', 'last_name', 'gender', 'language',
        'timezone', 'source', 'status', 'list_status', 'lists', 'tags',
        'subscribed_at', 'confirmed_at', 'unsubscribed_at',
    ];

    /**
     * Header names recognised without an explicit mapping (PL/EN/DE/ES).
     *
     * The first alias of each row is exactly what SubscriberExportService
     * writes, so an exported file needs no mapping at all.
     */
    public const HEADER_ALIASES = [
        'netsendo_id' => ['netsendo_id', 'id', 'subscriber_id', 'id_subskrybenta'],
        'email' => ['email', 'e-mail', 'e_mail', 'mail', 'adres email', 'adres e-mail', 'correo'],
        'phone' => ['phone', 'telefon', 'tel', 'mobile', 'komorka', 'komórka', 'phone_number', 'numer_telefonu', 'numer', 'telefono'],
        'first_name' => ['first_name', 'firstname', 'first name', 'imie', 'imię', 'name', 'nazwa', 'vorname', 'nombre'],
        'last_name' => ['last_name', 'lastname', 'last name', 'nazwisko', 'surname', 'nachname', 'apellido'],
        'gender' => ['gender', 'plec', 'płeć', 'sex', 'geschlecht', 'genero'],
        'language' => ['language', 'lang', 'jezyk', 'język', 'locale', 'sprache', 'idioma'],
        'timezone' => ['timezone', 'time_zone', 'strefa_czasowa', 'tz'],
        'source' => ['source', 'zrodlo', 'źródło', 'quelle', 'fuente'],
        'status' => ['status', 'status_globalny'],
        'list_status' => ['list_status', 'status_na_liscie', 'status_na_liście'],
        'lists' => ['lists', 'listy', 'contact_lists'],
        'tags' => ['tags', 'tagi', 'etykiety'],
        'subscribed_at' => ['subscribed_at', 'data_zapisu', 'data_dolaczenia', 'data_dołączenia'],
        'confirmed_at' => ['confirmed_at', 'data_potwierdzenia'],
        'unsubscribed_at' => ['unsubscribed_at', 'data_wypisania'],
    ];

    public function __construct(
        protected GenderService $genderService,
    ) {}

    // ========================================================================
    // Reading
    // ========================================================================

    /**
     * Read the file into normalised rows.
     *
     * @param array $options {
     *     separator: ','|';'|'tab',
     *     has_header: ?bool,
     *     column_mapping: ?array   column index (or header name for JSON) => field
     * }
     * @return array{rows: array, columns: array, header: ?array, mapping: array,
     *               is_netsendo_format: bool, warnings: array, total_rows: int}
     */
    public function parse(UploadedFile $file, User $user, array $options = []): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $content = (string) file_get_contents($file->getRealPath());
        $content = $this->stripBom($content);

        if (trim($content) === '') {
            throw new \InvalidArgumentException(__('subscribers.import.errors.empty_file'));
        }

        if ($extension === 'json' || $this->looksLikeJson($content)) {
            return $this->parseJson($content, $user, $options);
        }

        if ($extension === 'ndjson' || $extension === 'jsonl') {
            return $this->parseNdjson($content, $user, $options);
        }

        return $this->parseDelimited($content, $user, $options, $extension);
    }

    private function parseDelimited(string $content, User $user, array $options, string $extension): array
    {
        $separator = $options['separator'] ?? null;
        $delimiter = $this->resolveDelimiter($separator, $extension, $content);

        $reader = Reader::createFromString($content);
        $reader->setDelimiter($delimiter);
        $reader->setEnclosure('"');
        // Standard CSV has no backslash escaping; keeping the legacy default
        // would mangle values ending in "\" and is deprecated from PHP 8.4.
        $reader->setEscape('');

        $records = [];
        foreach ($reader->getRecords() as $record) {
            $records[] = array_values($record);

            if (count($records) > self::MAX_ROWS + 1) {
                break;
            }
        }

        if (empty($records)) {
            throw new \InvalidArgumentException(__('subscribers.import.errors.empty_file'));
        }

        $hasHeader = $options['has_header'] ?? null;
        if ($hasHeader === null) {
            $hasHeader = !$this->looksLikeDataRow($records[0]);
        }

        $header = $hasHeader ? array_map(fn ($h) => trim((string) $h), $records[0]) : null;
        $startRow = $hasHeader ? 1 : 0;

        $mapping = $this->resolveMapping(
            $options['column_mapping'] ?? null,
            $header,
            $records[$startRow] ?? [],
            $user
        );

        $warnings = [];
        $rows = [];

        for ($i = $startRow; $i < count($records); $i++) {
            if (count($rows) >= self::MAX_ROWS) {
                $warnings[] = __('subscribers.import.errors.truncated', ['limit' => self::MAX_ROWS]);
                break;
            }

            $row = $this->buildRow($records[$i], $mapping, count($rows) + 1);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'columns' => $header ?? $this->positionalColumnNames($records[0]),
            'header' => $header,
            'mapping' => $mapping,
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
            'is_netsendo_format' => $this->isNetsendoFormat($mapping),
            'warnings' => $warnings,
            'total_rows' => count($rows),
        ];
    }

    private function parseJson(string $content, User $user, array $options): array
    {
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException(__('subscribers.import.errors.invalid_json'));
        }

        // Both shapes are accepted: the envelope this app exports, and a bare
        // array of objects from anywhere else.
        $records = $decoded['subscribers'] ?? $decoded;

        if (!is_array($records) || empty($records)) {
            throw new \InvalidArgumentException(__('subscribers.import.errors.empty_file'));
        }

        return $this->parseRecords(array_values($records), $user, $options);
    }

    private function parseNdjson(string $content, User $user, array $options): array
    {
        $records = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        if (empty($records)) {
            throw new \InvalidArgumentException(__('subscribers.import.errors.invalid_json'));
        }

        return $this->parseRecords($records, $user, $options);
    }

    /**
     * Records keyed by field name (JSON / NDJSON).
     */
    private function parseRecords(array $records, User $user, array $options): array
    {
        $keys = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $keys = array_unique(array_merge($keys, array_keys($record)));
            }
        }

        $mapping = $this->resolveMapping(
            $options['column_mapping'] ?? null,
            array_combine($keys, $keys) ?: [],
            [],
            $user,
            keyByName: true
        );

        $rows = [];
        $warnings = [];

        foreach ($records as $record) {
            if (count($rows) >= self::MAX_ROWS) {
                $warnings[] = __('subscribers.import.errors.truncated', ['limit' => self::MAX_ROWS]);
                break;
            }

            if (!is_array($record)) {
                continue;
            }

            $row = $this->buildRowFromAssoc($record, $mapping, count($rows) + 1);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'columns' => array_values($keys),
            'header' => array_values($keys),
            'mapping' => $mapping,
            'delimiter' => null,
            'is_netsendo_format' => $this->isNetsendoFormat($mapping),
            'warnings' => $warnings,
            'total_rows' => count($rows),
        ];
    }

    // ========================================================================
    // Mapping
    // ========================================================================

    /**
     * Column index (or header name) => target field.
     *
     * Explicit mapping wins; otherwise headers are matched against the alias
     * table and the account's custom fields; otherwise columns are guessed from
     * the first data row.
     */
    private function resolveMapping(
        ?array $explicit,
        ?array $header,
        array $sampleRow,
        User $user,
        bool $keyByName = false
    ): array {
        $customFields = CustomField::where('user_id', $user->id)->get(['id', 'name', 'label']);
        $mapping = [];

        if (!empty($explicit)) {
            foreach ($explicit as $column => $field) {
                $field = is_string($field) ? trim($field) : '';

                if ($field === '' || $field === 'ignore') {
                    continue;
                }

                $resolved = $this->resolveFieldName($field, $customFields);

                if ($resolved !== null) {
                    $mapping[$keyByName ? mb_strtolower((string) $column) : (int) $column] = $resolved;
                }
            }

            return $mapping;
        }

        if (!empty($header)) {
            foreach ($header as $index => $name) {
                $normalized = mb_strtolower(trim((string) $name));

                if ($normalized === '') {
                    continue;
                }

                $columnKey = ($keyByName || !is_int($index)) ? $normalized : $index;

                // `cf:miasto` — the shape the export writes for custom fields.
                if (str_starts_with($normalized, 'cf:')) {
                    $custom = $customFields->first(
                        fn ($f) => mb_strtolower($f->name) === mb_substr($normalized, 3)
                    );

                    if ($custom) {
                        $mapping[$columnKey] = 'custom_field:' . $custom->id;
                        continue;
                    }
                }

                foreach (self::HEADER_ALIASES as $field => $aliases) {
                    if (in_array($normalized, $aliases, true)) {
                        $mapping[$columnKey] = $field;
                        continue 2;
                    }
                }

                $custom = $customFields->first(
                    fn ($f) => mb_strtolower($f->name) === $normalized || mb_strtolower((string) $f->label) === $normalized
                );

                if ($custom) {
                    $mapping[$columnKey] = 'custom_field:' . $custom->id;
                }
            }

            if (!empty($mapping)) {
                return $mapping;
            }
        }

        // Headerless file: guess from the first data row.
        foreach ($sampleRow as $index => $value) {
            $value = trim((string) $value);

            if ($value !== '' && str_contains($value, '@')) {
                $mapping[$index] = 'email';
            } elseif ($value !== '' && preg_match('/^\+?[0-9 ()-]{9,20}$/', $value)) {
                $mapping[$index] = 'phone';
            }
        }

        if (!isset($mapping[0])) {
            $mapping[0] = 'email';
        }

        if (!in_array('first_name', $mapping, true) && count($sampleRow) > 1) {
            $mapping[1] = 'first_name';
        }

        if (!in_array('last_name', $mapping, true) && count($sampleRow) > 2) {
            $mapping[2] = 'last_name';
        }

        return $mapping;
    }

    /**
     * Accepts a core field name, `custom_field:12`, `cf:miasto` or a custom
     * field's own name/label.
     */
    private function resolveFieldName(string $field, $customFields): ?string
    {
        if (in_array($field, self::MAPPABLE_FIELDS, true)) {
            return $field;
        }

        if (str_starts_with($field, 'custom_field:')) {
            $id = (int) substr($field, strlen('custom_field:'));

            return $customFields->contains('id', $id) ? 'custom_field:' . $id : null;
        }

        $normalized = mb_strtolower($field);

        if (str_starts_with($normalized, 'cf:')) {
            $normalized = mb_substr($normalized, 3);
        }

        foreach (self::HEADER_ALIASES as $target => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $target;
            }
        }

        $custom = $customFields->first(
            fn ($f) => mb_strtolower($f->name) === $normalized || mb_strtolower((string) $f->label) === $normalized
        );

        return $custom ? 'custom_field:' . $custom->id : null;
    }

    /**
     * A file carrying identity plus at least one relational column is one of
     * ours: the interface can then offer the round-trip options up front.
     */
    private function isNetsendoFormat(array $mapping): bool
    {
        $fields = array_values($mapping);

        return in_array('netsendo_id', $fields, true)
            && (bool) array_intersect($fields, ['lists', 'tags', 'list_status', 'status']);
    }

    // ========================================================================
    // Row normalisation
    // ========================================================================

    private function buildRow(array $record, array $mapping, int $rowNumber): ?array
    {
        $assoc = [];

        foreach ($mapping as $index => $field) {
            $assoc[$field] = $record[$index] ?? null;
        }

        return $this->normalizeRow($assoc, $rowNumber);
    }

    private function buildRowFromAssoc(array $record, array $mapping, int $rowNumber): ?array
    {
        $lowered = [];
        foreach ($record as $key => $value) {
            $lowered[mb_strtolower((string) $key)] = $value;
        }

        $assoc = [];
        foreach ($mapping as $key => $field) {
            $assoc[$field] = $lowered[is_int($key) ? $key : mb_strtolower((string) $key)] ?? null;
        }

        return $this->normalizeRow($assoc, $rowNumber);
    }

    /**
     * @return array|null null when the row carries nothing at all
     */
    private function normalizeRow(array $assoc, int $rowNumber): ?array
    {
        $custom = [];

        foreach ($assoc as $field => $value) {
            if (str_starts_with((string) $field, 'custom_field:')) {
                $value = $this->text($value);

                if ($value !== null) {
                    $custom[(int) substr((string) $field, strlen('custom_field:'))] = $value;
                }
            }
        }

        $email = $this->text($assoc['email'] ?? null);
        $phone = $this->text($assoc['phone'] ?? null);

        $row = [
            'row' => $rowNumber,
            'netsendo_id' => $this->positiveInt($assoc['netsendo_id'] ?? null),
            'email' => $email !== null ? mb_strtolower($email) : null,
            'phone' => $phone,
            'first_name' => $this->text($assoc['first_name'] ?? null),
            'last_name' => $this->text($assoc['last_name'] ?? null),
            'gender' => $this->normalizeGender($assoc['gender'] ?? null),
            'language' => $this->normalizeLanguage($assoc['language'] ?? null),
            'timezone' => $this->text($assoc['timezone'] ?? null),
            'source' => $this->text($assoc['source'] ?? null),
            'status' => $this->normalizeGlobalStatus($assoc['status'] ?? null),
            'list_status' => $this->normalizeMembershipStatus($assoc['list_status'] ?? null),
            'lists' => $this->decodeLists($assoc['lists'] ?? null),
            'tags' => $this->decodeTags($assoc['tags'] ?? null),
            'subscribed_at' => $this->date($assoc['subscribed_at'] ?? null),
            'confirmed_at' => $this->date($assoc['confirmed_at'] ?? null),
            'unsubscribed_at' => $this->date($assoc['unsubscribed_at'] ?? null),
            'custom' => $custom,
        ];

        $carriesSomething = $row['email'] !== null
            || $row['phone'] !== null
            || $row['netsendo_id'] !== null
            || $row['first_name'] !== null
            || $row['last_name'] !== null;

        return $carriesSomething ? $row : null;
    }

    // ========================================================================
    // Dry run
    // ========================================================================

    /**
     * What an import would do, without writing anything.
     */
    public function preview(User $user, ?ContactList $list, array $parsed, array $options = []): array
    {
        $isSms = $list?->type === 'sms';
        $summary = array_fill_keys(['total', 'create', 'update', 'reactivate', 'unchanged', 'invalid'], 0);
        $sample = [];
        $problems = [];
        $seen = [];

        foreach (array_slice($parsed['rows'], 0, self::PREVIEW_ROWS) as $row) {
            $summary['total']++;

            $problem = $this->rowProblem($row, $isSms);
            $key = $this->dedupeKey($row, $isSms);

            if ($problem === null && $key !== null && isset($seen[$key])) {
                $problem = 'duplicate_in_file';
            }

            if ($problem !== null) {
                $summary['invalid']++;

                if (count($problems) < 25) {
                    $problems[] = ['row' => $row['row'], 'email' => $row['email'], 'phone' => $row['phone'], 'reason' => $problem];
                }

                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }

            $subscriber = $this->findSubscriber($user, $row, $isSms);
            $action = 'create';

            if ($subscriber) {
                if ($subscriber->trashed()) {
                    $action = 'reactivate';
                } elseif ($list && $this->membershipStatus($subscriber->id, $list->id) === 'active') {
                    $action = ($options['update_mode'] ?? self::UPDATE_FILL_EMPTY) === self::UPDATE_SKIP
                        ? 'unchanged'
                        : 'update';
                } else {
                    $action = 'update';
                }
            }

            $summary[$action]++;

            if (count($sample) < 15) {
                $sample[] = [
                    'row' => $row['row'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'tags' => $row['tags'],
                    'lists' => array_column($row['lists'] ?? [], 'name'),
                    'action' => $action,
                ];
            }
        }

        return [
            'detected' => [
                'columns' => $parsed['columns'],
                'header' => $parsed['header'],
                'delimiter' => $parsed['delimiter'] ?? null,
                'mapping' => $this->describeMapping($parsed['mapping'], $user->id),
                'is_netsendo_format' => $parsed['is_netsendo_format'],
            ],
            'total_rows' => $parsed['total_rows'],
            'inspected_rows' => $summary['total'],
            'summary' => $summary,
            'sample' => $sample,
            'problem_rows' => $problems,
            'warnings' => $parsed['warnings'],
        ];
    }

    // ========================================================================
    // Import
    // ========================================================================

    /**
     * Apply the import.
     *
     * @param ContactList|null $list target list; may be null only when
     *                               restore_memberships is on and the file
     *                               carries a `lists` column
     * @param array $options {
     *     update_mode: fill_empty|overwrite|skip,
     *     signup_events: new_only|all|none,
     *     restore_memberships: bool,
     *     create_missing_lists: bool,
     *     apply_tags: bool,
     *     replace_tags: bool,
     *     detect_gender: bool,
     *     apply_dates: bool          honour subscribed_at/confirmed_at from the file
     * }
     * @return array counters plus the first errors encountered
     */
    public function import(User $user, ?ContactList $list, array $parsed, array $options = []): array
    {
        $updateMode = in_array($options['update_mode'] ?? null, self::UPDATE_MODES, true)
            ? $options['update_mode']
            : self::UPDATE_FILL_EMPTY;
        $signupEvents = in_array($options['signup_events'] ?? null, self::SIGNUP_MODES, true)
            ? $options['signup_events']
            : self::SIGNUP_NEW_ONLY;
        $restoreMemberships = (bool) ($options['restore_memberships'] ?? false);
        $createMissingLists = (bool) ($options['create_missing_lists'] ?? false);
        $applyTags = (bool) ($options['apply_tags'] ?? true);
        $replaceTags = (bool) ($options['replace_tags'] ?? false);
        $detectGender = (bool) ($options['detect_gender'] ?? true);
        $applyDates = (bool) ($options['apply_dates'] ?? false);

        $isSms = $list?->type === 'sms';

        // Built once, by hand: compact() cannot be used inside the arrow
        // function below — auto-capture only binds variables it sees written
        // out, not names passed as strings.
        $flags = [
            'updateMode' => $updateMode,
            'restoreMemberships' => $restoreMemberships,
            'createMissingLists' => $createMissingLists,
            'applyTags' => $applyTags,
            'replaceTags' => $replaceTags,
            'detectGender' => $detectGender,
            'applyDates' => $applyDates,
        ];

        $results = array_fill_keys(
            ['total', 'created', 'updated', 'reactivated', 'unchanged', 'invalid', 'failed'],
            0
        );
        $results['errors'] = [];
        $results['signups'] = 0;

        $listCache = [];
        $tagCache = [];
        $seen = [];
        // Events fire after the transaction of their row, so a listener never
        // reads a half-written subscriber.
        $pendingSignups = [];

        foreach ($parsed['rows'] as $row) {
            $results['total']++;

            $problem = $this->rowProblem($row, $isSms);
            $key = $this->dedupeKey($row, $isSms);

            if ($problem === null && $key !== null && isset($seen[$key])) {
                $problem = 'duplicate_in_file';
            }

            if ($problem !== null) {
                $results['invalid']++;
                $this->recordError($results, $row, $problem);
                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }

            try {
                // A full closure, not an arrow function: the caches are passed
                // by reference and auto-capture would hand each row its own
                // copy, so nothing would ever accumulate.
                $outcome = DB::transaction(function () use ($user, $list, $row, $isSms, $flags, &$listCache, &$tagCache) {
                    return $this->importRow($user, $list, $row, $isSms, $flags, $listCache, $tagCache);
                });
            } catch (\Throwable $e) {
                $results['failed']++;
                $this->recordError($results, $row, 'exception', $e->getMessage());

                Log::error('Subscriber import row failed', [
                    'row' => $row['row'],
                    'email' => $row['email'],
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $results[$outcome['action']]++;

            foreach ($outcome['signups'] as $signup) {
                // "all" replays every row as a fresh signup — the pre-2.2
                // behaviour, kept for people who deliberately want sequences to
                // restart. "new_only" (default) fires only where the membership
                // was created or reactivated, so re-importing the same file
                // changes nothing.
                if ($signupEvents === self::SIGNUP_NONE) {
                    continue;
                }

                if ($signupEvents === self::SIGNUP_NEW_ONLY && !$signup['is_new']) {
                    continue;
                }

                $pendingSignups[] = $signup;
            }
        }

        foreach ($pendingSignups as $signup) {
            event(new SubscriberSignedUp($signup['subscriber'], $signup['list'], null, 'import'));
            $results['signups']++;
        }

        return $results;
    }

    /**
     * One row, inside its own transaction.
     *
     * @return array{action: string, signups: array}
     */
    private function importRow(
        User $user,
        ?ContactList $list,
        array $row,
        bool $isSms,
        array $flags,
        array &$listCache,
        array &$tagCache
    ): array {
        $subscriber = $this->findSubscriber($user, $row, $isSms);
        $action = 'created';
        $wasTrashed = false;

        if ($subscriber && $subscriber->trashed()) {
            // Same reset the previous importer performed: a restored contact
            // must not silently reappear on the lists they were removed from,
            // and their old queue entries must not resume mid-sequence.
            $subscriber->restore();
            $subscriber->contactLists()->detach();
            MessageQueueEntry::where('subscriber_id', $subscriber->id)->delete();

            Log::info('Restored and reset soft-deleted subscriber during import', [
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
            ]);

            $wasTrashed = true;
            $action = 'reactivated';
        } elseif ($subscriber) {
            $action = 'updated';
        }

        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'user_id' => $user->id,
                'email' => $row['email'],
                'phone' => $row['phone'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'gender' => $row['gender'],
                'language' => $row['language'],
                'timezone' => $row['timezone'],
                'source' => $row['source'] ?? 'import',
                'is_active_global' => $row['status'] !== 'inactive',
            ]);
        } elseif ($flags['updateMode'] !== self::UPDATE_SKIP || $wasTrashed) {
            $this->applyAttributes($subscriber, $row, $user, $wasTrashed ? self::UPDATE_OVERWRITE : $flags['updateMode']);
        } else {
            $action = 'unchanged';
        }

        if ($flags['detectGender'] && empty($subscriber->gender) && !empty($subscriber->first_name)) {
            $detected = $this->genderService->detectGender($subscriber->first_name, 'PL', $user->id);

            if ($detected) {
                $subscriber->gender = $detected;
                $subscriber->save();
            }
        }

        if (!empty($row['custom']) && $action !== 'unchanged') {
            foreach ($row['custom'] as $fieldId => $value) {
                $subscriber->fieldValues()->updateOrCreate(
                    ['custom_field_id' => $fieldId],
                    ['value' => $value]
                );
            }
        }

        if ($flags['applyTags'] && $row['tags'] !== null && $action !== 'unchanged') {
            $this->applyTags($subscriber, $row['tags'], $user, $flags['replaceTags'], $tagCache);
        }

        $signups = [];

        if ($list) {
            $signup = $this->attachToList(
                $subscriber,
                $list,
                $row['list_status'] ?? 'active',
                $row,
                $flags['applyDates']
            );

            if ($signup !== null) {
                $signups[] = $signup;
            }
        }

        if ($flags['restoreMemberships'] && !empty($row['lists'])) {
            foreach ($row['lists'] as $entry) {
                $target = $this->resolveList($user, $entry['name'], $flags['createMissingLists'], $listCache);

                if (!$target || ($list && $target->id === $list->id)) {
                    continue;
                }

                $signup = $this->attachToList($subscriber, $target, $entry['status'], $row, $flags['applyDates']);

                if ($signup !== null) {
                    $signups[] = $signup;
                }
            }
        }

        return ['action' => $action, 'signups' => $signups];
    }

    /**
     * Write the row's attributes onto an existing contact.
     *
     * `fill_empty` only fills blanks (what the old importer did, so an import
     * could never wipe better data). `overwrite` is what a corrected export
     * needs: the spreadsheet is the source of truth.
     */
    private function applyAttributes(Subscriber $subscriber, array $row, User $user, string $updateMode): void
    {
        $overwrite = $updateMode === self::UPDATE_OVERWRITE;
        $update = [];

        foreach (['phone', 'first_name', 'last_name', 'gender', 'language', 'timezone', 'source'] as $field) {
            if ($row[$field] === null) {
                continue;
            }

            if ($overwrite || blank($subscriber->{$field})) {
                $update[$field] = $row[$field];
            }
        }

        if ($row['email'] !== null && $row['email'] !== mb_strtolower((string) $subscriber->email)) {
            if ($overwrite || blank($subscriber->email)) {
                // A corrected address must not collide with another contact of
                // the same account — the unique index would abort the row.
                $taken = Subscriber::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('email', $row['email'])
                    ->where('id', '!=', $subscriber->id)
                    ->exists();

                if (!$taken) {
                    $update['email'] = $row['email'];
                }
            }
        }

        if ($row['status'] !== null && $overwrite) {
            $update['is_active_global'] = $row['status'] === 'active';
        }

        if (!empty($update)) {
            $subscriber->update($update);
        }
    }

    /**
     * Put the contact on a list, honouring the list's resubscription behaviour.
     *
     * @return array{subscriber: Subscriber, list: ContactList, is_new: bool}|null
     *         the signup to announce, or null when nothing became active
     */
    private function attachToList(
        Subscriber $subscriber,
        ContactList $list,
        string $status,
        array $row,
        bool $applyDates
    ): ?array {
        $status = in_array($status, SubscriberExportService::MEMBERSHIP_STATUSES, true) ? $status : 'active';
        $existing = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $list->id)
            ->where('subscriber_id', $subscriber->id)
            ->first();

        $wasActive = $existing && $existing->status === 'active';

        $pivot = [
            'status' => $status,
            'unsubscribed_at' => $status === 'active' ? null : ($existing->unsubscribed_at ?? now()),
        ];

        if ($status !== 'active' && $applyDates && $row['unsubscribed_at'] !== null) {
            $pivot['unsubscribed_at'] = $row['unsubscribed_at'];
        }

        if ($applyDates && $row['confirmed_at'] !== null) {
            $pivot['confirmed_at'] = $row['confirmed_at'];
        }

        if ($existing) {
            // A membership that is already active keeps its signup date unless
            // the list is configured to reset it — restarting the clock would
            // move every autoresponder offset for that contact.
            $shouldResetDate = !$wasActive && $status === 'active'
                && ($list->resubscription_behavior ?? 'reset_date') === 'reset_date';

            if ($applyDates && $row['subscribed_at'] !== null) {
                $pivot['subscribed_at'] = $row['subscribed_at'];
            } elseif ($shouldResetDate) {
                $pivot['subscribed_at'] = now();
            }

            DB::table('contact_list_subscriber')
                ->where('id', $existing->id)
                ->update($pivot + ['updated_at' => now()]);
        } else {
            $pivot['subscribed_at'] = ($applyDates && $row['subscribed_at'] !== null)
                ? $row['subscribed_at']
                : now();

            DB::table('contact_list_subscriber')->insert($pivot + [
                'contact_list_id' => $list->id,
                'subscriber_id' => $subscriber->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($status !== 'active') {
            return null;
        }

        return [
            'subscriber' => $subscriber,
            'list' => $list,
            'is_new' => !$wasActive,
        ];
    }

    private function applyTags(Subscriber $subscriber, array $names, User $user, bool $replace, array &$cache): void
    {
        $ids = [];

        foreach ($names as $name) {
            $key = mb_strtolower($name);

            if (!array_key_exists($key, $cache)) {
                $cache[$key] = Tag::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $name],
                    ['color' => '#6366f1']
                )->id;
            }

            $ids[] = $cache[$key];
        }

        // Always through the relation query: `subscribers.tags` is a legacy JSON
        // column and an attribute wins over a relation of the same name.
        if ($replace) {
            $subscriber->tags()->sync($ids);
        } elseif (!empty($ids)) {
            $subscriber->tags()->syncWithoutDetaching($ids);
        }
    }

    /**
     * A list named in the file — by id, or by name among the lists the user can
     * reach.
     */
    private function resolveList(User $user, string $nameOrId, bool $createMissing, array &$cache): ?ContactList
    {
        $key = mb_strtolower(trim($nameOrId));

        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $list = null;

        if (ctype_digit($key)) {
            $list = $user->accessibleLists()->where('contact_lists.id', (int) $key)->first();
        }

        if (!$list) {
            $list = $user->accessibleLists()
                ->whereRaw('LOWER(contact_lists.name) = ?', [$key])
                ->first();
        }

        if (!$list && $createMissing) {
            $list = ContactList::create([
                'user_id' => $user->id,
                'name' => trim($nameOrId),
                'type' => 'email',
            ]);
        }

        return $cache[$key] = $list;
    }

    // ========================================================================
    // Lookups and validation
    // ========================================================================

    /**
     * Identity first, then address, then number — so a corrected email in the
     * file updates the original contact instead of forking a new one.
     */
    private function findSubscriber(User $user, array $row, bool $isSms): ?Subscriber
    {
        if ($row['netsendo_id'] !== null) {
            $byId = Subscriber::withTrashed()
                ->where('user_id', $user->id)
                ->where('id', $row['netsendo_id'])
                ->first();

            if ($byId) {
                return $byId;
            }
        }

        if ($row['email'] !== null) {
            $byEmail = Subscriber::withTrashed()
                ->where('user_id', $user->id)
                ->where('email', $row['email'])
                ->first();

            if ($byEmail) {
                return $byEmail;
            }
        }

        if ($row['phone'] !== null && ($isSms || $row['email'] === null)) {
            return Subscriber::withTrashed()
                ->where('user_id', $user->id)
                ->where('phone', $row['phone'])
                ->first();
        }

        return null;
    }

    private function membershipStatus(int $subscriberId, int $listId): ?string
    {
        return DB::table('contact_list_subscriber')
            ->where('contact_list_id', $listId)
            ->where('subscriber_id', $subscriberId)
            ->value('status');
    }

    /**
     * @return string|null a reason code, or null when the row is usable
     */
    private function rowProblem(array $row, bool $isSms): ?string
    {
        if ($isSms) {
            return $row['phone'] === null ? 'missing_phone' : null;
        }

        if ($row['email'] === null) {
            return $row['netsendo_id'] !== null ? null : 'missing_email';
        }

        return filter_var($row['email'], FILTER_VALIDATE_EMAIL) ? null : 'invalid_email';
    }

    private function dedupeKey(array $row, bool $isSms): ?string
    {
        if ($isSms && $row['phone'] !== null) {
            return 'p:' . preg_replace('/\D/', '', $row['phone']);
        }

        if ($row['email'] !== null) {
            return 'e:' . $row['email'];
        }

        return $row['netsendo_id'] !== null ? 'i:' . $row['netsendo_id'] : null;
    }

    private function recordError(array &$results, array $row, string $reason, ?string $detail = null): void
    {
        if (count($results['errors']) >= 25) {
            return;
        }

        $results['errors'][] = array_filter([
            'row' => $row['row'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'reason' => $reason,
            'detail' => $detail,
        ], fn ($v) => $v !== null);
    }

    // ========================================================================
    // Value helpers
    // ========================================================================

    /**
     * `Newsletter:active|Klienci:unsubscribed`
     *
     * Only a trailing token that is a known status counts as one, so a list
     * called "Oferta: lato" keeps its name.
     *
     * @return array<int, array{name: string, status: string}>|null
     */
    private function decodeLists(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $entry) {
                if (is_array($entry) && isset($entry['name'])) {
                    $out[] = [
                        'name' => (string) $entry['name'],
                        'status' => in_array($entry['status'] ?? '', SubscriberExportService::MEMBERSHIP_STATUSES, true)
                            ? $entry['status']
                            : 'active',
                    ];
                } elseif (is_scalar($entry)) {
                    $out[] = ['name' => (string) $entry, 'status' => 'active'];
                }
            }

            return $out ?: null;
        }

        $out = [];

        foreach (preg_split('/\s*\|\s*/', trim((string) $value)) as $token) {
            if ($token === '') {
                continue;
            }

            $status = 'active';
            $position = mb_strrpos($token, ':');

            if ($position !== false) {
                $candidate = mb_strtolower(trim(mb_substr($token, $position + 1)));

                if (in_array($candidate, SubscriberExportService::MEMBERSHIP_STATUSES, true)) {
                    $status = $candidate;
                    $token = trim(mb_substr($token, 0, $position));
                }
            }

            if ($token !== '') {
                $out[] = ['name' => $token, 'status' => $status];
            }
        }

        return $out ?: null;
    }

    /** @return string[]|null */
    private function decodeTags(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $tags = array_map('trim', array_map('strval', $value));
        } else {
            $raw = trim((string) $value);

            if ($raw === '') {
                return null;
            }

            // The export writes "|"; a hand-made file usually uses a comma.
            $separator = str_contains($raw, '|') ? '/\s*\|\s*/' : '/\s*[,;]\s*/';
            $tags = array_map('trim', preg_split($separator, $raw));
        }

        $tags = array_values(array_unique(array_filter($tags, fn ($t) => $t !== '')));

        return $tags ?: null;
    }

    private function normalizeGender(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return match (mb_strtolower($value)) {
            'm', 'male', 'mezczyzna', 'mężczyzna', 'man', 'hombre' => 'male',
            'f', 'k', 'female', 'kobieta', 'woman', 'mujer' => 'female',
            default => null,
        };
    }

    private function normalizeLanguage(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : mb_strtolower($value);
    }

    private function normalizeGlobalStatus(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return match (mb_strtolower($value)) {
            'active', 'aktywny', 'aktywna', '1', 'true', 'tak', 'yes' => 'active',
            'inactive', 'nieaktywny', 'nieaktywna', '0', 'false', 'nie', 'no' => 'inactive',
            default => null,
        };
    }

    private function normalizeMembershipStatus(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        $value = mb_strtolower($value);

        if (in_array($value, SubscriberExportService::MEMBERSHIP_STATUSES, true)) {
            return $value;
        }

        return match ($value) {
            'aktywny', 'aktywna' => 'active',
            'wypisany', 'wypisana' => 'unsubscribed',
            'odbity', 'odbita' => 'bounced',
            default => null,
        };
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = $this->text($value);

        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }

    private function stripBom(string $data): string
    {
        return preg_replace('/^\x{FEFF}/u', '', $data) ?? $data;
    }

    private function looksLikeJson(string $content): bool
    {
        $trimmed = ltrim($content);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private function looksLikeDataRow(array $row): bool
    {
        foreach ($row as $value) {
            $value = trim((string) $value);

            if (str_contains($value, '@') || preg_match('/^\+?[0-9 ()-]{9,20}$/', $value)) {
                return true;
            }
        }

        return false;
    }

    private function positionalColumnNames(array $row): array
    {
        return array_map(fn ($i) => 'column_' . ($i + 1), array_keys($row));
    }

    private function resolveDelimiter(?string $requested, string $extension, string $content): string
    {
        if ($extension === 'tsv') {
            return "\t";
        }

        if ($requested !== null && $requested !== '') {
            return $requested === 'tab' ? "\t" : substr($requested, 0, 1);
        }

        $firstLine = strtok($content, "\n") ?: '';
        $candidates = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
            '|' => substr_count($firstLine, '|'),
        ];
        arsort($candidates);
        $best = array_key_first($candidates);

        return $candidates[$best] > 0 ? $best : ',';
    }

    /**
     * Mapping rendered for the interface: `custom_field:7` reads as `cf:miasto`.
     */
    private function describeMapping(array $mapping, int $userId): array
    {
        $customIds = [];

        foreach ($mapping as $field) {
            if (str_starts_with((string) $field, 'custom_field:')) {
                $customIds[] = (int) substr((string) $field, strlen('custom_field:'));
            }
        }

        $names = empty($customIds)
            ? collect()
            : CustomField::where('user_id', $userId)->whereIn('id', $customIds)->pluck('name', 'id');

        $described = [];

        foreach ($mapping as $column => $field) {
            if (str_starts_with((string) $field, 'custom_field:')) {
                $id = (int) substr((string) $field, strlen('custom_field:'));
                $field = 'cf:' . ($names[$id] ?? $id);
            }

            $described[(string) $column] = $field;
        }

        return $described;
    }
}
