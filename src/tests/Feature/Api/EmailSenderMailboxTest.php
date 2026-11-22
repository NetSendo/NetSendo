<?php

namespace Tests\Feature\Api;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * An email sent through POST /api/v1/email/send went out from another mailbox
 * than the one the caller named. Gmail mailboxes cannot send broadcasts, the API
 * stored every email as a broadcast, and SendEmailJob then swapped the mailbox
 * for the first one allowed to send broadcasts — while the API had answered
 * "queued" with the Gmail mailbox's name. A personal email went from a shop's
 * address, six times in two weeks on one account.
 */
class EmailSenderMailboxTest extends TestCase
{
    use RecordsSentMail;
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private Mailbox $shop;
    private Mailbox $gmail;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', ['email:read', 'email:write'])['key'];

        // Created first, so it is the one the old fallback picked
        $this->shop = $this->mailbox('Shop', 'smtp', 'shop@example.com', ['broadcast', 'autoresponder', 'system']);
        $this->gmail = $this->mailbox('Gmail', 'gmail', 'owner@gmail.example', Mailbox::getDefaultAllowedTypes(Mailbox::PROVIDER_GMAIL), true);
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

    private function api()
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]);
    }

    private function sendEmail(array $payload = [])
    {
        return $this->api()->postJson('/api/v1/email/send', $payload + [
            'email' => 'reader@example.com',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
        ]);
    }

    /**
     * Run the send job for the message's only entry, as the queue worker would.
     */
    private function deliver(Message $message): ?array
    {
        $entry = $message->queueEntries()->firstOrFail();
        $before = count($this->sentMail);

        (new SendEmailJob($message->fresh(), Subscriber::findOrFail($entry->subscriber_id), null, $entry->id))
            ->handle(app(MailProviderService::class), app(PlaceholderService::class));

        return count($this->sentMail) > $before ? end($this->sentMail) : null;
    }

    public function test_an_individual_email_goes_from_the_gmail_mailbox_it_names(): void
    {
        $response = $this->sendEmail(['mailbox_id' => $this->gmail->id])
            ->assertStatus(202)
            ->assertJsonPath('data.mailbox_id', $this->gmail->id)
            ->assertJsonPath('data.from_email', 'owner@gmail.example');

        $message = Message::findOrFail($response->json('data.id'));
        $this->assertTrue($message->is_individual);

        $sent = $this->deliver($message);

        $this->assertNotNull($sent, 'The email was not sent');
        $this->assertSame($this->gmail->id, $sent['mailbox_id'], 'The email went out from another mailbox');
        $this->assertSame('Hello', $sent['subject'], 'An individual email has no TEST prefix');
        $this->assertSame(MessageQueueEntry::STATUS_SENT, $message->queueEntries()->first()->status);
    }

    public function test_n8n_sending_server_id_names_the_mailbox_too(): void
    {
        $this->sendEmail(['sending_server_id' => $this->gmail->id])
            ->assertStatus(202)
            ->assertJsonPath('data.mailbox_id', $this->gmail->id);
    }

    public function test_without_a_mailbox_the_email_goes_from_the_default_gmail_mailbox(): void
    {
        $id = $this->sendEmail()
            ->assertStatus(202)
            ->assertJsonPath('data.mailbox_id', $this->gmail->id)
            ->json('data.id');

        $this->assertSame($this->gmail->id, $this->deliver(Message::findOrFail($id))['mailbox_id'] ?? null);
    }

    public function test_a_named_mailbox_that_cannot_send_it_is_refused_up_front(): void
    {
        $systemOnly = $this->mailbox('System', 'smtp', 'system@example.com', ['system']);

        $this->sendEmail(['mailbox_id' => $systemOnly->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Mailbox Not Allowed')
            ->assertJsonPath('allowed_types', ['system']);

        $this->assertSame(0, Message::count(), 'Nothing may be queued for a refused mailbox');
    }

    public function test_an_individual_email_is_never_sent_from_another_mailbox(): void
    {
        $message = Message::findOrFail($this->sendEmail(['mailbox_id' => $this->gmail->id])->json('data.id'));

        // The mailbox loses its types between queueing and sending
        $this->gmail->update(['allowed_types' => ['system']]);

        $this->assertNull($this->deliver($message), 'The email went out from another mailbox');

        $entry = $message->queueEntries()->first();
        $this->assertSame(MessageQueueEntry::STATUS_FAILED, $entry->status);
        $this->assertStringContainsString('owner@gmail.example', $entry->error_message);
        $this->assertStringContainsString('not sent from another mailbox', $entry->error_message);

        // The reason is not an SMTP bounce: the recipient stays deliverable
        $subscriber = Subscriber::findOrFail($entry->subscriber_id);
        $this->assertTrue((bool) $subscriber->is_active_global);
        $this->assertNotSame('bounced', $subscriber->status);
    }

    public function test_an_individual_email_whose_mailbox_was_switched_off_is_not_rerouted(): void
    {
        $message = Message::findOrFail($this->sendEmail(['mailbox_id' => $this->gmail->id])->json('data.id'));

        $this->gmail->update(['is_active' => false]);

        $this->assertNull($this->deliver($message), 'The email went out from another mailbox');
        $this->assertStringContainsString('switched off or removed', $message->queueEntries()->first()->error_message);
    }

    public function test_an_individual_email_over_its_mailbox_limit_is_not_rerouted(): void
    {
        $message = Message::findOrFail($this->sendEmail(['mailbox_id' => $this->gmail->id])->json('data.id'));

        $this->gmail->update(['daily_limit' => 1, 'sent_today' => 1, 'sent_today_date' => now()->toDateString()]);

        $this->assertNull($this->deliver($message), 'The email went out from another mailbox');
        $this->assertStringContainsString('daily sending limit', $message->queueEntries()->first()->error_message);
    }

    public function test_a_batch_refuses_a_named_mailbox_that_cannot_send_broadcasts(): void
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'a@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);

        $this->api()->postJson('/api/v1/email/batch', [
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'subscriber_ids' => [$subscriber->id],
            'mailbox_id' => $this->gmail->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Mailbox Not Allowed');

        $this->assertSame(0, Message::count());
    }

    public function test_a_batch_without_a_mailbox_names_the_broadcast_mailbox_it_goes_from(): void
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'a@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);

        $id = $this->api()->postJson('/api/v1/email/batch', [
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'subscriber_ids' => [$subscriber->id],
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.mailbox_id', $this->shop->id)
            ->assertJsonPath('data.from_email', 'shop@example.com')
            ->json('data.id');

        $message = Message::findOrFail($id);
        $this->assertFalse($message->is_individual);
        $this->assertSame($this->shop->id, $message->mailbox_id, 'The message must name the mailbox it goes from');
    }

    public function test_a_broadcast_still_falls_back_when_no_one_named_its_mailbox(): void
    {
        // Autoresponder queues depend on this fallback (see SendEmailJob::handle).
        // Gmail is only the account default here, not the message's own mailbox.
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'a@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $message = Message::create([
            'user_id' => $this->user->id,
            'type' => 'broadcast',
            'channel' => 'email',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
        ]);

        $this->assertSame($this->shop->id, $this->runSendJob($message, $subscriber)['mailbox_id'] ?? null);
    }
}
