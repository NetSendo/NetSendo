<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Settings → Mailboxes: the secrets that authenticate a mailbox's bounce
 * webhooks (SendGrid verification key, Mailgun signing key, NetSendo token).
 */
class MailboxBounceWebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Mailbox $mailbox;
    private string $sendGridKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'smtp.mailgun.org', 'port' => 587, 'username' => 'u', 'password' => 'p'],
        ]);

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->sendGridKey = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', openssl_pkey_get_details($key)['key']);
    }

    private function updatePayload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'daily_limit' => null,
        ], $extra);
    }

    public function test_keys_are_saved_encrypted(): void
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($this->sendGridKey, 64, "\n") . "-----END PUBLIC KEY-----\n";

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), $this->updatePayload([
                'bounce_webhook_sendgrid_key' => $pem,
                'bounce_webhook_mailgun_key' => '  mailgun-signing-key  ',
            ]))
            ->assertSessionHasNoErrors();

        $credentials = $this->mailbox->fresh()->getDecryptedBounceWebhookCredentials();
        $this->assertSame($this->sendGridKey, $credentials['sendgrid_verification_key']);
        $this->assertSame('mailgun-signing-key', $credentials['mailgun_signing_key']);

        $raw = DB::table('mailboxes')->where('id', $this->mailbox->id)->value('bounce_webhook_credentials');
        $this->assertStringNotContainsString('mailgun-signing-key', $raw);
        $this->assertStringNotContainsString(substr($this->sendGridKey, 0, 40), $raw);
    }

    public function test_an_invalid_sendgrid_key_is_refused(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), $this->updatePayload([
                'bounce_webhook_sendgrid_key' => 'not-a-public-key',
            ]))
            ->assertSessionHasErrors('bounce_webhook_sendgrid_key');

        $this->assertSame([], $this->mailbox->fresh()->getDecryptedBounceWebhookCredentials());
    }

    public function test_empty_fields_keep_the_saved_keys_and_remove_deletes_them(): void
    {
        $this->mailbox->updateBounceWebhookCredentials([
            'token' => 'kept-token',
            'sendgrid_verification_key' => $this->sendGridKey,
            'mailgun_signing_key' => 'mailgun-signing-key',
        ]);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), $this->updatePayload([
                'bounce_webhook_sendgrid_key' => '',
                'bounce_webhook_mailgun_key' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'token' => 'kept-token',
            'sendgrid_verification_key' => $this->sendGridKey,
            'mailgun_signing_key' => 'mailgun-signing-key',
        ], $this->mailbox->fresh()->getDecryptedBounceWebhookCredentials());

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), $this->updatePayload([
                'bounce_webhook_remove' => ['sendgrid', 'mailgun'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['token' => 'kept-token'], $this->mailbox->fresh()->getDecryptedBounceWebhookCredentials());
    }

    public function test_the_owner_can_generate_and_replace_the_token(): void
    {
        $first = $this->actingAs($this->user)
            ->postJson(route('settings.mailboxes.bounce-webhook-token', $this->mailbox))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('bounce_webhook.urls.generic', url("/webhooks/bounce/generic/{$this->mailbox->id}"))
            ->json('bounce_webhook.token');

        $this->assertSame(48, strlen($first));
        $this->assertSame($first, $this->mailbox->fresh()->getDecryptedBounceWebhookCredentials()['token']);

        $second = $this->postJson(route('settings.mailboxes.bounce-webhook-token', $this->mailbox))->json('bounce_webhook.token');
        $this->assertNotSame($first, $second);

        // The replaced token no longer authenticates
        $this->withHeaders(['X-NetSendo-Webhook-Token' => $first])
            ->postJson("/webhooks/bounce/generic/{$this->mailbox->id}", ['email' => 'reader@example.com'])
            ->assertUnauthorized();
    }

    public function test_another_user_cannot_generate_a_token(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('settings.mailboxes.bounce-webhook-token', $this->mailbox))
            ->assertForbidden();

        $this->assertSame([], $this->mailbox->fresh()->getDecryptedBounceWebhookCredentials());
    }

    public function test_the_mailboxes_page_shows_urls_and_which_secrets_are_set_but_not_the_mailgun_key(): void
    {
        $this->withoutVite();
        $this->mailbox->updateBounceWebhookCredentials([
            'token' => 'visible-token',
            'mailgun_signing_key' => 'secret-mailgun-key',
        ]);

        $response = $this->actingAs($this->user)->get(route('settings.mailboxes.index'));

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mailboxes.0.bounce_webhook.urls.sendgrid', url("/webhooks/bounce/sendgrid/{$this->mailbox->id}"))
            ->where('mailboxes.0.bounce_webhook.urls.mailgun', url("/webhooks/bounce/mailgun/{$this->mailbox->id}"))
            ->where('mailboxes.0.bounce_webhook.urls.postmark', url("/webhooks/bounce/postmark/{$this->mailbox->id}"))
            ->where('mailboxes.0.bounce_webhook.token', 'visible-token')
            ->where('mailboxes.0.bounce_webhook.sendgrid_key_set', false)
            ->where('mailboxes.0.bounce_webhook.mailgun_key_set', true)
        );
        $this->assertStringNotContainsString('secret-mailgun-key', $response->getContent());
    }
}
