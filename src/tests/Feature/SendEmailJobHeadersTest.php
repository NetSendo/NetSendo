<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Mail\Providers\SmtpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * Header resolution in SendEmailJob for CRON sends, where the job gets no
 * explicit mailbox:
 *  - the list's sending headers were read from $subscriber->contactList, a
 *    relation removed long ago, so they never applied;
 *  - the mailbox's custom headers and bounce Return-Path came from the job's
 *    mailbox or the message's, never from the mailbox that actually sent
 *    (list default, account default), so CRON mail went without them;
 *  - SMTP and Gmail added Return-Path as a text header, which Symfony rejects:
 *    every send with a bounce mailbox failed.
 */
class SendEmailJobHeadersTest extends TestCase
{
    use RefreshDatabase;
    use RecordsSentMail;

    private User $user;
    private ContactList $list;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);
    }

    private function mailbox(array $attributes = []): Mailbox
    {
        return Mailbox::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Mailbox',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'is_default' => false,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ], $attributes));
    }

    private function message(array $attributes = []): Message
    {
        $message = Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
        ], $attributes));
        $message->contactLists()->attach($this->list->id);

        return $message;
    }

    public function test_list_sending_headers_apply(): void
    {
        $this->mailbox(['is_default' => true]);
        $this->list->update(['settings' => ['sending' => ['headers' => [
            'list_unsubscribe' => '<mailto:leave@example.com>, <[[unsubscribe_url]]>',
            'X-Campaign-Source' => 'list-[[email]]',
        ]]]]);

        $sent = $this->runSendJob($this->message(), $this->subscriber);

        $this->assertStringStartsWith('<mailto:leave@example.com>, <http://localhost/unsubscribe/', $sent['headers']['List-Unsubscribe']);
        $this->assertSame('list-reader@example.com', $sent['headers']['X-Campaign-Source']);
    }

    public function test_list_headers_override_account_defaults(): void
    {
        $this->mailbox(['is_default' => true]);
        $this->user->update(['settings' => ['sending' => ['headers' => ['X-Team' => 'account', 'X-Only-Account' => 'yes']]]]);
        $this->list->update(['settings' => ['sending' => ['headers' => ['X-Team' => 'list']]]]);

        $sent = $this->runSendJob($this->message(), $this->subscriber);

        $this->assertSame('list', $sent['headers']['X-Team']);
        $this->assertSame('yes', $sent['headers']['X-Only-Account']);
    }

    public function test_cron_send_through_the_list_default_mailbox_gets_its_headers_and_return_path(): void
    {
        $this->mailbox(['is_default' => true, 'name' => 'Account default']);
        $listMailbox = $this->mailbox([
            'name' => 'List default',
            'custom_headers' => [['key' => 'X-Mailbox', 'value' => 'list-default']],
            'bounce_enabled' => true,
            'bounce_imap_credentials' => ['username' => 'bounces@example.com', 'password' => 'x'],
        ]);
        $this->list->update(['default_mailbox_id' => $listMailbox->id]);

        $sent = $this->runSendJob($this->message(), $this->subscriber);

        $this->assertSame($listMailbox->id, $sent['mailbox_id']);
        $this->assertSame('list-default', $sent['headers']['X-Mailbox']);
        $this->assertSame('bounces@example.com', $sent['headers']['Return-Path']);
    }

    public function test_cron_send_through_the_account_default_mailbox_gets_its_headers_and_return_path(): void
    {
        $default = $this->mailbox([
            'is_default' => true,
            'custom_headers' => [['key' => 'X-Mailbox', 'value' => 'account-default']],
            'bounce_enabled' => true,
            'bounce_imap_credentials' => ['username' => 'bounces@example.com', 'password' => 'x'],
        ]);

        $sent = $this->runSendJob($this->message(), $this->subscriber);

        $this->assertSame($default->id, $sent['mailbox_id']);
        $this->assertSame('account-default', $sent['headers']['X-Mailbox']);
        $this->assertSame('bounces@example.com', $sent['headers']['Return-Path']);
    }

    public function test_fallback_mailbox_headers_replace_those_of_a_mailbox_not_allowed_to_send(): void
    {
        // The message's mailbox may not send autoresponders: another one sends
        $broadcastOnly = $this->mailbox([
            'allowed_types' => ['broadcast'],
            'custom_headers' => [['key' => 'X-Mailbox', 'value' => 'broadcast-only']],
        ]);
        $this->mailbox(['is_default' => true, 'custom_headers' => [['key' => 'X-Mailbox', 'value' => 'fallback']]]);

        $sent = $this->runSendJob($this->message(['type' => 'autoresponder', 'mailbox_id' => $broadcastOnly->id]), $this->subscriber);

        $this->assertSame('fallback', $sent['headers']['X-Mailbox']);
    }

    public function test_return_path_cannot_be_set_through_custom_headers(): void
    {
        $this->mailbox(['is_default' => true, 'custom_headers' => [['key' => 'Return-Path', 'value' => 'spoof@example.com']]]);

        $sent = $this->runSendJob($this->message(), $this->subscriber);

        $this->assertArrayNotHasKey('Return-Path', $sent['headers']);
    }

    public function test_smtp_sets_return_path_as_the_envelope_sender_instead_of_failing(): void
    {
        $provider = new SmtpProvider('localhost', 1025, 'none', '', '', 'news@example.com', 'News');
        $transport = new class extends AbstractTransport {
            public array $messages = [];

            protected function doSend(SentMessage $message): void
            {
                $this->messages[] = $message;
            }

            public function __toString(): string
            {
                return 'capture://';
            }
        };
        (new \ReflectionProperty(SmtpProvider::class, 'mailer'))->setValue($provider, new Mailer($transport));

        $provider->send('reader@example.com', 'Reader', 'Hello', '<p>Hi</p>', [
            'Return-Path' => 'bounces@example.com',
            'X-Mailbox' => 'main',
        ]);

        $sent = $transport->messages[0];
        $this->assertSame('bounces@example.com', $sent->getEnvelope()->getSender()->getAddress());
        $this->assertSame('main', $sent->getOriginalMessage()->getHeaders()->get('X-Mailbox')->getBodyAsString());
    }
}
