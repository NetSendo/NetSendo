<?php

namespace Tests\Feature;

use App\Mail\WebinarRegistrationConfirmation;
use App\Mail\WebinarReminder;
use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarRegistration;
use App\Services\Webinar\WebinarPageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * The start time of a live (or hybrid) webinar comes from a datetime-local
 * input: wall-clock digits with no offset. It used to be read in the app
 * timezone (UTC), so 19:00 typed in Europe/Warsaw was stored as 19:00 UTC and
 * the countdown, reminders and the watch page said 20:00, while the sign-up
 * page printed the raw UTC digits and said 19:00. The time is now read in the
 * webinar's timezone, stored as UTC and converted back wherever it is shown.
 */
class LiveWebinarTimezoneTest extends TestCase
{
    use ActivatesGold;
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-11-12 10:00:00'); // UTC

        $this->user = User::factory()->create(['timezone' => 'Europe/Warsaw']);
    }

    /** The scheduled_at column as stored, without any cast. */
    protected function storedStart(Webinar $webinar): string
    {
        return DB::table('webinars')->where('id', $webinar->id)->value('scheduled_at');
    }

    /** A scheduled live webinar of the Warsaw owner, its time as typed in the panel. */
    protected function webinarCreatedAt(string $typed, array $extra = []): Webinar
    {
        $this->actingAs($this->user)
            ->post(route('webinars.store'), array_merge([
                'name' => 'Live Q&A',
                'type' => 'live',
                'scheduled_at' => $typed,
            ], $extra))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $webinar = Webinar::latest('id')->firstOrFail();
        $webinar->update(['status' => Webinar::STATUS_SCHEDULED]);

        return $webinar;
    }

    protected function registrationFor(Webinar $webinar, ?string $timezone = 'Europe/Warsaw'): WebinarRegistration
    {
        return $webinar->registrations()->create([
            'email' => 'uczestnik@example.com',
            'first_name' => 'Ola',
            'access_token' => str_repeat('a', 64),
            'status' => WebinarRegistration::STATUS_REGISTERED,
            'timezone' => $timezone,
        ]);
    }

    // --- Storing ---

    public function test_a_time_typed_in_the_panel_is_read_in_the_owners_timezone(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        // Winter: Warsaw is UTC+1.
        $this->assertSame('2026-11-20 18:00:00', $this->storedStart($webinar));
        $this->assertSame('2026-11-20T18:00:00+00:00', $webinar->fresh()->scheduled_at->toIso8601String());
    }

    public function test_a_hybrid_webinar_is_read_in_the_owners_timezone_too(): void
    {
        $this->activateGold();

        $webinar = $this->webinarCreatedAt('2026-11-20T19:00', ['type' => 'hybrid']);

        $this->assertSame('2026-11-20 18:00:00', $this->storedStart($webinar));
    }

    public function test_a_time_with_its_own_offset_keeps_it(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T18:00:00+00:00');

        $this->assertSame('2026-11-20 18:00:00', $this->storedStart($webinar));
    }

    public function test_creating_rejects_a_time_already_past_in_the_webinars_timezone(): void
    {
        // 18:15 UTC is 19:15 in Warsaw: 19:00 Warsaw has passed, 19:00 UTC has not.
        $this->travelTo('2026-11-20 18:15:00');

        $this->actingAs($this->user)
            ->post(route('webinars.store'), [
                'name' => 'Too late',
                'type' => 'live',
                'scheduled_at' => '2026-11-20T19:00',
            ])
            ->assertSessionHasErrors('scheduled_at');

        $this->assertSame(0, Webinar::count());

        $webinar = $this->webinarCreatedAt('2026-11-20T19:30');
        $this->assertSame('2026-11-20 18:30:00', $this->storedStart($webinar));
    }

    public function test_editing_reads_the_time_in_the_timezone_set_in_the_same_request(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        $this->actingAs($this->user)
            ->put(route('webinars.update', $webinar), [
                'scheduled_at' => '2026-11-20T19:00',
                'timezone' => 'America/New_York',
            ])
            ->assertSessionHasNoErrors();

        // New York is UTC-5 in November.
        $this->assertSame('2026-11-21 00:00:00', $this->storedStart($webinar));

        // An empty timezone inherits the owner's again.
        $this->actingAs($this->user)
            ->put(route('webinars.update', $webinar), [
                'scheduled_at' => '2026-11-20T19:00',
                'timezone' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($webinar->fresh()->timezone);
        $this->assertSame('2026-11-20 18:00:00', $this->storedStart($webinar));
    }

    public function test_editing_without_a_timezone_field_uses_the_webinars_own(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00', ['timezone' => 'Europe/London']);
        $this->assertSame('2026-11-20 19:00:00', $this->storedStart($webinar));

        $this->actingAs($this->user)
            ->put(route('webinars.update', $webinar), ['scheduled_at' => '2027-07-10T19:00'])
            ->assertSessionHasNoErrors();

        // Summer: London is UTC+1.
        $this->assertSame('2027-07-10 18:00:00', $this->storedStart($webinar));
    }

    public function test_summer_time_is_taken_into_account(): void
    {
        $webinar = $this->webinarCreatedAt('2027-07-10T19:00');

        // Summer: Warsaw is UTC+2.
        $this->assertSame('2027-07-10 17:00:00', $this->storedStart($webinar));
    }

    // --- Showing ---

    public function test_the_edit_form_shows_the_time_in_the_webinars_timezone(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        $this->actingAs($this->user)
            ->get(route('webinars.edit', $webinar))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Webinars/Edit')
                ->where('webinar.scheduled_at_local', '2026-11-20T19:00')
                ->where('webinar.effective_timezone', 'Europe/Warsaw'));
    }

    public function test_saving_the_edit_form_unchanged_keeps_the_time(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        $shown = null;
        $this->actingAs($this->user)
            ->get(route('webinars.edit', $webinar))
            ->assertInertia(function (Assert $page) use (&$shown) {
                $shown = $page->toArray()['props']['webinar']['scheduled_at_local'];
            });

        $this->actingAs($this->user)
            ->put(route('webinars.update', $webinar), ['scheduled_at' => $shown, 'timezone' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-20 18:00:00', $this->storedStart($webinar));
    }

    /** A scheduled live webinar stored directly in UTC, bypassing the panel form. */
    protected function webinarStoredAt(string $utc, array $extra = []): Webinar
    {
        return Webinar::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Stored in UTC',
            'type' => 'live',
            'status' => Webinar::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::parse($utc, 'UTC'),
        ], $extra));
    }

    public function test_the_sign_up_page_shows_the_time_in_the_webinars_timezone(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        $this->get(route('webinar.register', $webinar->slug))
            ->assertOk()
            ->assertSee('20.11.2026')
            ->assertSee(__('webinars.public.register.at_hour') . ' 19:00')
            ->assertSee('(Europe/Warsaw)', false)
            ->assertDontSee('18:00');
    }

    public function test_the_sign_up_page_converts_the_stored_utc_time(): void
    {
        // 23:30 UTC is already the next day in Warsaw.
        $webinar = $this->webinarStoredAt('2026-11-20 23:30:00');

        $this->get(route('webinar.register', $webinar->slug))
            ->assertOk()
            ->assertSee('21.11.2026')
            ->assertSee(__('webinars.public.register.at_hour') . ' 00:30')
            ->assertSee('(Europe/Warsaw)', false);
    }

    public function test_the_thank_you_screen_after_sign_up_shows_the_time_in_the_registrants_timezone(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        $this->post(route('webinar.register.submit', $webinar->slug), [
            'email' => 'uczestnik@example.com',
            'timezone' => 'Europe/Warsaw',
        ])
            ->assertOk()
            ->assertSee('20.11.2026 o 19:00')
            ->assertSee('(Europe/Warsaw)', false);
    }

    public function test_a_registrant_without_a_timezone_sees_the_webinars_inherited_one(): void
    {
        // The webinar has no timezone of its own; it inherits the owner's.
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');
        $this->assertNull($webinar->timezone);

        $this->post(route('webinar.register.submit', $webinar->slug), ['email' => 'bez-strefy@example.com'])
            ->assertOk()
            ->assertSee('20.11.2026 o 19:00')
            ->assertSee('(Europe/Warsaw)', false);

        $registration = WebinarRegistration::where('email', 'bez-strefy@example.com')->firstOrFail();
        $this->assertNull($registration->timezone);

        $this->get($registration->watch_url)
            ->assertOk()
            ->assertSee('20.11.2026 19:00');
    }

    public function test_the_watch_page_shows_the_time_and_counts_down_to_the_utc_instant(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');
        $registration = $this->registrationFor($webinar);

        $this->get($registration->watch_url)
            ->assertOk()
            ->assertSee('20.11.2026 19:00')
            ->assertSee('(Europe/Warsaw)', false)
            // The countdown runs to this instant in the browser.
            ->assertSee('2026-11-20T18:00:00+00:00', false);
    }

    public function test_the_calendar_file_starts_at_the_utc_instant(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');
        $registration = $this->registrationFor($webinar);

        $this->get($registration->ics_url)
            ->assertOk()
            ->assertSee('DTSTART:20261120T180000Z', false);
    }

    public function test_emails_show_the_time_in_the_registrants_timezone(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');
        $registration = $this->registrationFor($webinar);

        $confirmation = (new WebinarRegistrationConfirmation($registration))->render();
        $this->assertStringContainsString('20.11.2026', $confirmation);
        $this->assertStringContainsString('19:00 (Europe/Warsaw)', $confirmation);

        $reminder = (new WebinarReminder($registration, '24h'))->render();
        $this->assertStringContainsString('19:00</strong> (Europe/Warsaw)', $reminder);
        $this->assertStringNotContainsString('18:00', $reminder);
    }

    public function test_page_builder_tokens_use_the_webinars_timezone(): void
    {
        $webinar = $this->webinarCreatedAt('2026-11-20T19:00');

        $tokens = WebinarPageRenderer::tokens($webinar->fresh());

        $this->assertSame('20.11.2026', $tokens['webinar_date']);
        $this->assertSame('19:00', $tokens['webinar_time']);

        $tokens = WebinarPageRenderer::tokens($this->webinarStoredAt('2026-11-20 23:30:00', ['slug' => 'late']));

        $this->assertSame('21.11.2026', $tokens['webinar_date']);
        $this->assertSame('00:30', $tokens['webinar_time']);
    }
}
