<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\ContactListGroup;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The message form says its Timezone field defaults to "list/group/account settings" and
 * "Priority: Message > List > Group > User Account" (Message::$effective_timezone), but
 * (2.7.2 review, issue 17):
 * - a queue message's "Pick a time" was read in UTC unless "Send in subscriber's timezone"
 *   was ticked;
 * - a scheduled broadcast's date was read in the message's or the account's timezone, never
 *   the list's or group's, while the CRON (send in subscriber's timezone) and the API's
 *   schedule endpoint already used the full order.
 */
class MessageTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create(['timezone' => 'Europe/Warsaw']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function list(?string $timezone = null, ?string $groupTimezone = null): ContactList
    {
        $group = $groupTimezone
            ? ContactListGroup::create(['user_id' => $this->user->id, 'name' => 'Group', 'timezone' => $groupTimezone])
            : null;

        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'List',
            'type' => 'email',
            'timezone' => $timezone,
            'contact_list_group_id' => $group?->id,
        ]);
    }

    private function scheduleBroadcast(ContactList $list, ?string $messageTimezone = null): Message
    {
        $this->actingAs($this->user)->post(route('messages.store'), [
            'subject' => 'Winter sale',
            'type' => 'broadcast',
            'status' => 'scheduled',
            'content' => '<p>Hello</p>',
            'contact_list_ids' => [$list->id],
            'send_at' => '2026-12-01 09:00',
            'timezone' => $messageTimezone,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Message::where('user_id', $this->user->id)->latest('id')->firstOrFail();
    }

    public function test_a_scheduled_broadcast_is_read_in_the_lists_timezone(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 8, 12, 0, 0, 'UTC'));

        $message = $this->scheduleBroadcast($this->list('America/New_York'));

        // 09:00 in New York (UTC-5 in December), not 09:00 in the account's Warsaw
        $this->assertSame('2026-12-01 14:00:00', $message->send_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 14:00:00', $message->scheduled_at->utc()->format('Y-m-d H:i:s'));

        // The edit form shows the time as it was entered
        $this->actingAs($this->user)->get(route('messages.edit', $message))
            ->assertInertia(fn (Assert $page) => $page->where('message.send_at', '2026-12-01 09:00:00'));
    }

    public function test_the_group_timezone_applies_when_the_list_has_none(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 8, 12, 0, 0, 'UTC'));

        $message = $this->scheduleBroadcast($this->list(null, 'Asia/Tokyo'));

        $this->assertSame('2026-12-01 00:00:00', $message->send_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_message_timezone_wins_and_the_account_timezone_is_the_last_resort(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 8, 12, 0, 0, 'UTC'));

        $message = $this->scheduleBroadcast($this->list('America/New_York'), 'Asia/Tokyo');
        $this->assertSame('2026-12-01 00:00:00', $message->send_at->utc()->format('Y-m-d H:i:s'));

        $message = $this->scheduleBroadcast($this->list());
        $this->assertSame('2026-12-01 08:00:00', $message->send_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_updating_a_broadcast_reads_the_date_in_the_lists_timezone(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 8, 12, 0, 0, 'UTC'));
        $list = $this->list('America/New_York');
        $message = $this->scheduleBroadcast($list);

        $this->actingAs($this->user)->put(route('messages.update', $message), [
            'subject' => 'Winter sale',
            'type' => 'broadcast',
            'status' => 'scheduled',
            'content' => '<p>Hello</p>',
            'contact_list_ids' => [$list->id],
            'send_at' => '2026-12-02 10:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-12-02 15:30:00', $message->fresh()->send_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_queue_messages_pick_a_time_is_in_the_message_timezone_not_utc(): void
    {
        $list = $this->list('Europe/Warsaw');
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'autoresponder',
            'subject' => 'Day 1',
            'content' => '<p>Hi</p>',
            'status' => 'scheduled',
            'day' => 1,
            'time_of_day' => '09:00',
            'send_in_subscriber_timezone' => false,
        ]);
        $message->contactLists()->attach($list->id);
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'timezone' => 'America/New_York',
        ]);

        $signup = Carbon::create(2026, 12, 1, 3, 0, 0, 'UTC');
        $expected = $message->fresh()->calculateExpectedSendAt($signup, $subscriber);

        // 09:00 Warsaw (UTC+1 in December) the next day; the subscriber's own timezone is not used
        $this->assertSame('2026-12-02 08:00:00', $expected->utc()->format('Y-m-d H:i:s'));

        // Ticked: the subscriber's timezone decides, as before
        $message->update(['send_in_subscriber_timezone' => true]);
        $expected = $message->fresh()->calculateExpectedSendAt($signup, $subscriber);
        $this->assertSame('2026-12-02 14:00:00', $expected->utc()->format('Y-m-d H:i:s'));
    }

    public function test_several_lists_use_the_one_with_the_lowest_id(): void
    {
        $first = $this->list('America/New_York');
        $second = $this->list('Asia/Tokyo');

        $lists = collect([$second, $first]);
        $this->assertSame('America/New_York', Message::resolveTimezone(null, $lists, $this->user));
        $this->assertSame('Europe/Warsaw', Message::resolveTimezone(null, [], $this->user));
        $this->assertSame('UTC', Message::resolveTimezone(null, [], null));
    }
}
