<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * A list whose default mailbox is Gmail names a broadcast mailbox for its
 * campaigns. Gmail mailboxes never send broadcasts; 2.12.2 stopped such
 * campaigns from going out of the first mailbox allowed to (an address nobody
 * chose) and refused them instead, which left a Gmail list with no way to send
 * a campaign at all. Now the list's broadcast mailbox takes over its
 * campaigns, while autoresponders, system emails and individual emails keep
 * going out from Gmail.
 */
class ListBroadcastMailboxTest extends TestCase
{
    use RecordsSentMail;
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private ContactList $list;
    private Mailbox $shop;
    private Mailbox $brand;
    private Mailbox $gmail;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', [
            'messages:read', 'messages:write', 'lists:read', 'lists:write',
        ])['key'];

        // Created first, so it is the one the old fallback picked: a takeover
        // that lands on "brand" proves the list's choice was used.
        $this->shop = $this->mailbox('Shop', 'smtp', 'shop@example.com', ['broadcast', 'autoresponder', 'system']);
        $this->brand = $this->mailbox('Brand', 'smtp', 'news@brand.example', ['broadcast', 'autoresponder', 'system']);
        $this->gmail = $this->mailbox('Gmail', 'gmail', 'owner@gmail.example', Mailbox::getDefaultAllowedTypes(Mailbox::PROVIDER_GMAIL), true);

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'GAPLI contacts',
            'type' => 'email',
            'is_public' => true,
            'default_mailbox_id' => $this->gmail->id,
        ]);

        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);
    }

    private function mailbox(string $name, string $provider, string $from, array $types, bool $default = false, ?User $owner = null): Mailbox
    {
        return Mailbox::create([
            'user_id' => ($owner ?? $this->user)->id,
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
            'mailbox_id' => null,
        ]);
        $message->contactLists()->attach($this->list->id);

        return $message->fresh('contactLists');
    }

    private function useBrandForCampaigns(): void
    {
        $this->list->update(['broadcast_mailbox_id' => $this->brand->id]);
    }

    private function api()
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]);
    }

    // ------------------------------------------------------------- sending

    public function test_a_campaign_inheriting_gmail_goes_out_from_the_lists_broadcast_mailbox(): void
    {
        $this->useBrandForCampaigns();
        $campaign = $this->campaign(['status' => 'scheduled']);

        $this->assertSame($this->brand->id, $this->runSendJob($campaign, $this->subscriber)['mailbox_id'] ?? null);
    }

    public function test_a_campaign_naming_gmail_itself_goes_out_from_the_lists_broadcast_mailbox(): void
    {
        // Hermes sets the list's default mailbox on the campaign it creates
        $this->useBrandForCampaigns();
        $campaign = $this->campaign(['status' => 'scheduled', 'mailbox_id' => $this->gmail->id]);

        $this->assertSame($this->brand->id, $this->runSendJob($campaign, $this->subscriber)['mailbox_id'] ?? null);
    }

    public function test_without_a_broadcast_mailbox_a_campaign_inheriting_gmail_is_not_rerouted(): void
    {
        $campaign = $this->campaign(['status' => 'scheduled']);
        $entry = $campaign->queueEntries()->create([
            'subscriber_id' => $this->subscriber->id,
            'status' => MessageQueueEntry::STATUS_QUEUED,
            'planned_at' => now(),
        ]);

        (new \App\Jobs\SendEmailJob($campaign->fresh(), $this->subscriber->fresh(), null, $entry->id))
            ->handle(app(\App\Services\Mail\MailProviderService::class), app(\App\Services\PlaceholderService::class));

        $this->assertSame([], $this->sentMail, 'The campaign went out from a mailbox nobody chose');

        $entry->refresh();
        $this->assertSame(MessageQueueEntry::STATUS_FAILED, $entry->status);
        $this->assertStringContainsString('broadcast mailbox', $entry->error_message);
        $this->assertStringContainsString('not sent from another mailbox', $entry->error_message);

        // Not an SMTP bounce: the recipient stays deliverable
        $this->assertTrue((bool) $this->subscriber->fresh()->is_active_global);
        $this->assertNotSame('bounced', $this->subscriber->fresh()->status);
    }

    public function test_autoresponders_of_the_list_keep_going_out_from_gmail(): void
    {
        $this->useBrandForCampaigns();
        $autoresponder = $this->campaign(['type' => 'autoresponder', 'status' => 'scheduled', 'day' => 0]);

        $this->assertSame($this->gmail->id, $this->runSendJob($autoresponder, $this->subscriber)['mailbox_id'] ?? null);
    }

    public function test_an_inactive_broadcast_mailbox_does_not_take_over(): void
    {
        $this->useBrandForCampaigns();
        $this->brand->update(['is_active' => false]);
        $campaign = $this->campaign(['status' => 'scheduled']);

        $this->assertNull($this->runSendJob($campaign, $this->subscriber));
    }

    // ----------------------------------------------------------------- API

    public function test_send_campaign_reports_the_lists_broadcast_mailbox_as_the_sender(): void
    {
        $this->useBrandForCampaigns();
        $campaign = $this->campaign(['mailbox_id' => $this->gmail->id]);

        $this->api()->postJson("/api/v1/messages/{$campaign->id}/send")
            ->assertOk()
            ->assertJsonPath('sender.from_email', 'news@brand.example')
            ->assertJsonPath('sender.list_broadcast_mailbox', true);

        $this->assertSame('scheduled', $campaign->fresh()->status);
    }

    public function test_send_campaign_without_a_broadcast_mailbox_points_to_the_list_setting(): void
    {
        $campaign = $this->campaign();

        $response = $this->api()->postJson("/api/v1/messages/{$campaign->id}/send")
            ->assertStatus(422)
            ->assertJsonPath('error', 'Mailbox Not Allowed')
            ->assertJsonPath('mailbox.from_email', 'owner@gmail.example')
            ->assertJsonPath('lists.0.id', $this->list->id)
            ->assertJsonPath('lists.0.broadcast_mailbox_id', null);

        $this->assertStringContainsString('broadcast_mailbox_id', $response->json('message'));
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_the_list_api_sets_and_returns_the_broadcast_mailbox(): void
    {
        $this->api()->putJson("/api/v1/lists/{$this->list->id}", ['broadcast_mailbox_id' => $this->brand->id])
            ->assertOk()
            ->assertJsonPath('data.broadcast_mailbox_id', $this->brand->id)
            ->assertJsonPath('data.settings.sending.broadcast_mailbox_id', $this->brand->id);

        $this->assertSame($this->brand->id, $this->list->fresh()->broadcast_mailbox_id);
        // The default mailbox stays Gmail
        $this->assertSame($this->gmail->id, $this->list->fresh()->default_mailbox_id);

        $this->api()->putJson("/api/v1/lists/{$this->list->id}", ['broadcast_mailbox_id' => null])->assertOk();
        $this->assertNull($this->list->fresh()->broadcast_mailbox_id);
    }

    public function test_the_list_api_refuses_gmail_or_another_accounts_mailbox_as_the_broadcast_mailbox(): void
    {
        $this->api()->putJson("/api/v1/lists/{$this->list->id}", ['broadcast_mailbox_id' => $this->gmail->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('broadcast_mailbox_id');

        $stranger = $this->mailbox('Stranger', 'smtp', 'x@other.example', ['broadcast'], false, User::factory()->create());
        $this->api()->putJson("/api/v1/lists/{$this->list->id}", ['broadcast_mailbox_id' => $stranger->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('broadcast_mailbox_id');

        $this->assertNull($this->list->fresh()->broadcast_mailbox_id);
    }

    // --------------------------------------------------------------- panel

    public function test_the_panel_sends_a_gmail_list_campaign_through_its_broadcast_mailbox(): void
    {
        $this->useBrandForCampaigns();

        $this->actingAs($this->user)->post(route('messages.store'), [
            'subject' => 'Invitation',
            'type' => 'broadcast',
            'status' => 'sent',
            'content' => '<p>Hello</p>',
            'contact_list_ids' => [$this->list->id],
            'mailbox_id' => $this->gmail->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Message::count());
    }

    public function test_the_panel_refuses_a_campaign_inheriting_gmail_without_a_broadcast_mailbox(): void
    {
        $this->actingAs($this->user)->post(route('messages.store'), [
            'subject' => 'Invitation',
            'type' => 'broadcast',
            'status' => 'sent',
            'content' => '<p>Hello</p>',
            'contact_list_ids' => [$this->list->id],
        ])->assertSessionHasErrors('mailbox_id');

        $this->assertSame(0, Message::count());
    }

    public function test_the_list_editor_saves_the_broadcast_mailbox(): void
    {
        $this->actingAs($this->user)->put(route('mailing-lists.update', $this->list), [
            'name' => 'GAPLI contacts',
            'settings' => ['sending' => [
                'mailbox_id' => $this->gmail->id,
                'broadcast_mailbox_id' => $this->brand->id,
            ]],
        ])->assertSessionHasNoErrors();

        $list = $this->list->fresh();
        $this->assertSame($this->gmail->id, $list->default_mailbox_id);
        $this->assertSame($this->brand->id, $list->broadcast_mailbox_id);
    }

    public function test_the_list_editor_refuses_gmail_as_the_broadcast_mailbox(): void
    {
        $this->actingAs($this->user)->put(route('mailing-lists.update', $this->list), [
            'name' => 'GAPLI contacts',
            'settings' => ['sending' => [
                'mailbox_id' => $this->gmail->id,
                'broadcast_mailbox_id' => $this->gmail->id,
            ]],
        ])->assertSessionHasErrors('settings.sending.broadcast_mailbox_id');

        $this->assertNull($this->list->fresh()->broadcast_mailbox_id);
    }
}
