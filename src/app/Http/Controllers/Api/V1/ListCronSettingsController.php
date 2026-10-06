<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\ContactListCronSetting;
use App\Models\CronSetting;
use App\Services\CronScheduleService;
use App\Services\Lists\ListSettingsSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Per-list sending schedule (CRON): the weekly windows in which queued
 * messages of a list may be dispatched, and its messages-per-minute limit.
 * A list either follows the instance-wide CRON settings (use_defaults) or has
 * its own. Stored in contact_list_cron_settings, which the dispatcher reads.
 */
class ListCronSettingsController extends Controller
{
    use ManagesContactLists;

    public function __construct(private CronScheduleService $cron)
    {
    }

    public function show(Request $request, int $list): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $contactList = $this->findList($request, $list);

        if (!$contactList) {
            return $this->listNotFound();
        }

        return $this->respond($contactList);
    }

    /**
     * Partial update: days sent in `schedule` are merged into the current
     * schedule (or the global one when the list followed the defaults), so
     * `{"schedule": {"saturday": {"enabled": false}}}` changes only Saturday.
     * Sending `volume_per_minute` or `schedule` without `use_defaults` switches
     * the list to its own settings.
     */
    public function update(Request $request, int $list): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $contactList = $this->findList($request, $list);

        if (!$contactList) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'use_defaults' => 'sometimes|boolean',
            'volume_per_minute' => 'sometimes|nullable|integer|min:1|max:10000',
            'schedule' => ['sometimes', 'array', function (string $attribute, mixed $value, \Closure $fail) {
                $unknown = array_diff(array_keys((array) $value), ListSettingsSchema::DAYS);
                if ($unknown) {
                    $fail('Unknown day(s): ' . implode(', ', $unknown) . '. Use: ' . implode(', ', ListSettingsSchema::DAYS) . '.');
                }
            }],
            'schedule.*' => 'array',
            'schedule.*.enabled' => 'sometimes|boolean',
            'schedule.*.start' => 'sometimes|integer|min:0|max:1440',
            'schedule.*.end' => 'sometimes|integer|min:0|max:1440',
        ]);

        $current = ContactListCronSetting::getOrCreateForList($contactList->id);

        $customGiven = array_key_exists('volume_per_minute', $validated) || array_key_exists('schedule', $validated);
        $useDefaults = array_key_exists('use_defaults', $validated)
            ? (bool) $validated['use_defaults']
            : ($customGiven ? false : (bool) $current->use_defaults);

        $baseSchedule = (!$current->use_defaults && !empty($current->schedule))
            ? $current->schedule
            : CronSetting::getGlobalSchedule();

        $schedule = $baseSchedule;
        foreach ($validated['schedule'] ?? [] as $day => $window) {
            $schedule[$day] = array_merge(
                ['enabled' => true, 'start' => 0, 'end' => 1440],
                $schedule[$day] ?? [],
                $window
            );
        }

        foreach ($schedule as $day => $window) {
            if ((int) ($window['end'] ?? 1440) < (int) ($window['start'] ?? 0)) {
                throw ValidationException::withMessages([
                    "schedule.{$day}.end" => "The {$day} window ends before it starts (minutes from midnight, 0-1440; windows cannot span midnight).",
                ]);
            }
        }

        $this->cron->saveListSettings($contactList->id, [
            'use_defaults' => $useDefaults,
            'volume_per_minute' => array_key_exists('volume_per_minute', $validated)
                ? $validated['volume_per_minute']
                : $current->volume_per_minute,
            'schedule' => $schedule,
        ]);

        return $this->respond($contactList);
    }

    private function respond(ContactList $list): JsonResponse
    {
        $settings = ContactListCronSetting::getOrCreateForList($list->id);
        $global = $this->cron->getGlobalSettings();

        return response()->json([
            'data' => [
                'list_id' => $list->id,
                'use_defaults' => (bool) $settings->use_defaults,
                'volume_per_minute' => $settings->volume_per_minute,
                'schedule' => $settings->schedule,
                'effective_volume_per_minute' => $settings->getEffectiveVolumePerMinute(),
                'effective_schedule' => $settings->getEffectiveSchedule(),
                'dispatch_allowed_now' => $settings->isDispatchAllowedNow(),
                'global' => [
                    'volume_per_minute' => $global['volume_per_minute'],
                    'schedule' => $global['schedule'],
                ],
            ],
        ]);
    }
}
