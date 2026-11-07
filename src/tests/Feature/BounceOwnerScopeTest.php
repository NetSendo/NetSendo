<?php

namespace Tests\Feature;

use App\Events\EmailBounced;
use App\Jobs\SendEmailJob;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Mail\BounceMailboxService;
use App\Services\Mail\GmailOAuthService;
use App\Services\Mail\MailProviderInterface;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Bounces read from a bounce mailbox (IMAP) or from an SMTP error at send time
 * used to mark the first subscriber with that address in any account of the
 * instance. They now only touch the account that sent the mail.
 */
class BounceOwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private ContactList $list;
    private ContactList $otherList;
    private Subscriber $subscriber;
    private Subscriber $otherSubscriber;
    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([EmailBounced::class]);

        // The other account's subscriber is created first, so an unscoped
        // lookup by email would find it.
        $this->otherUser = User::factory()->create();
        $this->otherList = ContactList::create(['user_id' => $this->otherUser->id, 'name' => 'Other', 'type' => 'email']);
        $this->otherSubscriber = $this->subscriberOn($this->otherList);

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->subscriber = $this->subscriberOn($this->list);

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
    }

    private function subscriberOn(ContactList $list): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $list->user_id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $subscriber->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()]);

        return $subscriber;
    }

    private function pivotStatus(Subscriber $subscriber, ContactList $list): ?string
    {
        return $subscriber->contactLists()->where('contact_lists.id', $list->id)->first()?->pivot->status;
    }

    private function assertOnlyOwnSubscriberBounced(): void
    {
        $this->assertSame('bounced', $this->pivotStatus($this->subscriber, $this->list));
        $this->assertSame('active', $this->pivotStatus($this->otherSubscriber, $this->otherList));
        Event::assertDispatched(EmailBounced::class, fn ($e) => $e->subscriberId === $this->subscriber->id);
        Event::assertNotDispatched(EmailBounced::class, fn ($e) => $e->subscriberId === $this->otherSubscriber->id);
    }

    public function test_a_bounce_from_the_bounce_mailbox_touches_only_the_mailbox_owner(): void
    {
        $dsn = "This is the mail system at host mx.example.com.\n\n"
            . "Final-Recipient: rfc822; reader@example.com\n"
            . "Action: failed\n"
            . "Status: 5.1.1\n"
            . "Diagnostic-Code: smtp; 550 5.1.1 User unknown\n";

        $message = new class($dsn) {
            public function __construct(private string $body) {}

            public function getSubject(): object
            {
                return new class {
                    public function toString(): string
                    {
                        return 'Undelivered Mail Returned to Sender';
                    }
                };
            }

            public function getTextBody(): string
            {
                return $this->body;
            }

            public function getHTMLBody(): ?string
            {
                return null;
            }

            public function getHeader(): ?object
            {
                return null;
            }

            public function getRawBody(): string
            {
                return $this->body;
            }
        };

        $service = app(BounceMailboxService::class);
        $result = (fn ($message, $mailbox) => $this->processMessage($message, $mailbox))
            ->call($service, $message, $this->mailbox);

        $this->assertSame('reader@example.com', $result['email']);
        $this->assertOnlyOwnSubscriberBounced();
    }

    public function test_an_smtp_rejection_at_send_time_touches_only_the_senders_subscriber(): void
    {
        $this->app->instance(MailProviderService::class, new class(app(GmailOAuthService::class)) extends MailProviderService {
            public function getProvider(Mailbox $mailbox): MailProviderInterface
            {
                return new class implements MailProviderInterface {
                    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = [], array $attachments = [], ?string $textContent = null, ?bool $trackingEnabled = null): bool
                    {
                        throw new \RuntimeException('Expected response code 250 but got code "550", with message "550 5.1.1 User unknown"');
                    }

                    public function testConnection(?string $toEmail = null): array
                    {
                        return ['success' => true, 'message' => 'ok'];
                    }

                    public function getProviderName(): string
                    {
                        return 'Rejecting';
                    }
                };
            }
        });

        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
        ]);
        $message->contactLists()->attach($this->list->id);

        (new SendEmailJob($message->fresh(), $this->subscriber->fresh(), $this->mailbox))
            ->handle(app(MailProviderService::class), app(PlaceholderService::class));

        $this->assertOnlyOwnSubscriberBounced();
    }
}
