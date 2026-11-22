<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * A campaign with a Gmail mailbox went out from another address. Gmail mailboxes
 * cannot send broadcasts, and SendEmailJob swapped the campaign's own mailbox for
 * the first one allowed to — a campaign "to one person" through Gmail went from
 * a shop's address three times on one account. 2.12.1 fixed POST /email/send and
 * /email/batch only; a campaign sent with send_campaign or from the panel kept
 * swapping. Now such a campaign is refused before sending, and the job never
 * reroutes it.
 */
class CampaignSenderMailboxTest extends TestCase
{
    use RecordsSentMail;
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private ContactList $list;
    private Mailbox $shop;
    private Mailbox $gmail;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', ['messages:read', 'messages:write'])['key'];

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'One person',
            'type' => 'email',
            'is_public' => true,
        ]);

        // Created first, so it is the one the fallback picks
        $this->shop = $this->mailbox('Shop', 'smtp', 'shop@example.com', ['broadcast', 'autoresponder', 'system']);
        $this->gmail = $this->mailbox('Gmail', 'gmail', 'owner@gmail.example', Mailbox::getDefaultAllowedTypes(Mailbox::PROVIDER_GMAIL), true);

        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);
    }

    private function mailbox(string $name, string $provider, string $from, array $types, bool $default = false): Mailbox
    {
        return Mailbox::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'provider' => $provider,
            'from_email' => $from,
            'from_name' => $name,
            'is_default' => $default,
            'is_active' => true,
            'allowed_types' => $types,
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);
    }

    private function campaign(array $attributes = []): Message
    {
        $message = Message::create($attributes + [
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Invitation',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
            'mailbox_id' => $this->gmail->id,
        ]);
        $message->contactLists()->attach($this->list->id);

        return $message->fresh('contactLists');
    }

    private function api()
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]);
    }

    public function test_send_campaign_refuses_a_campaign_whose_mailbox_cannot_send_broadcasts(): void
    {
        $campaign = $this->campaign();

        $this->api()->postJson("/api/v1/messages/{$campaign->id}/send")
            ->assertStatus(422)
            ->assertJsonPath('error', 'Mailbox Not Allowed')
            ->assertJsonPath('mailbox.from_email', 'owner@gmail.example');

        $this->assertSame('draft', $campaign->fresh()->status, 'A refused campaign must not be queued');
        $this->assertSame(0, MessageQueueEntry::count());
    }

    public function test_schedule_campaign_refuses_it_too(): void
    {
        $campaign = $this->campaign();

        $this->api()->postJson("/api/v1/messages/{$campaign->id}/schedule", ['scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Mailbox Not Allowed');

        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_send_campaign_with_a_mailbox_allowed_to_send_it_still_works(): void
    {
        $campaign = $this->campaign(['mailbox_id' => $this->shop->id]);

        $this->api()->postJson("/api/v1/messages/{$campaign->id}/send")->assertStatus(200);

        $this->assertSame('scheduled', $campaign->fresh()->status);
    }

    public function test_the_job_never_sends_such_a_campaign_from_another_mailbox(): void
    {
        $campaign = $this->campaign(['status' => 'scheduled']);
        $entry = $campaign->queueEntries()->create([
            'subscriber_id' => $this->subscriber->id,
            'status' => MessageQueueEntry::STATUS_QUEUED,
            'planned_at' => now(),
        ]);

        (new SendEmailJob($campaign->fresh(), $this->subscriber->fresh(), null, $entry->id))
            ->handle(app(MailProviderService::class), app(PlaceholderService::class));

        $this->assertSame([], $this->sentMail, 'The campaign went out from another mailbox');

        $entry->refresh();
        $this->assertSame(MessageQueueEntry::STATUS_FAILED, $entry->status);
        $this->assertStringContainsString('owner@gmail.example', $entry->error_message);
        $this->assertStringContainsString('not sent from another mailbox', $entry->error_message);

        // The reason is not an SMTP bounce: the recipient stays deliverable
        $this->assertTrue((bool) $this->subscriber->fresh()->is_active_global);
        $this->assertNotSame('bounced', $this->subscriber->fresh()->status);
    }

    public function test_a_campaign_without_its_own_mailbox_still_falls_back_from_the_default(): void
    {
        // Nobody chose Gmail for this campaign: it is only the account default
        $campaign = $this->campaign(['status' => 'scheduled', 'mailbox_id' => null]);

        $this->assertSame($this->shop->id, $this->runSendJob($campaign, $this->subscriber)['mailbox_id'] ?? null);
    }

    public function test_the_panel_refuses_to_send_a_campaign_through_gmail(): void
    {
        $this->actingAs($this->user)->post(route('messages.store'), [
            'subject' => 'Invitation',
            'type' => 'broadcast',
            'status' => 'sent',
            'content' => '<p>Hello</p>',
            'contact_list_ids' => [$this->list->id],
            'mailbox_id' => $this->gmail->id,
        ])->assertSessionHasErrors('mailbox_id');

        $this->assertSame(0, Message::count());
    }

    public function test_the_panel_keeps_a_gmail_draft(): void
    {
        $this->actingAs($this->user)->post(route('messages.store'), [
            'subject' => 'Invitation',
            'type' => 'broadcast',
            'status' => 'draft',
            'content' => '<p>Hello</p>',
            'contact_list_ids' => [$this->list->id],
            'mailbox_id' => $this->gmail->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->gmail->id, Message::firstOrFail()->mailbox_id);
    }
}
