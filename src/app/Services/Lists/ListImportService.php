<?php

namespace App\Services\Lists;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use App\Models\Tag;
use App\Models\User;
use App\Services\GenderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Parses and applies subscriber imports coming from the API / MCP layer.
 *
 * Unlike the web importer this never touches an uploaded file: the payload
 * arrives inline (CSV text, JSON records or a plain list of addresses), which
 * is what an AI agent can actually produce. The write path deliberately mirrors
 * SubscriberController@batch — restore soft-deleted subscribers, honour the
 * list's resubscription_behavior and fire SubscriberSignedUp — so autoresponders,
 * automations and webhooks behave identically no matter the entry point.
 */
class ListImportService
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_TSV = 'tsv';
    public const FORMAT_JSON = 'json';
    public const FORMAT_EMAILS = 'emails';

    public const FORMATS = [self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSON, self::FORMAT_EMAILS];

    /** Hard cap per request — larger sets must be sent in several calls. */
    public const MAX_ROWS = 5000;

    /** Core subscriber columns an import can target. */
    public const CORE_FIELDS = [
        'email', 'phone', 'first_name', 'last_name', 'gender', 'language', 'timezone', 'source',
    ];

    /**
     * Header aliases (PL/EN/DE/ES) used when no explicit mapping is supplied.
     */
    private const HEADER_ALIASES = [
        'email' => ['email', 'e-mail', 'e_mail', 'mail', 'adres email', 'adres e-mail', 'correo', 'correo electronico'],
        'phone' => ['phone', 'telefon', 'tel', 'mobile', 'komorka', 'komórka', 'phone_number', 'numer_telefonu', 'numer', 'telefono', 'nummer'],
        'first_name' => ['first_name', 'firstname', 'first name', 'imie', 'imię', 'name', 'nazwa', 'vorname', 'nombre'],
        'last_name' => ['last_name', 'lastname', 'last name', 'nazwisko', 'surname', 'nachname', 'apellido'],
        'gender' => ['gender', 'plec', 'płeć', 'sex', 'geschlecht', 'genero'],
        'language' => ['language', 'lang', 'jezyk', 'język', 'locale', 'sprache', 'idioma'],
        'timezone' => ['timezone', 'time_zone', 'strefa_czasowa', 'tz'],
        'source' => ['source', 'zrodlo', 'źródło', 'quelle', 'fuente'],
    ];

    public function __construct(
        protected EmailValidator $validator,
        protected GenderService $genderService,
    ) {}

    // ========================================================================
    // Parsing
    // ========================================================================

    /**
     * Turn a raw payload into normalised rows.
     *
     * @param array $payload {
     *     format: csv|tsv|json|emails,
     *     data: ?string           raw text for csv/tsv/emails,
     *     records: ?array         array of objects for json,
     *     delimiter: ?string      csv delimiter (default: auto-detect),
     *     has_header: ?bool       default: auto-detect,
     *     column_mapping: ?array  index|header => field name
     * }
     * @return array{rows: array, columns: array, header: ?array, mapping: array, warnings: array, total_rows: int}
     *
     * @throws \InvalidArgumentException on unusable input
     */
    public function parse(array $payload, User $user): array
    {
        $format = $payload['format'] ?? self::FORMAT_CSV;

        if (!in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException('Unsupported format: ' . $format);
        }

        return match ($format) {
            self::FORMAT_JSON => $this->parseRecords($payload['records'] ?? [], $payload, $user),
            self::FORMAT_EMAILS => $this->parseEmailLines((string) ($payload['data'] ?? '')),
            default => $this->parseDelimited((string) ($payload['data'] ?? ''), $format, $payload, $user),
        };
    }

    /**
     * CSV / TSV text.
     */
    private function parseDelimited(string $data, string $format, array $payload, User $user): array
    {
        $data = $this->stripBom($data);

        if (trim($data) === '') {
            throw new \InvalidArgumentException('No data supplied — "data" is empty.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $data);
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));

        if (empty($lines)) {
            throw new \InvalidArgumentException('No data supplied — "data" contains no rows.');
        }

        $delimiter = $this->resolveDelimiter($payload['delimiter'] ?? null, $format, $lines[0]);

        // Escape is passed explicitly as "" — standard CSV has no backslash
        // escaping, and PHP 8.4+ deprecates relying on the legacy default.
        $parsedLines = array_map(fn ($line) => str_getcsv($line, $delimiter, '"', ''), $lines);

        $header = null;
        $startRow = 0;
        $hasHeader = $payload['has_header'] ?? null;

        if ($hasHeader === null) {
            $hasHeader = !$this->looksLikeDataRow($parsedLines[0]);
        }

        if ($hasHeader) {
            $header = array_map(fn ($h) => trim((string) $h), $parsedLines[0]);
            $startRow = 1;
        }

        $mapping = $this->resolveMapping(
            $payload['column_mapping'] ?? null,
            $header,
            $parsedLines[$startRow] ?? [],
            $user
        );

        $rows = [];
        $warnings = [];

        for ($i = $startRow; $i < count($parsedLines); $i++) {
            if (count($rows) >= self::MAX_ROWS) {
                $warnings[] = sprintf(
                    'Input truncated at %d rows (limit per request). Send the remainder in a follow-up call.',
                    self::MAX_ROWS
                );
                break;
            }

            $rows[] = $this->buildRow($parsedLines[$i], $mapping, $i + 1, $header);
        }

        return [
            'rows' => $rows,
            'columns' => $header ?? $this->positionalColumnNames($parsedLines[0] ?? []),
            'header' => $header,
            'mapping' => $mapping,
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
            'warnings' => $warnings,
            'total_rows' => count($rows),
        ];
    }

    /**
     * JSON records — an array of objects keyed by field name.
     */
    private function parseRecords(array $records, array $payload, User $user): array
    {
        if (empty($records)) {
            throw new \InvalidArgumentException('No data supplied — "records" is empty.');
        }

        $keys = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $keys = array_unique(array_merge($keys, array_keys($record)));
            }
        }

        // Records are keyed by name, so the mapping must be too — array_keys()
        // of the records yields a positional list, which would otherwise be
        // mistaken for column indexes.
        $mapping = $this->resolveMapping(
            $payload['column_mapping'] ?? null,
            array_combine($keys, $keys),
            [],
            $user,
            keyByName: true
        );

        $rows = [];
        $warnings = [];

        foreach (array_values($records) as $index => $record) {
            if (count($rows) >= self::MAX_ROWS) {
                $warnings[] = sprintf(
                    'Input truncated at %d records (limit per request). Send the remainder in a follow-up call.',
                    self::MAX_ROWS
                );
                break;
            }

            if (!is_array($record)) {
                $warnings[] = 'Record #' . ($index + 1) . ' is not an object and was skipped.';
                continue;
            }

            $rows[] = $this->buildRowFromAssoc($record, $mapping, $index + 1);
        }

        return [
            'rows' => $rows,
            'columns' => array_values($keys),
            'header' => array_values($keys),
            'mapping' => $mapping,
            'delimiter' => null,
            'warnings' => $warnings,
            'total_rows' => count($rows),
        ];
    }

    /**
     * Plain address list: one per line, optionally "Name <mail@example.com>"
     * or "mail@example.com, First, Last". Commas and semicolons on a single
     * line are also treated as separators when no name syntax is present.
     */
    private function parseEmailLines(string $data): array
    {
        $data = $this->stripBom($data);

        if (trim($data) === '') {
            throw new \InvalidArgumentException('No data supplied — "data" is empty.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $data);
        $rows = [];
        $warnings = [];
        $rowNumber = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // "Name <mail@example.com>" possibly several per line
            if (preg_match_all('/(?:"?([^"<,;]*)"?\s*)?<([^>]+)>/', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rowNumber++;
                    $rows[] = $this->emailRow(trim($match[2]), trim($match[1] ?? ''), $rowNumber, $line);
                }
                continue;
            }

            $parts = array_values(array_filter(array_map('trim', preg_split('/[,;\t]+/', $line))));
            $emailParts = array_values(array_filter($parts, fn ($p) => str_contains($p, '@')));

            if (empty($emailParts)) {
                $rowNumber++;
                $rows[] = $this->emailRow($line, '', $rowNumber, $line);
                continue;
            }

            if (count($emailParts) > 1) {
                // Several bare addresses on one line — treat each as its own row.
                foreach ($emailParts as $email) {
                    $rowNumber++;
                    $rows[] = $this->emailRow($email, '', $rowNumber, $line);
                }
                continue;
            }

            $email = $emailParts[0];
            $names = array_values(array_filter($parts, fn ($p) => !str_contains($p, '@')));
            $rowNumber++;
            $rows[] = $this->emailRow($email, implode(' ', array_slice($names, 0, 2)), $rowNumber, $line);
        }

        if (count($rows) > self::MAX_ROWS) {
            $warnings[] = sprintf(
                'Input truncated at %d addresses (limit per request). Send the remainder in a follow-up call.',
                self::MAX_ROWS
            );
            $rows = array_slice($rows, 0, self::MAX_ROWS);
        }

        if (empty($rows)) {
            throw new \InvalidArgumentException('No addresses found in the supplied data.');
        }

        return [
            'rows' => $rows,
            'columns' => ['email', 'name'],
            'header' => null,
            'mapping' => ['email' => 'email'],
            'delimiter' => null,
            'warnings' => $warnings,
            'total_rows' => count($rows),
        ];
    }

    private function emailRow(string $email, string $name, int $rowNumber, string $raw): array
    {
        $nameParts = array_values(array_filter(preg_split('/\s+/', trim($name))));

        return $this->normalizeRow([
            'email' => $email,
            'first_name' => $nameParts[0] ?? null,
            'last_name' => isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : null,
        ], [], $rowNumber, $raw);
    }

    // ========================================================================
    // Mapping helpers
    // ========================================================================

    /**
     * Build the field mapping: explicit column_mapping wins, otherwise headers
     * are matched against known aliases, otherwise positional guessing.
     *
     * The returned map is keyed by column index (delimited input) or, with
     * $keyByName, by lower-cased key name (JSON input); values are a core
     * field name, 'custom_field:<id>' or 'ignore'.
     */
    private function resolveMapping(
        ?array $explicit,
        ?array $header,
        array $sampleRow,
        User $user,
        bool $keyByName = false
    ): array {
        $customFields = CustomField::where('user_id', $user->id)->get();
        $mapping = [];

        if (!empty($explicit)) {
            foreach ($explicit as $column => $field) {
                $field = is_string($field) ? trim($field) : '';

                if ($field === '' || $field === 'ignore') {
                    continue;
                }

                $resolved = $this->resolveFieldName($field, $customFields);
                if ($resolved !== null) {
                    $mapping[$this->normalizeColumnKey($column)] = $resolved;
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

                // Delimited input maps by index; JSON input maps by key name.
                $columnKey = ($keyByName || !is_int($index)) ? $normalized : $index;

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

        // No header and no explicit mapping: guess from the first data row.
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
     * Accepts 'email', 'custom_field:12', 'custom:city' or a custom field
     * name/label and returns the canonical mapping target.
     */
    private function resolveFieldName(string $field, $customFields): ?string
    {
        $normalized = mb_strtolower($field);

        if (in_array($normalized, self::CORE_FIELDS, true)) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'custom_field:')) {
            $id = (int) substr($normalized, strlen('custom_field:'));
            return $customFields->firstWhere('id', $id) ? 'custom_field:' . $id : null;
        }

        if (str_starts_with($normalized, 'custom:')) {
            $name = substr($normalized, strlen('custom:'));
            $custom = $customFields->first(fn ($f) => mb_strtolower($f->name) === $name);
            return $custom ? 'custom_field:' . $custom->id : null;
        }

        foreach (self::HEADER_ALIASES as $core => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $core;
            }
        }

        $custom = $customFields->first(
            fn ($f) => mb_strtolower($f->name) === $normalized || mb_strtolower((string) $f->label) === $normalized
        );

        return $custom ? 'custom_field:' . $custom->id : null;
    }

    private function normalizeColumnKey(string|int $column): string|int
    {
        if (is_int($column)) {
            return $column;
        }

        return ctype_digit($column) ? (int) $column : mb_strtolower(trim($column));
    }

    private function buildRow(array $values, array $mapping, int $rowNumber, ?array $header): array
    {
        $fields = [];

        foreach ($mapping as $column => $field) {
            if (!is_int($column)) {
                // Header-keyed mapping over delimited input: resolve to an index.
                $column = $header ? array_search($column, array_map(fn ($h) => mb_strtolower((string) $h), $header), true) : false;
                if ($column === false) {
                    continue;
                }
            }

            if (!array_key_exists($column, $values)) {
                continue;
            }

            $fields[$field] = $values[$column];
        }

        return $this->normalizeRow($fields, [], $rowNumber, $values);
    }

    private function buildRowFromAssoc(array $record, array $mapping, int $rowNumber): array
    {
        $fields = [];
        $lowered = [];

        foreach ($record as $key => $value) {
            $lowered[mb_strtolower((string) $key)] = $value;
        }

        foreach ($mapping as $column => $field) {
            $key = is_int($column) ? $column : mb_strtolower((string) $column);

            if (array_key_exists($key, $lowered)) {
                $fields[$field] = $lowered[$key];
            }
        }

        // Records may also carry nested structures the mapping does not cover.
        $extras = [];
        if (isset($record['custom_fields']) && is_array($record['custom_fields'])) {
            $extras['custom_fields_by_name'] = $record['custom_fields'];
        }
        if (isset($record['tags']) && is_array($record['tags'])) {
            $extras['tags'] = $record['tags'];
        }

        return $this->normalizeRow($fields, $extras, $rowNumber, $record);
    }

    /**
     * Produce the canonical row shape consumed by plan()/import().
     */
    private function normalizeRow(array $fields, array $extras, int $rowNumber, mixed $raw): array
    {
        $custom = [];

        foreach ($fields as $field => $value) {
            if (str_starts_with((string) $field, 'custom_field:')) {
                $id = (int) substr((string) $field, strlen('custom_field:'));
                $value = is_scalar($value) ? trim((string) $value) : null;
                if ($value !== null && $value !== '') {
                    $custom[$id] = $value;
                }
                unset($fields[$field]);
            }
        }

        $get = function (string $key) use ($fields): ?string {
            $value = $fields[$key] ?? null;
            if (!is_scalar($value)) {
                return null;
            }
            $value = trim((string) $value);
            return $value === '' ? null : $value;
        };

        $email = $this->validator->normalize($get('email'));

        return [
            'row' => $rowNumber,
            'email' => $email,
            'phone' => $get('phone'),
            'first_name' => $get('first_name'),
            'last_name' => $get('last_name'),
            'gender' => $this->normalizeGender($get('gender')),
            'language' => $get('language') ? mb_strtolower($get('language')) : null,
            'timezone' => $get('timezone'),
            'source' => $get('source'),
            'custom_fields' => $custom,
            'custom_fields_by_name' => $extras['custom_fields_by_name'] ?? [],
            'tags' => $extras['tags'] ?? [],
            'raw' => is_array($raw) ? array_slice($raw, 0, 20) : $raw,
        ];
    }

    private function normalizeGender(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (mb_strtolower($value)) {
            'm', 'male', 'mezczyzna', 'mężczyzna', 'man', 'hombre' => 'male',
            'f', 'k', 'female', 'kobieta', 'woman', 'mujer' => 'female',
            default => null,
        };
    }

    private function stripBom(string $data): string
    {
        return preg_replace('/^\x{FEFF}/u', '', $data) ?? $data;
    }

    private function resolveDelimiter(?string $requested, string $format, string $firstLine): string
    {
        if ($format === self::FORMAT_TSV) {
            return "\t";
        }

        if ($requested !== null && $requested !== '') {
            return $requested === 'tab' ? "\t" : substr($requested, 0, 1);
        }

        $candidates = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t"), '|' => substr_count($firstLine, '|')];
        arsort($candidates);
        $best = array_key_first($candidates);

        return $candidates[$best] > 0 ? $best : ',';
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
        return array_map(fn ($i) => 'column_' . $i, array_keys($row));
    }

    // ========================================================================
    // Planning (shared by preview and import)
    // ========================================================================

    /**
     * Classify every parsed row against the current state of the list.
     *
     * @return array{plan: array, summary: array}
     */
    public function plan(ContactList $list, array $rows, array $options = []): array
    {
        $user = $list->user;
        $skipInvalid = $options['skip_invalid'] ?? true;
        $skipRole = $options['skip_role'] ?? false;
        $skipDisposable = $options['skip_disposable'] ?? true;
        $skipSuppressed = $options['skip_suppressed'] ?? true;
        $fixTypos = $options['fix_typos'] ?? false;
        $updateExisting = $options['update_existing'] ?? true;

        $isSms = $list->type === 'sms';

        // Pre-load everything we need in bulk — a per-row query would make a
        // 5k-row import unusable.
        $emails = array_values(array_filter(array_column($rows, 'email')));
        $phones = array_values(array_filter(array_column($rows, 'phone')));

        $existing = collect();
        if (!empty($emails)) {
            $existing = Subscriber::withTrashed()
                ->where('user_id', $user->id)
                ->whereIn('email', array_unique($emails))
                ->get();
        }

        $existingByEmail = $existing->keyBy(fn ($s) => mb_strtolower((string) $s->email));

        $existingByPhone = collect();
        if ($isSms && !empty($phones)) {
            $existingByPhone = Subscriber::withTrashed()
                ->where('user_id', $user->id)
                ->whereIn('phone', array_unique($phones))
                ->get()
                ->keyBy('phone');
        }

        $existingIds = $existing->pluck('id')->merge($existingByPhone->pluck('id'))->unique()->all();

        $memberships = collect();
        if (!empty($existingIds)) {
            $memberships = DB::table('contact_list_subscriber')
                ->where('contact_list_id', $list->id)
                ->whereIn('subscriber_id', $existingIds)
                ->get()
                ->keyBy('subscriber_id');
        }

        $suppressed = [];
        if ($skipSuppressed && !empty($emails)) {
            $suppressed = SuppressionList::where('user_id', $user->id)
                ->whereIn('email', array_unique(array_map('mb_strtolower', $emails)))
                ->pluck('email')
                ->flip()
                ->all();
        }

        $plan = [];
        $seen = [];
        $summary = array_fill_keys(
            ['total', 'create', 'update', 'reactivate', 'already_active', 'skipped', 'invalid', 'duplicate_in_payload'],
            0
        );
        $issueCounts = [];

        foreach ($rows as $row) {
            $summary['total']++;

            $email = $row['email'];
            $phone = $row['phone'];
            $issues = [];
            $suggestion = null;

            if ($email !== null) {
                $inspection = $this->validator->inspect($email);
                $issues = $inspection['issues'];
                $suggestion = $inspection['suggestion'];

                if ($fixTypos && $suggestion !== null) {
                    $email = $suggestion;
                    $row['email'] = $email;
                    $issues[] = 'typo_corrected';
                }
            }

            // Primary identifier check depends on the list channel.
            $missingIdentifier = $isSms ? ($phone === null) : ($email === null);
            $invalidSyntax = !$isSms && $email !== null && in_array('invalid_syntax', $issues, true);

            $action = 'create';
            $reason = null;

            if ($missingIdentifier) {
                $action = 'invalid';
                $reason = $isSms ? 'missing_phone' : 'missing_email';
            } elseif ($invalidSyntax) {
                $action = $skipInvalid ? 'invalid' : 'create';
                $reason = 'invalid_syntax';
            } elseif ($skipSuppressed && $email !== null && isset($suppressed[$email])) {
                $action = 'skip';
                $reason = 'suppressed';
            } elseif ($skipRole && in_array('role_address', $issues, true)) {
                $action = 'skip';
                $reason = 'role_address';
            } elseif ($skipDisposable && in_array('disposable_domain', $issues, true)) {
                $action = 'skip';
                $reason = 'disposable_domain';
            }

            $dedupeKey = $isSms && $phone !== null
                ? 'p:' . preg_replace('/\D/', '', $phone)
                : 'e:' . $this->validator->canonical($email);

            if ($action !== 'invalid' && isset($seen[$dedupeKey])) {
                $action = 'skip';
                $reason = 'duplicate_in_payload';
                $summary['duplicate_in_payload']++;
            } elseif ($action !== 'invalid') {
                $seen[$dedupeKey] = true;
            }

            $subscriber = null;
            if ($action === 'create') {
                $subscriber = $email !== null ? $existingByEmail->get($email) : null;
                if (!$subscriber && $isSms && $phone !== null) {
                    $subscriber = $existingByPhone->get($phone);
                }

                if ($subscriber) {
                    $membership = $memberships->get($subscriber->id);

                    if ($subscriber->trashed()) {
                        $action = 'reactivate';
                    } elseif ($membership === null) {
                        $action = 'update';
                    } elseif ($membership->status === 'active') {
                        $action = $updateExisting ? 'already_active' : 'skip';
                        $reason = $reason ?? 'already_active';
                    } else {
                        $action = 'reactivate';
                    }
                }
            }

            foreach ($issues as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
            }

            match ($action) {
                'create' => $summary['create']++,
                'update' => $summary['update']++,
                'reactivate' => $summary['reactivate']++,
                'already_active' => $summary['already_active']++,
                'invalid' => $summary['invalid']++,
                default => $summary['skipped']++,
            };

            $plan[] = [
                'row' => $row['row'],
                'email' => $email,
                'phone' => $phone,
                'action' => $action,
                'reason' => $reason,
                'issues' => array_values(array_unique($issues)),
                'suggestion' => $suggestion,
                'subscriber_id' => $subscriber?->id,
                'data' => $row,
            ];
        }

        $summary['issues'] = $issueCounts;

        return ['plan' => $plan, 'summary' => $summary];
    }

    /**
     * Dry-run report: what an import would do, with a sample of rows.
     */
    public function preview(ContactList $list, array $parsed, array $options = []): array
    {
        $planned = $this->plan($list, $parsed['rows'], $options);
        $sampleSize = (int) ($options['sample_size'] ?? 10);

        return [
            'list' => ['id' => $list->id, 'name' => $list->name, 'type' => $list->type],
            'detected' => [
                'columns' => $parsed['columns'],
                'header' => $parsed['header'],
                'delimiter' => $parsed['delimiter'] ?? null,
                'mapping' => $this->describeMapping($parsed['mapping'], $list->user_id),
            ],
            'summary' => $planned['summary'],
            'warnings' => $parsed['warnings'],
            'sample' => array_map(
                fn ($entry) => [
                    'row' => $entry['row'],
                    'email' => $entry['email'],
                    'phone' => $entry['phone'],
                    'first_name' => $entry['data']['first_name'],
                    'last_name' => $entry['data']['last_name'],
                    'action' => $entry['action'],
                    'reason' => $entry['reason'],
                    'issues' => $entry['issues'],
                    'suggestion' => $entry['suggestion'],
                ],
                array_slice($planned['plan'], 0, max(0, min($sampleSize, 50)))
            ),
            'problem_rows' => array_values(array_map(
                fn ($entry) => [
                    'row' => $entry['row'],
                    'email' => $entry['email'],
                    'action' => $entry['action'],
                    'reason' => $entry['reason'],
                    'issues' => $entry['issues'],
                    'suggestion' => $entry['suggestion'],
                ],
                array_slice(
                    array_filter($planned['plan'], fn ($e) => in_array($e['action'], ['invalid', 'skip'], true)),
                    0,
                    25
                )
            )),
        ];
    }

    /**
     * Human-readable mapping for the preview response.
     */
    private function describeMapping(array $mapping, int $userId): array
    {
        $customIds = [];
        foreach ($mapping as $field) {
            if (str_starts_with((string) $field, 'custom_field:')) {
                $customIds[] = (int) substr((string) $field, strlen('custom_field:'));
            }
        }

        $customNames = empty($customIds)
            ? collect()
            : CustomField::where('user_id', $userId)->whereIn('id', $customIds)->pluck('name', 'id');

        $described = [];
        foreach ($mapping as $column => $field) {
            if (str_starts_with((string) $field, 'custom_field:')) {
                $id = (int) substr((string) $field, strlen('custom_field:'));
                $field = 'custom:' . ($customNames[$id] ?? $id);
            }
            $described[(string) $column] = $field;
        }

        return $described;
    }

    // ========================================================================
    // Import
    // ========================================================================

    /**
     * Apply the import. Returns the same counters as preview() plus the ids of
     * subscribers that were touched.
     */
    public function import(ContactList $list, array $parsed, array $options = []): array
    {
        $planned = $this->plan($list, $parsed['rows'], $options);

        $user = $list->user;
        $status = $options['status'] ?? 'active';
        $source = $options['source'] ?? 'api_import';
        $triggerAutomations = $options['trigger_automations'] ?? true;
        $detectGender = $options['detect_gender'] ?? true;
        $updateExisting = $options['update_existing'] ?? true;
        $tagIds = $this->resolveTagIds($options['tags'] ?? [], $user->id);

        $results = [
            'created' => 0,
            'updated' => 0,
            'reactivated' => 0,
            'already_active' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'failed' => 0,
            'errors' => [],
            'subscriber_ids' => [],
        ];

        $customFieldsByName = null;

        foreach ($planned['plan'] as $entry) {
            if ($entry['action'] === 'invalid') {
                $results['invalid']++;
                if (count($results['errors']) < 25) {
                    $results['errors'][] = [
                        'row' => $entry['row'],
                        'email' => $entry['email'],
                        'error' => $entry['reason'] ?? 'invalid',
                    ];
                }
                continue;
            }

            if ($entry['action'] === 'skip') {
                $results['skipped']++;
                continue;
            }

            try {
                $row = $entry['data'];
                $subscriber = $entry['subscriber_id'] ? Subscriber::withTrashed()->find($entry['subscriber_id']) : null;
                $isNew = false;

                if ($subscriber) {
                    if ($subscriber->trashed()) {
                        $subscriber->restore();
                        // A restored subscriber must not silently regain their
                        // previous list memberships or queued autoresponders.
                        $subscriber->contactLists()->detach();
                        MessageQueueEntry::where('subscriber_id', $subscriber->id)->delete();
                        $subscriber->update(['subscribed_at' => now()]);

                        Log::info('Restored and reset soft-deleted subscriber during API import', [
                            'subscriber_id' => $subscriber->id,
                            'email' => $subscriber->email,
                            'list_id' => $list->id,
                        ]);
                    }

                    if ($updateExisting) {
                        $this->applyUpdates($subscriber, $row);
                    }
                } else {
                    $subscriber = Subscriber::create([
                        'user_id' => $user->id,
                        'email' => $entry['email'],
                        'phone' => $row['phone'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'gender' => $row['gender'],
                        'language' => $row['language'],
                        'timezone' => $row['timezone'],
                        'source' => $row['source'] ?? $source,
                        'is_active_global' => $status === 'active',
                        'subscribed_at' => now(),
                    ]);
                    $isNew = true;
                }

                if ($detectGender && empty($subscriber->gender) && !empty($subscriber->first_name)) {
                    $detected = $this->genderService->detectGender($subscriber->first_name, 'PL', $user->id);
                    if ($detected) {
                        $subscriber->update(['gender' => $detected]);
                    }
                }

                // Custom fields by id (from column mapping)
                foreach ($row['custom_fields'] as $fieldId => $value) {
                    $subscriber->fieldValues()->updateOrCreate(
                        ['custom_field_id' => $fieldId],
                        ['value' => $value]
                    );
                }

                // Custom fields by name (JSON records with a custom_fields object)
                if (!empty($row['custom_fields_by_name'])) {
                    $customFieldsByName ??= CustomField::where('user_id', $user->id)->get()->keyBy(fn ($f) => mb_strtolower($f->name));

                    foreach ($row['custom_fields_by_name'] as $name => $value) {
                        $field = $customFieldsByName->get(mb_strtolower((string) $name));
                        if ($field && is_scalar($value) && trim((string) $value) !== '') {
                            $subscriber->fieldValues()->updateOrCreate(
                                ['custom_field_id' => $field->id],
                                ['value' => trim((string) $value)]
                            );
                        }
                    }
                }

                $rowTagIds = $tagIds;
                if (!empty($row['tags'])) {
                    $rowTagIds = array_unique(array_merge($rowTagIds, $this->resolveTagIds($row['tags'], $user->id)));
                }

                if (!empty($rowTagIds)) {
                    foreach ($rowTagIds as $tagId) {
                        $tag = Tag::where('user_id', $user->id)->find($tagId);
                        if ($tag) {
                            $subscriber->addTag($tag);
                        }
                    }
                }

                // Attach / reactivate on the list, honouring resubscription_behavior.
                $signedUp = $this->attachToList($subscriber, $list, $status, $row['source'] ?? $source);

                if ($signedUp && $triggerAutomations) {
                    event(new SubscriberSignedUp($subscriber, $list, null, $source));
                }

                $results['subscriber_ids'][] = $subscriber->id;

                match ($entry['action']) {
                    'create' => $isNew ? $results['created']++ : $results['updated']++,
                    'update' => $results['updated']++,
                    'reactivate' => $results['reactivated']++,
                    default => $results['already_active']++,
                };
            } catch (\Throwable $e) {
                $results['failed']++;
                if (count($results['errors']) < 25) {
                    $results['errors'][] = [
                        'row' => $entry['row'],
                        'email' => $entry['email'],
                        'error' => $e->getMessage(),
                    ];
                }

                Log::error('API list import row failed', [
                    'list_id' => $list->id,
                    'row' => $entry['row'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $results['subscriber_ids'] = array_slice(array_values(array_unique($results['subscriber_ids'])), 0, 200);
        $results['plan_summary'] = $planned['summary'];
        $results['warnings'] = $parsed['warnings'];

        return $results;
    }

    /**
     * Fill in blanks on an existing subscriber without overwriting data the
     * account already holds — an import is not authoritative over the CRM.
     */
    private function applyUpdates(Subscriber $subscriber, array $row): void
    {
        $updates = [];

        foreach (['email', 'phone', 'first_name', 'last_name', 'gender', 'language', 'timezone'] as $field) {
            if (!empty($row[$field]) && empty($subscriber->{$field})) {
                $updates[$field] = $row[$field];
            }
        }

        if (!empty($updates)) {
            $subscriber->update($updates);
        }
    }

    /**
     * @return bool whether this counted as a (re)signup, i.e. whether
     *              SubscriberSignedUp should fire.
     */
    private function attachToList(Subscriber $subscriber, ContactList $list, string $status, string $source): bool
    {
        $existing = $subscriber->contactLists()->where('contact_list_id', $list->id)->first();

        if (!$existing) {
            $subscriber->contactLists()->attach($list->id, [
                'status' => $status,
                'subscribed_at' => now(),
                'source' => $source,
            ]);

            return $status === 'active';
        }

        $wasActive = $existing->pivot->status === 'active';
        $shouldResetDate = !$wasActive || ($list->resubscription_behavior ?? 'reset_date') === 'reset_date';

        $pivot = ['status' => $status, 'source' => $source];

        if ($status === 'active') {
            $pivot['unsubscribed_at'] = null;
            if ($shouldResetDate) {
                $pivot['subscribed_at'] = now();
            }
            if (!$wasActive) {
                $pivot['resubscribed_at'] = now();
            }
        }

        $subscriber->contactLists()->updateExistingPivot($list->id, $pivot);

        return $status === 'active' && !$wasActive;
    }

    /**
     * Accepts tag ids and/or names; names that do not exist are created.
     *
     * @return int[]
     */
    private function resolveTagIds(array $tags, int $userId): array
    {
        if (empty($tags)) {
            return [];
        }

        $ids = [];

        foreach ($tags as $tag) {
            if (is_int($tag) || (is_string($tag) && ctype_digit($tag))) {
                $found = Tag::where('user_id', $userId)->find((int) $tag);
                if ($found) {
                    $ids[] = $found->id;
                }
                continue;
            }

            if (is_string($tag) && trim($tag) !== '') {
                $found = Tag::firstOrCreate(
                    ['user_id' => $userId, 'name' => trim($tag)],
                    ['color' => '#6366f1']
                );
                $ids[] = $found->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
