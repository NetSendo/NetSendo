<?php

namespace Tests\Feature;

use App\Models\AutoWebinarChatScript;
use App\Models\AutoWebinarSchedule;
use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use App\Models\WebinarRegistration;
use App\Models\WebinarSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * The public webinar room (/webinar/{slug}/watch/{token}).
 *
 * Reported on 2.11.0: the chat panel of an auto-webinar said "Loading chat…"
 * forever (the page had no code for it), the Studio's "Chat enabled" switch
 * did not remove it, leaving the room answered 500, and a slot picked in
 * Europe/Warsaw started an hour late.
 */
class WebinarRoomChatTest extends TestCase
{
    use ActivatesGold;
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->activateGold();
        $this->travelTo(Carbon::parse('2026-11-12 17:31:00', 'UTC'));

        $this->user = User::factory()->create(['timezone' => 'Europe/Warsaw']);
    }

    protected function autoWebinar(array $attributes = []): Webinar
    {
        return Webinar::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Auto test',
            'slug' => 'auto-test',
            'type' => Webinar::TYPE_AUTO,
            'status' => Webinar::STATUS_PUBLISHED,
            'timezone' => 'Europe/Warsaw',
            'video_url' => 'https://example.com/recording.mp4',
        ], $attributes));
    }

    protected function script(Webinar $webinar, int $at, string $text, array $attributes = []): AutoWebinarChatScript
    {
        return AutoWebinarChatScript::create(array_merge([
            'webinar_id' => $webinar->id,
            'show_at_seconds' => $at,
            'sender_name' => 'Anna K.',
            'message_type' => AutoWebinarChatScript::TYPE_COMMENT,
            'message_text' => $text,
            'delay_variance_seconds' => 0,
            'show_randomly' => false,
            'is_active' => true,
        ], $attributes));
    }

    /** A viewer whose session started $secondsAgo seconds ago. */
    protected function viewer(Webinar $webinar, int $secondsAgo, string $email = 'widz@example.com'): WebinarRegistration
    {
        $session = WebinarSession::create([
            'webinar_id' => $webinar->id,
            'scheduled_at' => now()->subSeconds($secondsAgo),
            'status' => WebinarSession::STATUS_SCHEDULED,
            'is_replay' => true,
            'session_number' => WebinarSession::where('webinar_id', $webinar->id)->count() + 1,
        ]);

        return WebinarRegistration::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session->id,
            'email' => $email,
            'first_name' => 'Grzegorz',
            'timezone' => 'Europe/Warsaw',
            'status' => WebinarRegistration::STATUS_REGISTERED,
        ]);
    }

    protected function feed(WebinarRegistration $registration): array
    {
        return $this->getJson("/webinar/{$registration->webinar->slug}/chat/{$registration->access_token}")
            ->assertOk()
            ->json();
    }

    protected function texts(array $feed): array
    {
        return array_column($feed['messages'], 'message');
    }

    public function test_scripted_messages_appear_when_the_viewers_session_clock_reaches_them(): void
    {
        $webinar = $this->autoWebinar();
        $this->script($webinar, 25, 'Dzień dobry wszystkim!');
        $this->script($webinar, 105, 'Super materiał');
        $this->script($webinar, 165, 'Kiedy oferta?', ['message_type' => AutoWebinarChatScript::TYPE_QUESTION]);

        $viewer = $this->viewer($webinar, 37);

        $feed = $this->feed($viewer);
        $this->assertTrue($feed['enabled']);
        $this->assertTrue($feed['can_send']);
        $this->assertSame(['Dzień dobry wszystkim!'], $this->texts($feed));
        // a scripted line looks like any viewer's message
        $this->assertFalse($feed['messages'][0]['is_own']);
        $this->assertStringStartsWith('s', $feed['messages'][0]['key']);
        $this->assertArrayNotHasKey('sender_type', $feed['messages'][0]);

        $this->travel(70)->seconds();
        $this->assertSame(['Dzień dobry wszystkim!', 'Super materiał'], $this->texts($this->feed($viewer)));

        $this->travel(60)->seconds();
        $feed = $this->feed($viewer);
        $this->assertSame(['Dzień dobry wszystkim!', 'Super materiał', 'Kiedy oferta?'], $this->texts($feed));
        $this->assertSame('question', $feed['messages'][2]['kind']);
    }

    public function test_nothing_is_sent_before_the_session_starts_and_inactive_lines_never(): void
    {
        $webinar = $this->autoWebinar();
        $this->script($webinar, 0, 'Pierwszy!');
        $this->script($webinar, 5, 'Wyłączony', ['is_active' => false]);

        $this->assertSame([], $this->texts($this->feed($this->viewer($webinar, -120))));
        $this->assertSame(['Pierwszy!'], $this->texts($this->feed($this->viewer($webinar, 60, 'drugi@example.com'))));
    }

    public function test_random_lines_stay_the_same_for_a_viewer_across_polls(): void
    {
        $webinar = $this->autoWebinar();
        foreach (range(1, 20) as $i) {
            $this->script($webinar, $i, "Linia {$i}", ['show_randomly' => true]);
        }

        $viewer = $this->viewer($webinar, 60);
        $first = $this->texts($this->feed($viewer));

        $this->assertNotEmpty($first);
        $this->assertLessThan(20, count($first), 'about 70% of "random" lines are shown');
        $this->assertSame($first, $this->texts($this->feed($viewer)));
    }

    public function test_a_viewer_posts_a_message_and_sees_it_while_other_viewers_of_a_recording_do_not(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);
        $other = $this->viewer($webinar, 30, 'inny@example.com');

        $this->postJson("/webinar/auto-test/chat/{$viewer->access_token}", ['message' => 'Cześć, słychać mnie?'])
            ->assertCreated()
            ->assertJsonPath('message.message', 'Cześć, słychać mnie?')
            ->assertJsonPath('message.is_own', true)
            ->assertJsonPath('message.pending', false);

        $this->assertDatabaseHas('webinar_chat_messages', [
            'webinar_id' => $webinar->id,
            'registration_id' => $viewer->id,
            'sender_type' => WebinarChatMessage::SENDER_ATTENDEE,
            'message' => 'Cześć, słychać mnie?',
            'is_visible' => true,
        ]);
        $this->assertSame(1, $viewer->fresh()->chat_messages_count);

        $this->assertSame(['Cześć, słychać mnie?'], $this->texts($this->feed($viewer)));
        $this->assertSame([], $this->texts($this->feed($other)));
    }

    public function test_host_messages_reach_viewers_of_a_recording(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);

        WebinarChatMessage::create([
            'webinar_id' => $webinar->id,
            'sender_type' => WebinarChatMessage::SENDER_HOST,
            'sender_name' => 'Prowadzący',
            'message' => 'Witam na webinarze',
            'message_type' => WebinarChatMessage::TYPE_TEXT,
        ]);

        $feed = $this->feed($viewer);
        $this->assertSame(['Witam na webinarze'], $this->texts($feed));
        $this->assertTrue($feed['messages'][0]['is_host']);
    }

    public function test_in_a_live_session_everyone_sees_the_sessions_chat(): void
    {
        $webinar = $this->autoWebinar(['type' => Webinar::TYPE_LIVE, 'slug' => 'live-test', 'video_url' => null]);
        $live = WebinarSession::create([
            'webinar_id' => $webinar->id,
            'scheduled_at' => now()->subMinutes(5),
            'status' => WebinarSession::STATUS_LIVE,
            'session_number' => 1,
        ]);
        $a = WebinarRegistration::create(['webinar_id' => $webinar->id, 'email' => 'a@example.com', 'first_name' => 'Ala']);
        $b = WebinarRegistration::create(['webinar_id' => $webinar->id, 'email' => 'b@example.com', 'first_name' => 'Bartek']);

        $this->postJson("/webinar/live-test/chat/{$a->access_token}", ['message' => 'Hej!'])->assertCreated();

        $this->assertDatabaseHas('webinar_chat_messages', ['registration_id' => $a->id, 'webinar_session_id' => $live->id]);
        $feed = $this->feed($b);
        $this->assertSame(['Hej!'], $this->texts($feed));
        $this->assertFalse($feed['messages'][0]['is_own']);
    }

    public function test_moderated_chat_keeps_the_message_hidden_from_others_until_approved(): void
    {
        $webinar = $this->autoWebinar(['type' => Webinar::TYPE_LIVE, 'slug' => 'live-test', 'video_url' => null]);
        $webinar->updateChatSettings(['mode' => 'moderated']);
        WebinarSession::create([
            'webinar_id' => $webinar->id,
            'scheduled_at' => now()->subMinutes(5),
            'status' => WebinarSession::STATUS_LIVE,
            'session_number' => 1,
        ]);
        $a = WebinarRegistration::create(['webinar_id' => $webinar->id, 'email' => 'a@example.com']);
        $b = WebinarRegistration::create(['webinar_id' => $webinar->id, 'email' => 'b@example.com']);

        $this->postJson("/webinar/live-test/chat/{$a->access_token}", ['message' => 'Pytanie do moderacji'])
            ->assertCreated()
            ->assertJsonPath('message.pending', true);

        $this->assertTrue($this->feed($a)['messages'][0]['pending']);
        $this->assertSame([], $this->texts($this->feed($b)));
    }

    public function test_host_only_mode_and_slow_mode_refuse_messages(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);

        $webinar->updateChatSettings(['mode' => 'host_only']);
        $this->assertFalse($this->feed($viewer)['can_send']);
        $this->postJson("/webinar/auto-test/chat/{$viewer->access_token}", ['message' => 'Hej'])
            ->assertForbidden()
            ->assertJsonPath('error', 'host_only');

        $webinar->updateChatSettings(['mode' => 'open', 'slow_mode_seconds' => 30]);
        $this->postJson("/webinar/auto-test/chat/{$viewer->access_token}", ['message' => 'Pierwsza'])->assertCreated();
        $this->postJson("/webinar/auto-test/chat/{$viewer->access_token}", ['message' => 'Druga'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'slow_mode')
            ->assertJsonPath('retry_after', 30);

        $this->travel(31)->seconds();
        $this->postJson("/webinar/auto-test/chat/{$viewer->access_token}", ['message' => 'Druga'])->assertCreated();
    }

    public function test_the_room_renders_the_chat_and_drops_it_when_the_studio_turns_chat_off(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);
        $watch = "/webinar/auto-test/watch/{$viewer->access_token}";

        $this->get($watch)
            ->assertOk()
            ->assertSee('id="chat-panel"', false)
            ->assertSee(json_encode(route('webinar.chat', ['auto-test', $viewer->access_token])), false);

        // Studio → "Chat enabled" off
        $webinar->updateChatSettings(['enabled' => false]);

        $this->get($watch)
            ->assertOk()
            ->assertDontSee('id="chat-panel"', false)
            ->assertDontSee(__('webinars.public.watch.loading_chat'));

        $this->assertFalse($this->feed($viewer)['enabled']);
        $this->postJson("/webinar/auto-test/chat/{$viewer->access_token}", ['message' => 'Hej'])
            ->assertForbidden()
            ->assertJsonPath('error', 'disabled');
    }

    public function test_the_chat_endpoints_need_a_valid_token_for_that_webinar(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);
        $this->autoWebinar(['slug' => 'inny-webinar']);

        $this->getJson('/webinar/auto-test/chat/nie-ma-takiego-tokenu')->assertNotFound();
        $this->getJson("/webinar/inny-webinar/chat/{$viewer->access_token}")->assertNotFound();
        $this->postJson('/webinar/auto-test/chat/nie-ma-takiego-tokenu', ['message' => 'x'])->assertNotFound();
    }

    public function test_leaving_the_room_records_watch_time_instead_of_failing(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);

        $this->get("/webinar/auto-test/watch/{$viewer->access_token}")->assertOk();
        $this->travel(5)->minutes();

        $this->postJson("/webinar/auto-test/leave/{$viewer->access_token}", ['video_time_seconds' => 330])
            ->assertOk();

        $viewer->refresh();
        $this->assertSame(300, $viewer->watch_time_seconds);
        $this->assertNotNull($viewer->left_at);
    }

    public function test_waiting_in_the_countdown_does_not_count_as_watching(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, -120); // starts in 2 minutes
        $watch = "/webinar/auto-test/watch/{$viewer->access_token}";
        $leave = "/webinar/auto-test/leave/{$viewer->access_token}";

        $this->get($watch)->assertOk();
        $this->travel(2)->minutes();
        // the room reloads itself at the start: one leave and one join
        $this->postJson($leave)->assertOk();
        $this->assertSame(0, $viewer->fresh()->watch_time_seconds);

        $this->get($watch)->assertOk();
        $this->travel(5)->minutes();
        $this->postJson($leave)->assertOk();

        $this->assertSame(300, $viewer->fresh()->watch_time_seconds);
    }

    /**
     * The room reloads itself when the session starts; the reloaded page can
     * reach the server before the old page's leave beacon. That late beacon
     * used to close the new visit, so its whole watch time was lost.
     */
    public function test_a_late_leave_from_the_page_before_a_reload_keeps_the_new_visit_open(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, -120); // starts in 2 minutes
        $watch = "/webinar/auto-test/watch/{$viewer->access_token}";
        $leave = "/webinar/auto-test/leave/{$viewer->access_token}";

        $this->get($watch)->assertOk()->assertSee('visit: ', false);
        $countdownVisit = $viewer->fresh()->visit_started_at->getTimestamp();

        $this->travel(123)->seconds();
        $this->get($watch)->assertOk(); // the reload: visit 2
        $this->postJson($leave, ['visit' => $countdownVisit])->assertOk(); // late beacon of visit 1

        $viewer->refresh();
        $this->assertSame(3, $viewer->watch_time_seconds, 'visit 1 counts from the start to the reload');
        $this->assertNull($viewer->left_at, 'visit 2 is still open');
        $this->assertNotNull($viewer->visit_started_at);

        $this->travel(5)->minutes();
        $this->postJson($leave, ['visit' => $viewer->visit_started_at->getTimestamp()])->assertOk();

        $this->assertSame(303, $viewer->fresh()->watch_time_seconds);
        $this->assertNotNull($viewer->fresh()->left_at);
    }

    public function test_coming_back_later_does_not_count_the_time_away(): void
    {
        $webinar = $this->autoWebinar();
        $viewer = $this->viewer($webinar, 30);
        $watch = "/webinar/auto-test/watch/{$viewer->access_token}";
        $leave = "/webinar/auto-test/leave/{$viewer->access_token}";

        $this->get($watch)->assertOk();
        $this->travel(3)->minutes();
        $this->postJson($leave)->assertOk();

        $this->travel(20)->minutes(); // away

        $this->get($watch)->assertOk();
        $this->travel(2)->minutes();
        $this->postJson($leave)->assertOk();
        // a second tab or a repeated beacon closes nothing new
        $this->postJson($leave)->assertOk();

        $viewer->refresh();
        $this->assertSame(300, $viewer->watch_time_seconds);
        $this->assertNotNull($viewer->joined_at);
        $this->assertNull($viewer->visit_started_at);
    }

    /**
     * Europe/Warsaw in winter is UTC+1. The 18:40 slot of the sign-up page is
     * 17:40 UTC; it used to be stored as 18:40 UTC and shown as 19:40.
     */
    public function test_a_slot_picked_in_the_schedules_timezone_starts_at_that_time(): void
    {
        $webinar = $this->autoWebinar();
        AutoWebinarSchedule::create([
            'webinar_id' => $webinar->id,
            'schedule_type' => AutoWebinarSchedule::TYPE_EVERGREEN,
            'available_slots' => [10],
            'timezone' => 'Europe/Warsaw',
            'is_active' => true,
        ]);

        // the page lists the slot with its offset
        $this->get('/webinar/auto-test')
            ->assertOk()
            ->assertSee('value="2026-11-12T18:41:00+01:00"', false);

        // a value with an offset (current page) and one without (older cached page)
        foreach (['2026-11-12T18:41:00+01:00' => 'nowy@example.com', '2026-11-12 18:41:00' => 'stary@example.com'] as $slot => $email) {
            $this->post('/webinar/auto-test', [
                'email' => $email,
                'timezone' => 'Europe/Warsaw',
                'session_time' => $slot,
            ])->assertOk()
                ->assertSee('18:41')
                ->assertDontSee('19:41');

            $start = WebinarRegistration::where('email', $email)->first()->session->scheduled_at;
            $this->assertSame('2026-11-12 17:41:00', $start->copy()->utc()->format('Y-m-d H:i:s'), $slot);
        }
    }

    public function test_a_registration_without_a_picked_slot_starts_at_the_first_slot(): void
    {
        $webinar = $this->autoWebinar();
        AutoWebinarSchedule::create([
            'webinar_id' => $webinar->id,
            'schedule_type' => AutoWebinarSchedule::TYPE_ON_DEMAND,
            'start_delay_minutes' => 15,
            'timezone' => 'Europe/Warsaw',
            'is_active' => true,
        ]);

        $this->post('/webinar/auto-test', ['email' => 'od-razu@example.com', 'timezone' => 'Europe/Warsaw'])->assertOk();

        $start = WebinarRegistration::where('email', 'od-razu@example.com')->first()->session->scheduled_at;
        $this->assertSame('2026-11-12 17:46:00', $start->copy()->utc()->format('Y-m-d H:i:s'));
    }
}
