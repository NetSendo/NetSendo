<?php

namespace App\Services\Lists;

use App\Models\ContactListCronSetting;
use App\Models\CronSetting;
use App\Services\CronScheduleService;
use Illuminate\Validation\ValidationException;

/**
 * Binds the CRON tab of the web list editors (MailingList/Edit.vue,
 * SmsList/Edit.vue) to contact_list_cron_settings — the table the dispatcher
 * reads. The tab works with short day keys (mon..sun) and a `use_custom`
 * flag; the table stores full day names and `use_defaults`.
 *
 * Form shape (request `settings.cron`):
 *   { use_custom: bool, volume_per_minute: int|null,
 *     schedule: { mon: { enabled: bool, start: 0-1440, end: 0-1440 }, ... } }
 *
 * Page shape (prop `list.cron_settings`, read by the Vue tab):
 *   { use_custom_settings: bool, volume_per_minute: int|null,
 *     weekly_schedule: { mon: {...}, ... } }
 */
class ListCronSettingsForm
{
    /** Short day key used by the web form => day name stored in the table. */
    public const DAY_KEYS = [
        'mon' => 'monday',
        'tue' => 'tuesday',
        'wed' => 'wednesday',
        'thu' => 'thursday',
        'fri' => 'friday',
        'sat' => 'saturday',
        'sun' => 'sunday',
    ];

    public function __construct(private CronScheduleService $cron)
    {
    }

    /**
     * Validation rules for the `settings.cron` part of the list update request.
     */
    public static function rules(): array
    {
        return [
            'settings.cron' => 'nullable|array',
            'settings.cron.use_custom' => 'boolean',
            'settings.cron.volume_per_minute' => 'nullable|integer|min:1|max:10000',
            'settings.cron.schedule' => 'nullable|array',
            'settings.cron.schedule.*' => 'array',
            'settings.cron.schedule.*.enabled' => 'sometimes|boolean',
            'settings.cron.schedule.*.start' => 'nullable|integer|min:0|max:1440',
            'settings.cron.schedule.*.end' => 'nullable|integer|min:0|max:1440',
        ];
    }

    /**
     * The list's stored CRON settings in the shape the Vue tab reads. Read-only:
     * does not create a settings row for lists that never had one.
     */
    public function forPage(int $contactListId): array
    {
        $row = ContactListCronSetting::where('contact_list_id', $contactListId)->first();
        $useCustom = $row && !$row->use_defaults;

        // Lists following the defaults show the global schedule, so switching to
        // custom starts from the windows that currently apply.
        $schedule = CronSetting::getGlobalSchedule();
        if ($useCustom && !empty($row->schedule)) {
            $schedule = array_merge($schedule, $row->schedule);
        }

        $weekly = [];
        foreach (self::DAY_KEYS as $short => $day) {
            $weekly[$short] = $this->normalizeWindow($schedule[$day] ?? []);
        }

        return [
            'use_custom_settings' => $useCustom,
            'volume_per_minute' => $useCustom ? $row->volume_per_minute : null,
            'weekly_schedule' => $weekly,
        ];
    }

    /**
     * Persist the CRON tab (validated `settings.cron`) through
     * CronScheduleService::saveListSettings. Accepts short (mon) or full
     * (monday) day keys; days not sent keep their current window.
     *
     * @throws ValidationException when an enabled day ends before it starts
     */
    public function save(int $contactListId, array $cron): void
    {
        $current = ContactListCronSetting::where('contact_list_id', $contactListId)->first();
        $useDefaults = !($cron['use_custom'] ?? false);

        if ($useDefaults) {
            $this->cron->saveListSettings($contactListId, ['use_defaults' => true]);

            return;
        }

        $schedule = ($current && !$current->use_defaults && !empty($current->schedule))
            ? $current->schedule
            : CronSetting::getGlobalSchedule();

        $fullToShort = array_flip(self::DAY_KEYS);

        foreach ((array) ($cron['schedule'] ?? []) as $key => $window) {
            $day = self::DAY_KEYS[$key] ?? (isset($fullToShort[$key]) ? $key : null);
            if ($day === null || !is_array($window)) {
                continue;
            }

            $merged = $this->normalizeWindow(array_merge(
                $schedule[$day] ?? [],
                array_filter($window, fn ($value) => $value !== null)
            ));

            if ($merged['enabled'] && $merged['end'] < $merged['start']) {
                throw ValidationException::withMessages([
                    "settings.cron.schedule.{$key}.end" => "The {$day} sending window ends before it starts (windows cannot span midnight).",
                ]);
            }

            $schedule[$day] = $merged;
        }

        $this->cron->saveListSettings($contactListId, [
            'use_defaults' => false,
            'volume_per_minute' => array_key_exists('volume_per_minute', $cron)
                ? $cron['volume_per_minute']
                : $current?->volume_per_minute,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Copy a list's CRON settings row (real columns) onto another list.
     */
    public static function copy(int $fromListId, int $toListId): void
    {
        $source = ContactListCronSetting::where('contact_list_id', $fromListId)->first();

        if (!$source) {
            return;
        }

        ContactListCronSetting::updateOrCreate(
            ['contact_list_id' => $toListId],
            [
                'use_defaults' => (bool) $source->use_defaults,
                'volume_per_minute' => $source->volume_per_minute,
                'schedule' => $source->schedule,
            ]
        );
    }

    private function normalizeWindow(array $window): array
    {
        return [
            'enabled' => filter_var($window['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'start' => (int) ($window['start'] ?? 0),
            'end' => (int) ($window['end'] ?? 1440),
        ];
    }
}
