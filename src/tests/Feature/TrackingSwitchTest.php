<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Mail\Providers\SendGridProvider;
use App\Services\Mail\Providers\SmtpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * Click and open tracking can be switched off per mailbox and per message.
 * Off: links are not rewritten to /t/click, no open pixel is added, and
 * SendGrid's own tracking is switched off for that message (tracking_settings
 * through the API, X-SMTPAPI through its SMTP relay).
 */
class TrackingSwitchTest extends TestCase
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
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
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
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);
    }

    private function send(?bool $messageTracking, bool $mailboxTracking): array
    {
        $this->mailbox->update(['tracking_enabled' => $mailboxTracking]);

        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<html><body><p><a href="https://example.org/offer">Offer</a></p></body></html>',
            'status' => 'scheduled',
            'tracking_enabled' => $messageTracking,
        ]);
        $message->contactLists()->attach($this->list->id);

        return $this->runSendJob($message, $this->subscriber);
    }

    public static function switches(): array
    {
        // message switch, mailbox switch, tracked?
        return [
            'default: tracked' => [null, true, true],
            'mailbox off' => [null, false, false],
            'message off' => [false, true, false],
            'message on beats mailbox off' => [true, false, true],
        ];
    }

    #[DataProvider('switches')]
    public function test_message_switch_else_mailbox_switch_decides(?bool $message, bool $mailbox, bool $tracked): void
    {
        $sent = $this->send($message, $mailbox);

        $this->assertSame($tracked, str_contains($sent['html'], '/t/click/'), 'click tracking');
        $this->assertSame($tracked, str_contains($sent['html'], '/t/open/'), 'open pixel');
        $this->assertSame($tracked, $sent['tracking'], 'switch passed to the provider');

        if (!$tracked) {
            $this->assertStringContainsString('href="https://example.org/offer"', $sent['html']);
        }
    }

    public function test_sendgrid_switches_its_own_tracking_off_per_message(): void
    {
        Http::fake(['api.sendgrid.com/*' => Http::response('', 202)]);
        $provider = new SendGridProvider('SG.key', 'news@example.com', 'News');

        $provider->send('reader@example.com', 'Reader', 'Off', '<p>x</p>', ['Return-Path' => 'b@example.com'], [], null, false);
        $provider->send('reader@example.com', 'Reader', 'Default', '<p>x</p>', [], [], null, true);

        $requests = Http::recorded()->map(fn ($pair) => $pair[0]->data());

        $this->assertSame([
            'click_tracking' => ['enable' => false, 'enable_text' => false],
            'open_tracking' => ['enable' => false],
        ], $requests[0]['tracking_settings']);
        $this->assertArrayNotHasKey('headers', $requests[0], 'SendGrid sets the envelope sender itself');
        $this->assertArrayNotHasKey('tracking_settings', $requests[1], 'On: the account settings apply');
    }

    public function test_sendgrid_smtp_relay_gets_x_smtpapi_when_tracking_is_off(): void
    {
        $relay = $this->smtp('smtp.sendgrid.net');
        $relay['provider']->send('reader@example.com', 'Reader', 'Off', '<p>x</p>', [], [], null, false);

        $header = $relay['transport']->messages[0]->getOriginalMessage()->getHeaders()->get('X-SMTPAPI')->getBodyAsString();
        $this->assertSame(
            ['filters' => ['clicktrack' => ['settings' => ['enable' => 0, 'enable_text' => 0]], 'opentrack' => ['settings' => ['enable' => 0]]]],
            json_decode($header, true)
        );

        $other = $this->smtp('mail.example.com');
        $other['provider']->send('reader@example.com', 'Reader', 'Off', '<p>x</p>', [], [], null, false);
        $this->assertFalse($other['transport']->messages[0]->getOriginalMessage()->getHeaders()->has('X-SMTPAPI'));
    }

    public function test_switches_are_saved_through_the_api(): void
    {
        $key = ApiKey::generate($this->user->id, 'Agent', ['messages:write', 'email:write'])['key'];
        $api = $this->withHeaders(['Authorization' => 'Bearer ' . $key]);

        $id = $api->postJson('/api/v1/messages', [
            'subject' => 'Weekly',
            'channel' => 'email',
            'type' => 'broadcast',
            'content' => '<p>Hi</p>',
            'tracking_enabled' => false,
            'contact_list_ids' => [$this->list->id],
        ])->assertCreated()->assertJsonPath('data.tracking_enabled', false)->json('data.id');

        $api->putJson("/api/v1/messages/{$id}", ['tracking_enabled' => null])
            ->assertOk()
            ->assertJsonPath('data.tracking_enabled', null);

        $sendId = $api->postJson('/api/v1/email/send', [
            'email' => 'reader@example.com',
            'subject' => 'Receipt',
            'content' => '<p>Receipt</p>',
            'tracking_enabled' => false,
        ])->assertStatus(202)->json('data.id');

        $this->assertFalse(Message::find($sendId)->tracking_enabled);
    }

    public function test_mailbox_switch_is_saved_in_the_panel(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), [
                'name' => 'Main',
                'provider' => 'smtp',
                'from_email' => 'news@example.com',
                'from_name' => 'News',
                'allowed_types' => ['broadcast'],
                'daily_limit' => null,
                'tracking_enabled' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->mailbox->fresh()->tracking_enabled);
    }

    /**
     * @return array{provider: SmtpProvider, transport: AbstractTransport}
     */
    private function smtp(string $host): array
    {
        $provider = new SmtpProvider($host, 587, 'none', 'apikey', 'secret', 'news@example.com', 'News');
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

        return ['provider' => $provider, 'transport' => $transport];
    }
}
