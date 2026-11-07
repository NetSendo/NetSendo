<?php

namespace Tests\Feature;

use App\Events\EmailBounced;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bounce webhooks (/webhooks/bounce/{provider}/{mailbox}) used to accept any
 * POST, so anyone who knew an instance URL could mark any address as
 * hard-bounced. Each provider is now authenticated with its own mechanism and
 * the secrets of the mailbox in the URL, and a bounce only touches that
 * mailbox owner's subscribers.
 */
class BounceWebhookAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'tok_7f3a9c1e5b2d8f4a6c0e9b3d7f1a5c8e2b6d0f4a8c3e7b1d';
    private const MAILGUN_KEY = 'mg-signing-key-0123456789abcdef';

    private User $user;
    private User $otherUser;
    private Mailbox $mailbox;
    private ContactList $list;
    private ContactList $otherList;
    private Subscriber $subscriber;
    private Subscriber $otherSubscriber;

    /** @var \OpenSSLAsymmetricKey SendGrid's private key (stands in for SendGrid) */
    private $sendGridPrivateKey;
    private string $sendGridPublicKey;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([EmailBounced::class]);
        Log::spy();

        [$this->sendGridPrivateKey, $this->sendGridPublicKey] = $this->ecKeyPair();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->mailbox = $this->mailboxFor($this->user, [
            'token' => self::TOKEN,
            'sendgrid_verification_key' => $this->sendGridPublicKey,
            'mailgun_signing_key' => self::MAILGUN_KEY,
        ]);

        // The same address subscribed in two accounts of one instance
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->otherList = ContactList::create(['user_id' => $this->otherUser->id, 'name' => 'Other', 'type' => 'email']);
        $this->subscriber = $this->subscriberOn($this->list, 'reader@example.com');
        $this->otherSubscriber = $this->subscriberOn($this->otherList, 'reader@example.com');
    }

    // ---------------------------------------------------------------- SendGrid

    public function test_sendgrid_accepts_a_valid_signature(): void
    {
        $this->sendGrid($this->sendGridEvents())->assertOk()->assertJson(['status' => 'ok']);

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_sendgrid_accepts_the_key_pasted_as_pem(): void
    {
        $this->mailbox->updateBounceWebhookCredentials([
            'sendgrid_verification_key' => "-----BEGIN PUBLIC KEY-----\n" . chunk_split($this->sendGridPublicKey, 64, "\n") . "-----END PUBLIC KEY-----\n",
        ]);

        $this->sendGrid($this->sendGridEvents())->assertOk();

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_sendgrid_rejects_a_signature_from_another_key(): void
    {
        [$otherKey] = $this->ecKeyPair();

        $this->sendGrid($this->sendGridEvents(), signWith: $otherKey)->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('sendgrid', 'invalid_signature');
    }

    public function test_sendgrid_rejects_a_body_changed_after_signing(): void
    {
        $timestamp = (string) now()->getTimestamp();
        $signed = $this->sendGridEvents('someone@example.com');
        $signature = $this->sendGridSignature($timestamp . $signed);

        $this->postRaw($this->url('sendgrid'), $this->sendGridEvents(), [
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $timestamp,
        ])->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('sendgrid', 'invalid_signature');
    }

    public function test_sendgrid_rejects_a_missing_signature(): void
    {
        $this->postRaw($this->url('sendgrid'), $this->sendGridEvents())->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('sendgrid', 'missing_signature');
    }

    public function test_sendgrid_rejects_a_stale_timestamp(): void
    {
        $this->sendGrid($this->sendGridEvents(), timestamp: now()->subMinutes(6)->getTimestamp())->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('sendgrid', 'stale_timestamp');
    }

    public function test_sendgrid_rejects_a_replayed_request(): void
    {
        $timestamp = (string) now()->getTimestamp();
        $body = $this->sendGridEvents('soft@example.com', 'deferred');
        $headers = [
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => $this->sendGridSignature($timestamp . $body),
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $timestamp,
        ];

        $this->postRaw($this->url('sendgrid'), $body, $headers)->assertOk();
        $this->postRaw($this->url('sendgrid'), $body, $headers)->assertUnauthorized();

        $this->assertRejected('sendgrid', 'replayed');
    }

    public function test_sendgrid_rejects_a_mailbox_without_a_key(): void
    {
        $this->mailbox->updateBounceWebhookCredentials(['sendgrid_verification_key' => null]);

        $this->sendGrid($this->sendGridEvents())->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('sendgrid', 'not_configured');
    }

    // ----------------------------------------------------------------- Mailgun

    public function test_mailgun_accepts_a_valid_signature(): void
    {
        $this->postJson($this->url('mailgun'), $this->mailgunPayload())->assertOk()->assertJson(['status' => 'ok']);

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_mailgun_rejects_a_signature_made_with_another_key(): void
    {
        $this->postJson($this->url('mailgun'), $this->mailgunPayload(key: 'not-the-signing-key'))->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('mailgun', 'invalid_signature');
    }

    public function test_mailgun_rejects_a_missing_signature(): void
    {
        $payload = $this->mailgunPayload();
        unset($payload['signature']);

        $this->postJson($this->url('mailgun'), $payload)->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('mailgun', 'missing_signature');
    }

    public function test_mailgun_rejects_a_stale_timestamp(): void
    {
        $this->postJson($this->url('mailgun'), $this->mailgunPayload(timestamp: now()->subMinutes(6)->getTimestamp()))
            ->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('mailgun', 'stale_timestamp');
    }

    public function test_mailgun_signature_cannot_be_reused_with_another_event(): void
    {
        $first = $this->mailgunPayload(recipient: 'someone@example.com');
        $this->postJson($this->url('mailgun'), $first)->assertOk();

        // Mailgun signs timestamp + token only: the same signature on a forged event
        $forged = $first;
        $forged['event-data']['recipient'] = 'reader@example.com';

        $this->postJson($this->url('mailgun'), $forged)->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('mailgun', 'replayed');
    }

    // ---------------------------------------------------------------- Postmark

    public function test_postmark_accepts_basic_auth_with_the_token(): void
    {
        $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('netsendo:' . self::TOKEN)])
            ->postJson($this->url('postmark'), $this->postmarkPayload())
            ->assertOk()->assertJson(['status' => 'ok']);

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_postmark_accepts_the_token_in_the_url(): void
    {
        $this->postJson($this->url('postmark') . '?token=' . self::TOKEN, $this->postmarkPayload())->assertOk();

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_postmark_rejects_a_wrong_password(): void
    {
        $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('netsendo:wrong-token')])
            ->postJson($this->url('postmark'), $this->postmarkPayload())
            ->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('postmark', 'invalid_token');
    }

    public function test_postmark_rejects_a_request_without_credentials(): void
    {
        $this->postJson($this->url('postmark'), $this->postmarkPayload())->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('postmark', 'missing_token');
    }

    // ----------------------------------------------------------------- Generic

    public function test_generic_accepts_the_token_header(): void
    {
        $this->withHeaders(['X-NetSendo-Webhook-Token' => self::TOKEN])
            ->postJson($this->url('generic'), ['email' => 'reader@example.com', 'bounce_type' => 'hard'])
            ->assertOk()->assertJson(['status' => 'ok']);

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_generic_accepts_a_bearer_token(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson($this->url('generic'), ['email' => 'reader@example.com'])
            ->assertOk();

        $this->assertBouncedOnlyInOwnAccount();
    }

    public function test_generic_rejects_a_wrong_token(): void
    {
        $this->withHeaders(['X-NetSendo-Webhook-Token' => 'wrong-token'])
            ->postJson($this->url('generic'), ['email' => 'reader@example.com'])
            ->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('generic', 'invalid_token');
    }

    public function test_generic_rejects_a_request_without_a_token(): void
    {
        $this->postJson($this->url('generic'), ['email' => 'reader@example.com'])->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('generic', 'missing_token');
    }

    public function test_generic_rejects_a_mailbox_without_a_token(): void
    {
        $this->mailbox->updateBounceWebhookCredentials(['token' => null]);

        $this->withHeaders(['X-NetSendo-Webhook-Token' => self::TOKEN])
            ->postJson($this->url('generic'), ['email' => 'reader@example.com'])
            ->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('generic', 'not_configured');
    }

    // ------------------------------------------------------------------ Common

    #[DataProvider('providers')]
    public function test_old_urls_without_a_mailbox_are_rejected(string $provider): void
    {
        $this->withHeaders(['X-NetSendo-Webhook-Token' => self::TOKEN])
            ->postJson("/webhooks/bounce/{$provider}", ['email' => 'reader@example.com'])
            ->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected($provider, 'legacy_url');
    }

    public static function providers(): array
    {
        return [['sendgrid'], ['mailgun'], ['postmark'], ['generic']];
    }

    public function test_an_unknown_mailbox_is_rejected(): void
    {
        $this->withHeaders(['X-NetSendo-Webhook-Token' => self::TOKEN])
            ->postJson('/webhooks/bounce/generic/999999', ['email' => 'reader@example.com'])
            ->assertUnauthorized();

        $this->assertNothingBounced();
        $this->assertRejected('generic', 'unknown_mailbox');
    }

    public function test_a_mailbox_only_bounces_its_owners_subscribers(): void
    {
        $otherMailbox = $this->mailboxFor($this->otherUser, ['token' => 'other-account-token']);

        $this->withHeaders(['X-NetSendo-Webhook-Token' => 'other-account-token'])
            ->postJson("/webhooks/bounce/generic/{$otherMailbox->id}", ['email' => 'reader@example.com'])
            ->assertOk();

        $this->assertSame('bounced', $this->pivotStatus($this->otherSubscriber, $this->otherList));
        $this->assertSame('active', $this->pivotStatus($this->subscriber, $this->list));
        Event::assertDispatched(EmailBounced::class, fn ($e) => $e->subscriberId === $this->otherSubscriber->id);
        Event::assertNotDispatched(EmailBounced::class, fn ($e) => $e->subscriberId === $this->subscriber->id);
    }

    public function test_another_mailboxs_token_does_not_open_this_mailbox(): void
    {
        $this->mailboxFor($this->otherUser, ['token' => 'other-account-token']);

        $this->withHeaders(['X-NetSendo-Webhook-Token' => 'other-account-token'])
            ->postJson($this->url('generic'), ['email' => 'reader@example.com'])
            ->assertUnauthorized();

        $this->assertNothingBounced();
    }

    // ----------------------------------------------------------------- Helpers

    private function mailboxFor(User $user, array $webhookCredentials): Mailbox
    {
        return Mailbox::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
            'bounce_webhook_credentials' => $webhookCredentials,
        ]);
    }

    private function subscriberOn(ContactList $list, string $email): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $list->user_id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $subscriber->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()]);

        return $subscriber;
    }

    private function url(string $provider): string
    {
        return "/webhooks/bounce/{$provider}/{$this->mailbox->id}";
    }

    /** @return array{0: \OpenSSLAsymmetricKey, 1: string} private key, base64 public key as SendGrid shows it */
    private function ecKeyPair(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $pem = openssl_pkey_get_details($key)['key'];

        return [$key, preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $pem)];
    }

    private function sendGridSignature(string $data, $privateKey = null): string
    {
        openssl_sign($data, $signature, $privateKey ?? $this->sendGridPrivateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function sendGridEvents(string $email = 'reader@example.com', string $event = 'bounce'): string
    {
        return json_encode([
            ['email' => $email, 'event' => $event, 'type' => 'bounce', 'reason' => '550 5.1.1 User unknown', 'timestamp' => now()->getTimestamp()],
            ['email' => 'opened@example.com', 'event' => 'open', 'timestamp' => now()->getTimestamp()],
        ]);
    }

    private function sendGrid(string $body, $signWith = null, ?int $timestamp = null): TestResponse
    {
        $timestamp = (string) ($timestamp ?? now()->getTimestamp());

        return $this->postRaw($this->url('sendgrid'), $body, [
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => $this->sendGridSignature($timestamp . $body, $signWith),
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $timestamp,
        ]);
    }

    private function postRaw(string $uri, string $body, array $server = []): TestResponse
    {
        return $this->call('POST', $uri, [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $server), $body);
    }

    private function mailgunPayload(
        string $key = self::MAILGUN_KEY,
        ?int $timestamp = null,
        string $recipient = 'reader@example.com'
    ): array {
        $timestamp = (string) ($timestamp ?? now()->getTimestamp());
        $token = bin2hex(random_bytes(25));

        return [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => hash_hmac('sha256', $timestamp . $token, $key),
            ],
            'event-data' => [
                'event' => 'failed',
                'severity' => 'permanent',
                'recipient' => $recipient,
                'delivery-status' => ['message' => '550 5.1.1 User unknown'],
            ],
        ];
    }

    private function postmarkPayload(): array
    {
        return [
            'RecordType' => 'Bounce',
            'Type' => 'HardBounce',
            'Email' => 'reader@example.com',
            'Description' => 'The server was unable to deliver your message',
        ];
    }

    private function pivotStatus(Subscriber $subscriber, ContactList $list): ?string
    {
        return $subscriber->contactLists()->where('contact_lists.id', $list->id)->first()?->pivot->status;
    }

    private function assertBouncedOnlyInOwnAccount(): void
    {
        $this->assertSame('bounced', $this->pivotStatus($this->subscriber, $this->list));
        $this->assertSame('active', $this->pivotStatus($this->otherSubscriber, $this->otherList));
        Event::assertDispatched(EmailBounced::class, fn ($e) => $e->subscriberId === $this->subscriber->id);
        Event::assertNotDispatched(EmailBounced::class, fn ($e) => $e->subscriberId === $this->otherSubscriber->id);
    }

    private function assertNothingBounced(): void
    {
        $this->assertSame('active', $this->pivotStatus($this->subscriber, $this->list));
        $this->assertSame('active', $this->pivotStatus($this->otherSubscriber, $this->otherList));
        Event::assertNotDispatched(EmailBounced::class, fn ($e) => in_array(
            $e->subscriberId,
            [$this->subscriber->id, $this->otherSubscriber->id],
            true
        ));
    }

    private function assertRejected(string $provider, string $reason): void
    {
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'Bounce webhook rejected'
                && ($context['provider'] ?? null) === $provider
                && ($context['reason'] ?? null) === $reason)
            ->once();
    }
}
