<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\CronSetting;
use App\Services\CronScheduleService;
use App\Services\Lists\ListSettingsSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Account-level defaults for contact lists (Settings → Defaults in the
 * browser): the subscription, sending, pages and advanced values a list falls
 * back to when it has no value of its own, plus the instance-wide CRON
 * schedule that the same screen edits.
 */
class ListDefaultsController extends Controller
{
    use ManagesContactLists;

    public function __construct(private CronScheduleService $cron)
    {
    }

    public function show(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        return $this->respond($request);
    }

    /**
     * Deep-merges the sections sent into the stored defaults; sections and
     * keys not sent are kept, and so are the account's other settings (CRM,
     * campaign advisor...) stored in the same document.
     */
    public function update(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $user = $request->user();
        $allowedSections = [...ListSettingsSchema::DEFAULT_SECTIONS, 'cron'];

        $validated = $request->validate([
            ...ListSettingsSchema::rules($user->id, 'settings', false),
            'settings' => ['required', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($allowedSections) {
                $unknown = array_diff(array_keys((array) $value), $allowedSections);
                if ($unknown) {
                    $fail('Unknown section(s): ' . implode(', ', $unknown) . '. Allowed: ' . implode(', ', $allowedSections) . '.');
                }
            }],
            'settings.cron' => 'nullable|array',
            'settings.cron.volume_per_minute' => 'nullable|integer|min:1|max:10000',
            'settings.cron.daily_maintenance_hour' => 'nullable|integer|min:0|max:23',
            'settings.cron.schedule' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) {
                $unknown = array_diff(array_keys((array) $value), ListSettingsSchema::DAYS);
                if ($unknown) {
                    $fail('Unknown day(s): ' . implode(', ', $unknown) . '.');
                }
            }],
            'settings.cron.schedule.*' => 'array',
            'settings.cron.schedule.*.enabled' => 'sometimes|boolean',
            'settings.cron.schedule.*.start' => 'sometimes|integer|min:0|max:1440',
            'settings.cron.schedule.*.end' => 'sometimes|integer|min:0|max:1440',
        ]);

        $patch = $validated['settings'];
        $cron = $patch['cron'] ?? null;
        unset($patch['cron']);

        // The CRON schedule is shared by every account on the instance, so a
        // team member's key may not change it.
        if (!empty($cron) && !$user->isAdmin()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Only the account owner (admin) can change the instance-wide CRON settings.',
            ], 403);
        }

        $cronUpdate = null;
        if (!empty($cron)) {
            $cronUpdate = array_filter([
                'volume_per_minute' => $cron['volume_per_minute'] ?? null,
                'daily_maintenance_hour' => $cron['daily_maintenance_hour'] ?? null,
            ], fn ($value) => $value !== null);

            if (!empty($cron['schedule'])) {
                $schedule = CronSetting::getGlobalSchedule();
                foreach ($cron['schedule'] as $day => $window) {
                    $schedule[$day] = array_merge($schedule[$day], $window);
                    if ((int) $schedule[$day]['end'] < (int) $schedule[$day]['start']) {
                        throw ValidationException::withMessages([
                            "settings.cron.schedule.{$day}.end" => "The {$day} window ends before it starts.",
                        ]);
                    }
                }
                $cronUpdate['schedule'] = $schedule;
            }
        }

        $settings = $user->settings ?? [];
        foreach (ListSettingsSchema::DEFAULT_SECTIONS as $section) {
            if (array_key_exists($section, $patch)) {
                $settings[$section] = ListSettingsSchema::merge(
                    is_array($settings[$section] ?? null) ? $settings[$section] : [],
                    $patch[$section] ?? []
                );
            }
        }

        $user->settings = $settings;
        $user->save();

        if ($cronUpdate) {
            $this->cron->saveGlobalSettings($cronUpdate);
        }

        return $this->respond($request);
    }

    private function respond(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();
        $stored = $user->settings ?? [];

        $sections = [];
        foreach (ListSettingsSchema::DEFAULT_SECTIONS as $section) {
            $sections[$section] = (object) (is_array($stored[$section] ?? null) ? $stored[$section] : []);
        }

        $global = $this->cron->getGlobalSettings();

        return response()->json([
            'data' => [
                'settings' => $sections,
                'cron' => [
                    'volume_per_minute' => $global['volume_per_minute'],
                    'daily_maintenance_hour' => $global['daily_maintenance_hour'],
                    'schedule' => $global['schedule'],
                ],
                'can_edit_cron' => $user->isAdmin(),
            ],
        ]);
    }
}
