<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\ContactListCronSetting;
use App\Models\User;
use App\Services\CronScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CRON tab of the web list editors used to save into the list's
 * `settings.cron` JSON and read fields that do not exist, while the dispatcher
 * only reads contact_list_cron_settings — so a per-list sending schedule set
 * in the panel never took effect. Copying a list also wrote nonexistent
 * columns, so the copy never inherited the schedule.
 */
class ListCronSettingsWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function makeList(string $type = 'email'): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => ucfirst($type) . ' list',
            'type' => $type,
            'is_public' => true,
        ]);
    }

    /** Weekdays 08:00-17:00, weekend off — as the Vue tab sends it. */
    private function customCron(int $volume = 25): array
    {
        $schedule = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri'] as $day) {
            $schedule[$day] = ['enabled' => true, 'start' => 480, 'end' => 1020];
        }
        $schedule['sat'] = ['enabled' => false, 'start' => 0, 'end' => 1440];
        $schedule['sun'] = ['enabled' => false, 'start' => 0, 'end' => 1440];

        return ['use_custom' => true, 'volume_per_minute' => $volume, 'schedule' => $schedule];
    }

    private function cronRow(ContactList $list): ?ContactListCronSetting
    {
        return ContactListCronSetting::where('contact_list_id', $list->id)->first();
    }

    public function test_mailing_list_update_persists_cron_tab_to_cron_settings_table(): void
    {
        $list = $this->makeList();

        $this->actingAs($this->user)
            ->put(route('mailing-lists.update', $list), [
                'name' => 'Renamed',
                'settings' => [
                    'subscription' => ['double_optin' => true],
                    'cron' => $this->customCron(25),
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mailing-lists.index'));

        $row = $this->cronRow($list);
        $this->assertNotNull($row);
        $this->assertFalse($row->use_defaults);
        $this->assertSame(25, $row->volume_per_minute);
        $this->assertSame(['enabled' => true, 'start' => 480, 'end' => 1020], $row->schedule['monday']);
        $this->assertFalse($row->schedule['saturday']['enabled']);
        $this->assertFalse($row->schedule['sunday']['enabled']);

        $list->refresh();
        $this->assertSame('Renamed', $list->name);
        $this->assertTrue($list->settings['subscription']['double_optin']);
        $this->assertArrayNotHasKey('cron', $list->settings, 'CRON tab must not be shadow-stored in the settings JSON');

        // The dispatcher honours the saved schedule.
        $cron = app(CronScheduleService::class);
        $this->assertTrue($cron->isDispatchAllowed($list->id, Carbon::parse('2026-10-05 10:00')));  // Monday
        $this->assertFalse($cron->isDispatchAllowed($list->id, Carbon::parse('2026-10-05 18:30'))); // Monday evening
        $this->assertFalse($cron->isDispatchAllowed($list->id, Carbon::parse('2026-10-10 10:00'))); // Saturday
        $this->assertSame(25, $row->getEffectiveVolumePerMinute());
    }

    public function test_switching_back_to_global_settings_clears_custom_schedule(): void
    {
        $list = $this->makeList();
        app(\App\Services\Lists\ListCronSettingsForm::class)->save($list->id, $this->customCron());

        $this->actingAs($this->user)
            ->put(route('mailing-lists.update', $list), [
                'name' => $list->name,
                'settings' => ['cron' => ['use_custom' => false, 'volume_per_minute' => null, 'schedule' => []]],
            ])
            ->assertSessionHasNoErrors();

        $row = $this->cronRow($list);
        $this->assertTrue($row->use_defaults);
        $this->assertNull($row->volume_per_minute);
        $this->assertNull($row->schedule);
        $this->assertTrue(app(CronScheduleService::class)->isDispatchAllowed($list->id, Carbon::parse('2026-10-10 10:00')));
    }

    public function test_update_without_cron_tab_leaves_cron_settings_untouched(): void
    {
        $list = $this->makeList();
        app(\App\Services\Lists\ListCronSettingsForm::class)->save($list->id, $this->customCron(40));

        $this->actingAs($this->user)
            ->put(route('mailing-lists.update', $list), ['name' => 'Only the name'])
            ->assertSessionHasNoErrors();

        $row = $this->cronRow($list);
        $this->assertFalse($row->use_defaults);
        $this->assertSame(40, $row->volume_per_minute);
    }

    public function test_window_ending_before_it_starts_is_rejected(): void
    {
        $list = $this->makeList();
        $cron = $this->customCron();
        $cron['schedule']['mon'] = ['enabled' => true, 'start' => 1020, 'end' => 480];

        $this->actingAs($this->user)
            ->put(route('mailing-lists.update', $list), ['name' => 'Renamed', 'settings' => ['cron' => $cron]])
            ->assertSessionHasErrors('settings.cron.schedule.mon.end');

        $this->assertNull($this->cronRow($list));
        $this->assertSame('Email list', $list->fresh()->name);
    }

    public function test_edit_page_shows_stored_cron_settings_in_tab_shape(): void
    {
        $list = $this->makeList();
        app(\App\Services\Lists\ListCronSettingsForm::class)->save($list->id, $this->customCron(30));

        $this->actingAs($this->user)
            ->get(route('mailing-lists.edit', $list))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MailingList/Edit')
                ->where('list.cron_settings.use_custom_settings', true)
                ->where('list.cron_settings.volume_per_minute', 30)
                ->where('list.cron_settings.weekly_schedule.mon', ['enabled' => true, 'start' => 480, 'end' => 1020])
                ->where('list.cron_settings.weekly_schedule.sat.enabled', false)
            );
    }

    public function test_edit_page_for_list_on_defaults_shows_global_schedule_without_creating_a_row(): void
    {
        $list = $this->makeList();

        $this->actingAs($this->user)
            ->get(route('mailing-lists.edit', $list))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('list.cron_settings.use_custom_settings', false)
                ->where('list.cron_settings.volume_per_minute', null)
                ->where('list.cron_settings.weekly_schedule.sun', ['enabled' => true, 'start' => 0, 'end' => 1440])
            );

        $this->assertNull($this->cronRow($list));
    }

    public function test_sms_list_update_persists_cron_tab_to_cron_settings_table(): void
    {
        $list = $this->makeList('sms');

        $this->actingAs($this->user)
            ->put(route('sms-lists.update', $list), [
                'name' => 'SMS renamed',
                'settings' => ['cron' => $this->customCron(12)],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sms-lists.index'));

        $row = $this->cronRow($list);
        $this->assertFalse($row->use_defaults);
        $this->assertSame(12, $row->volume_per_minute);
        $this->assertFalse(app(CronScheduleService::class)->isDispatchAllowed($list->id, Carbon::parse('2026-10-11 12:00'))); // Sunday
        $this->assertArrayNotHasKey('cron', $list->fresh()->settings ?? []);

        $this->actingAs($this->user)
            ->get(route('sms-lists.edit', $list))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SmsList/Edit')
                ->where('list.cron_settings.use_custom_settings', true)
                ->where('list.cron_settings.volume_per_minute', 12)
            );
    }

    public function test_copying_a_mailing_list_copies_its_cron_settings(): void
    {
        $list = $this->makeList();
        app(\App\Services\Lists\ListCronSettingsForm::class)->save($list->id, $this->customCron(33));

        $this->actingAs($this->user)
            ->post(route('mailing-lists.copy', $list), [
                'name' => 'Copy',
                'is_public' => true,
                'copy_subscribers' => false,
                'copy_system_messages' => false,
                'copy_system_pages' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mailing-lists.index'));

        $copy = ContactList::where('name', 'Copy')->firstOrFail();
        $source = $this->cronRow($list);
        $copied = $this->cronRow($copy);

        $this->assertNotNull($copied);
        $this->assertFalse($copied->use_defaults);
        $this->assertSame(33, $copied->volume_per_minute);
        $this->assertSame($source->schedule, $copied->schedule);
        $this->assertFalse(app(CronScheduleService::class)->isDispatchAllowed($copy->id, Carbon::parse('2026-10-10 10:00')));
    }

    public function test_copying_an_sms_list_copies_its_cron_settings(): void
    {
        $list = $this->makeList('sms');
        app(\App\Services\Lists\ListCronSettingsForm::class)->save($list->id, $this->customCron(7));

        $this->actingAs($this->user)
            ->post(route('sms-lists.copy', $list), [
                'name' => 'SMS copy',
                'is_public' => false,
                'copy_subscribers' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sms-lists.index'));

        $copied = $this->cronRow(ContactList::where('name', 'SMS copy')->firstOrFail());
        $this->assertFalse($copied->use_defaults);
        $this->assertSame(7, $copied->volume_per_minute);
        $this->assertFalse($copied->schedule['saturday']['enabled']);
    }
}
