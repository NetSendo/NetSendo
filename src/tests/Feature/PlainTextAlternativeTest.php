<?php

namespace Tests\Feature;

use App\Helpers\EmailPlainText;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageTranslation;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Mail\Providers\SendGridProvider;
use App\Services\Mail\Providers\SmtpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * Every e-mail carries a text/plain part next to the HTML: the message's own
 * plain text, or text generated from the HTML. SendGrid and SMTP used to send
 * HTML only (SpamAssassin MIME_HTML_ONLY, unreadable in text-only clients).
 */
class PlainTextAlternativeTest extends TestCase
{
    use RefreshDatabase;
    use RecordsSentMail;

    private User $user;
    private ContactList $list;
    private Mailbox $mailbox;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Readers', 'type' => 'email']);
        $this->mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);
        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'first_name' => 'Ola',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);
    }

    private function message(array $attributes = []): Message
    {
        $message = Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Hi [[fname]], read <a href="https://example.org/post">the post</a>.</p>',
            'status' => 'scheduled',
        ], $attributes));
        $message->contactLists()->attach($this->list->id);

        return $message;
    }

    // ---- HTML → text ---------------------------------------------------

    public function test_html_is_converted_to_readable_text(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html><html><head><title>T</title><style>p{color:red}</style></head>
            <body>
            <!-- Preheader text -->
            <div style="display: none; max-height: 0; overflow: hidden;">Hidden preheader&#847; &#847;</div>
            <h1>Big news</h1>
            <p>Hello&nbsp;there,<br>second   line &amp; more.</p>
            <ul><li>One</li><li><a href="https://example.org/two">Two</a></li></ul>
            <p><a href="https://example.org/">https://example.org/</a> · <a href="mailto:hi@example.org">write</a></p>
            <img src="https://example.org/logo.png" alt="Logo">
            <img src="https://t.example.org/o.gif" width="1" height="1" alt="">
            <script>alert(1)</script>
            </body></html>
            HTML;

        $text = EmailPlainText::fromHtml($html);

        $this->assertSame(
            "BIG NEWS\n\nHello there,\nsecond line & more.\n\n- One\n- Two (https://example.org/two)\n\n"
            . "https://example.org/ · write (hi@example.org)\n\nLogo",
            $text
        );
    }

    public function test_own_plain_text_wins_and_can_be_switched_off(): void
    {
        $this->assertSame("Line 1\nLine 2", EmailPlainText::forEmail("Line 1\r\nLine 2\r\n", '<p>HTML</p>'));
        $this->assertSame('HTML', EmailPlainText::forEmail('   ', '<p>HTML</p>'));

        config(['netsendo.email.plain_text_alternative' => false]);
        $this->assertNull(EmailPlainText::forEmail('Own text', '<p>HTML</p>'));
    }

    // ---- SendEmailJob ---------------------------------------------------

    public function test_generated_text_has_the_real_link_targets_not_tracking_urls(): void
    {
        $sent = $this->runSendJob($this->message(), $this->subscriber);

        $this->assertNotNull($sent);
        $this->assertStringContainsString('/t/click/', $sent['html'], 'HTML links are still tracked');
        $this->assertSame('Hi Ola, read the post (https://example.org/post).', $sent['text']);
    }

    public function test_own_plain_text_gets_placeholders_filled(): void
    {
        $message = $this->message(['plain_text' => "Hi [[fname]],\nunsubscribe: [[unsubscribe]]"]);

        $sent = $this->runSendJob($message, $this->subscriber);

        $this->assertStringStartsWith("Hi Ola,\nunsubscribe: http://localhost/unsubscribe/{$this->subscriber->id}/{$this->list->id}?signature=", $sent['text']);
    }

    public function test_translated_content_gets_text_from_the_translation_not_the_original_text(): void
    {
        $message = $this->message(['plain_text' => 'Polski tekst']);
        MessageTranslation::create([
            'message_id' => $message->id,
            'language' => 'en',
            'subject' => 'Hello EN',
            'content' => '<p>English body</p>',
        ]);
        $this->subscriber->update(['language' => 'en']);

        $sent = $this->runSendJob($message, $this->subscriber);

        $this->assertSame('English body', $sent['text']);
    }

    // ---- Providers -------------------------------------------------------

    public function test_sendgrid_sends_text_plain_before_text_html(): void
    {
        Http::fake(['api.sendgrid.com/*' => Http::response('', 202)]);

        (new SendGridProvider('SG.key', 'news@example.com', 'News'))
            ->send('reader@example.com', 'Reader', 'Hello', '<p>Hi <a href="https://example.org">there</a></p>');

        Http::assertSent(function (HttpRequest $request) {
            $content = $request->data()['content'];

            return $content[0]['type'] === 'text/plain'
                && $content[0]['value'] === 'Hi there (https://example.org)'
                && $content[1]['type'] === 'text/html'
                && !isset($request->data()['tracking_settings']);
        });
    }

    public function test_smtp_sends_multipart_alternative(): void
    {
        $transport = $this->captureSmtp($provider = new SmtpProvider('localhost', 1025, 'none', '', '', 'news@example.com', 'News'));

        $provider->send('reader@example.com', 'Reader', 'Hello', '<p>Hi</p>', [], [], 'Own text');

        /** @var Email $email */
        $email = $transport->messages[0]->getOriginalMessage();
        $this->assertSame('Own text', $email->getTextBody());
        $this->assertStringContainsString('<p>Hi</p>', $email->getHtmlBody());
        $this->assertStringContainsString('multipart/alternative', $transport->messages[0]->toString());
    }

    // ---- API ------------------------------------------------------------

    public function test_api_accepts_plain_text_on_messages_and_single_sends(): void
    {
        $key = ApiKey::generate($this->user->id, 'Agent', ['messages:write', 'email:write'])['key'];
        $api = $this->withHeaders(['Authorization' => 'Bearer ' . $key]);

        $id = $api->postJson('/api/v1/messages', [
            'subject' => 'Weekly',
            'channel' => 'email',
            'type' => 'broadcast',
            'content' => '<p>Hi</p>',
            'plain_text' => 'Hi in text',
            'contact_list_ids' => [$this->list->id],
        ])->assertCreated()->assertJsonPath('data.plain_text', 'Hi in text')->json('data.id');

        $api->putJson("/api/v1/messages/{$id}", ['plain_text' => 'Changed'])
            ->assertOk()
            ->assertJsonPath('data.plain_text', 'Changed');

        $api->putJson("/api/v1/messages/{$id}", ['plain_text' => null])->assertOk();
        $this->assertNull(Message::find($id)->plain_text, 'null clears it: text is generated again');

        $sendId = $api->postJson('/api/v1/email/send', [
            'email' => 'reader@example.com',
            'subject' => 'Receipt',
            'content' => '<p>Receipt</p>',
            'plain_text' => 'Receipt in text',
        ])->assertStatus(202)->json('data.id');

        $this->assertSame('Receipt in text', Message::find($sendId)->plain_text);
    }

    /**
     * Replace the SMTP provider's transport with one that keeps the messages.
     */
    private function captureSmtp(SmtpProvider $provider): AbstractTransport
    {
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

        $property = new \ReflectionProperty(SmtpProvider::class, 'mailer');
        $property->setValue($provider, new Mailer($transport));

        return $transport;
    }
}
