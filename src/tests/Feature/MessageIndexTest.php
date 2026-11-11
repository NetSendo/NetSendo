<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The e-mail message list: tabs (all, drafts, scheduled, sent, queues) with their counts,
 * and e-mail only.
 */
class MessageIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function message(array $attributes = []): Message
    {
        return Message::create($attributes + [
            'user_id' => $this->user->id, 'subject' => 'Hello', 'content' => '<p>Hi</p>',
            'channel' => 'email', 'type' => 'broadcast', 'status' => 'draft',
        ]);
    }

    private function index(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->get(route('messages.index', $query))->assertOk();
    }

    private function subjects(array $query = []): array
    {
        $subjects = [];
        $this->index($query)->assertInertia(function (Assert $page) use (&$subjects) {
            $subjects = array_column($page->toArray()['props']['messages']['data'], 'subject');
        });

        return $subjects;
    }

    public function test_the_tabs_filter_by_type_and_status(): void
    {
        $this->message(['subject' => 'Draft']);
        $this->message(['subject' => 'Scheduled', 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $this->message(['subject' => 'Sent', 'status' => 'sent']);
        $this->message(['subject' => 'Queue', 'type' => 'autoresponder', 'status' => 'sent', 'day' => 1]);

        $this->assertSame(['Draft'], $this->subjects(['type' => 'broadcast', 'status' => 'draft']));
        $this->assertSame(['Scheduled'], $this->subjects(['type' => 'broadcast', 'status' => 'scheduled']));
        $this->assertSame(['Sent'], $this->subjects(['type' => 'broadcast', 'status' => 'sent']));
        $this->assertSame(['Queue'], $this->subjects(['type' => 'autoresponder']));
        $this->assertCount(4, $this->subjects());
        $this->assertCount(4, $this->subjects(['status' => 'no-such-status']));
    }

    public function test_every_tab_carries_its_count(): void
    {
        $this->message();
        $this->message();
        $this->message(['status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $this->message(['status' => 'sent']);
        $this->message(['type' => 'autoresponder', 'status' => 'sent', 'day' => 1]);
        Message::create(['user_id' => User::factory()->create()->id, 'subject' => 'Someone else', 'content' => 'x', 'channel' => 'email', 'type' => 'broadcast', 'status' => 'draft']);

        // The counts describe the whole list, whatever tab or search is open
        $this->index(['type' => 'broadcast', 'status' => 'sent', 'search' => 'nothing'])->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['all' => 5, 'draft' => 2, 'scheduled' => 1, 'sent' => 1, 'autoresponder' => 1])
            ->where('filters.status', 'sent'));
    }

    public function test_sms_messages_are_not_in_the_email_list(): void
    {
        $this->message(['subject' => 'E-mail']);
        $this->message(['subject' => 'Text message', 'channel' => 'sms']);

        $this->assertSame(['E-mail'], $this->subjects());
        $this->index()->assertInertia(fn (Assert $page) => $page->where('counts.all', 1));
    }

    public function test_a_message_lists_the_names_of_its_lists(): void
    {
        $message = $this->message();
        $message->contactLists()->attach([
            ContactList::factory()->create(['user_id' => $this->user->id, 'name' => 'Newsletter'])->id,
            ContactList::factory()->create(['user_id' => $this->user->id, 'name' => 'Customers'])->id,
        ]);

        $this->index()->assertInertia(function (Assert $page) {
            $names = $page->toArray()['props']['messages']['data'][0]['list_names'];
            sort($names);
            $this->assertSame(['Customers', 'Newsletter'], $names);
        });
    }
}
