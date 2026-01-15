<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use App\Models\CronSetting;

class DefaultSettingsController extends Controller
{
    /**
     * Display the default settings form.
     */
    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Defaults/Index', [
            'settings' => $user->settings,
            'mailboxes' => \App\Models\Mailbox::where('user_id', auth()->id())->active()->get(['id', 'name', 'from_email']),
            'externalPages' => \App\Models\ExternalPage::where('user_id', auth()->id())->get(['id', 'name']),
            'globalCronSettings' => [
                'volume_per_minute' => (int) CronSetting::getValue('volume_per_minute', 100),
                'daily_maintenance_hour' => (int) CronSetting::getValue('daily_maintenance_hour', 4),
                'schedule' => CronSetting::getGlobalSchedule(),
            ],
        ]);
    }

    /**
     * Store the default settings.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',

            // Subscription
            'settings.subscription.double_optin' => 'boolean',
            'settings.subscription.notification_email' => 'nullable|email',
            'settings.subscription.delete_unconfirmed' => 'boolean',
            'settings.subscription.delete_unconfirmed_after_days' => 'nullable|integer|min:1|max:365',

            // Sending
            'settings.sending.from_name' => 'nullable|string|max:255',
            'settings.sending.reply_to' => 'nullable|email',
            'settings.sending.company_name' => 'nullable|string|max:255',
            'settings.sending.company_address' => 'nullable|string|max:255',
            'settings.sending.company_city' => 'nullable|string|max:255',
            'settings.sending.company_zip' => 'nullable|string|max:20',
            'settings.sending.company_country' => 'nullable|string|max:255',
            'settings.sending.headers' => 'nullable|array',
            'settings.sending.headers.list_unsubscribe' => 'nullable|string',
            'settings.sending.headers.list_unsubscribe_post' => 'nullable|string',

            // Pages
            'settings.pages' => 'nullable|array',
            'settings.pages.*.type' => 'nullable|string',
            'settings.pages.*.url' => 'nullable|string',
            'settings.pages.*.external_page_id' => 'nullable|integer',

            // CRON
            'settings.cron.volume_per_minute' => 'nullable|integer|min:1|max:10000',
            'settings.cron.daily_maintenance_hour' => 'nullable|integer|min:0|max:23',
            'settings.cron.schedule' => 'nullable|array',

            // Advanced
            'settings.advanced.facebook_integration' => 'nullable|string',
            'settings.advanced.queue_days' => 'nullable|array',
            'settings.advanced.bounce_analysis' => 'boolean',
            'settings.sending.mailbox_id' => 'nullable|integer',
        ]);

        $user = auth()->user();

        // Extract CRON settings before saving user settings
        $cronData = $validated['settings']['cron'] ?? null;
        unset($validated['settings']['cron']);

        // Save user settings (without CRON)
        $user->settings = $validated['settings'];
        $user->save();

        // Save CRON settings separately using key-value store
        if ($cronData) {
            CronSetting::setValue('volume_per_minute', $cronData['volume_per_minute'] ?? 100);
            CronSetting::setValue('daily_maintenance_hour', $cronData['daily_maintenance_hour'] ?? 4);

            if (!empty($cronData['schedule'])) {
                CronSetting::setGlobalSchedule($cronData['schedule']);
            }
        }

        return Redirect::route('defaults.index')->with('success', 'Ustawienia domyślne zostały zapisane.');
    }
}

