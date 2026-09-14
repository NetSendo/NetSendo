<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmTask;
use App\Models\LeadScoreHistory;
use App\Models\Mailbox;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Automation\AutomationActionExecutor;
use App\Services\Automation\AutomationService;
use App\Services\Mail\InboundEmail;
use App\Services\Mail\ReplyMailboxService;
use App\Services\Mail\ReplyProcessingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\Protocols\ProtocolInterface;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;

/**
 * Nothing dispatched CrmContactReplied, so a subscriber's reply never reached the
 * CRM history, the crm_contact_replied trigger or the email_replied scoring rule.
 * Replies are now read from each mailbox's reply inbox over IMAP.
 */
class CrmContactReplyDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Mailbox $mailbox;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the queued lead scoring listener inline.
        config(['queue.default' => 'sync']);

        // SQLite keeps the enums of the create_* migrations; MySQL gains
        // 'email_reply' and 'crm_contact_replied' in the 2026_09_14 migrations.
        Schema::table('crm_activities', fn (Blueprint $table) => $table->string('type')->change());
        Schema::table('automation_rules', fn (Blueprint $table) => $table->string('trigger_event')->change());

        $this->user = User::factory()->create();

        $this->mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Sales',
            'provider' => Mailbox::PROVIDER_SMTP,
            'from_email' => 'sales@firma.pl',
            'from_name' => 'Firma',
            'allowed_types' => [Mailbox::TYPE_BROADCAST],
            'credentials' => ['host' => 'smtp.firma.pl'],
            'reply_enabled' => true,
            'reply_imap_host' => 'imap.firma.pl',
            'reply_imap_credentials' => ['username' => 'biuro@firma.pl', 'password' => 'secret'],
        ]);

        $this->subscriber = $this->subscriberOf($this->user, 'anna@example.com');
    }

    public function test_a_reply_from_a_crm_contact_is_recorded_once(): void
    {
        $contact = $this->contactFor($this->subscriber);
        $email = InboundEmail::fromRaw($this->rawEmail());

        $triggers = $this->triggersFiredBy(fn () => $this->assertTrue(
            $this->processor()->process($this->mailbox, $email)?->is($contact)
        ));

        $this->assertSame(['crm_contact_replied'], $this->replyTriggers($triggers));

        $activity = $contact->activities()->where('type', 'email_reply')->sole();
        $this->assertSame('email_reply', $activity->type);
        $this->assertSame("Re: Oferta na wrzesień\n\nDzień dobry,\njestem zainteresowana ofertą.", $activity->content);
        $this->assertSame('reply-1@mail.example.com', $activity->metadata['message_id']);
        $this->assertNull($activity->created_by_id);
        $this->assertNotNull($contact->fresh()->last_activity_at);

        // The default "Odpowiedź na email" scoring rule
        $this->assertSame(25, LeadScoreHistory::where('crm_contact_id', $contact->id)->where('event_type', 'email_replied')->sole()->points_change);

        // Read again, e.g. from a second mailbox with the same inbox
        $triggers = $this->triggersFiredBy(fn () => $this->assertNull(
            $this->processor()->process($this->mailbox, $email)
        ));

        $this->assertSame([], $triggers);
        $this->assertSame(1, $contact->activities()->where('type', 'email_reply')->count());
    }

    public function test_a_crm_contact_replied_rule_runs_once_per_reply(): void
    {
        $contact = $this->contactFor($this->subscriber);

        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Call back',
            'trigger_event' => 'crm_contact_replied',
            'trigger_config' => [],
            'actions' => [['type' => 'crm_create_task', 'config' => ['title' => 'Call back']]],
            'is_active' => true,
        ]);

        $this->processor()->process($this->mailbox, InboundEmail::fromRaw($this->rawEmail()));

        $this->assertSame(1, AutomationRuleLog::where('automation_rule_id', $rule->id)->count());
        $this->assertSame(1, CrmTask::where('title', 'Call back')->where('crm_contact_id', $contact->id)->count());
    }

    public function test_a_subscriber_outside_the_crm_is_added_as_a_lead(): void
    {
        $triggers = $this->triggersFiredBy(fn () => $this->processor()->process(
            $this->mailbox,
            InboundEmail::fromRaw($this->rawEmail()),
        ));

        $contact = CrmContact::where('subscriber_id', $this->subscriber->id)->sole();

        $this->assertSame('email_reply', $contact->source);
        $this->assertSame($this->user->id, $contact->user_id);
        $this->assertContains('crm_contact_created', $triggers);
        $this->assertSame(['crm_contact_replied'], $this->replyTriggers($triggers));
        $this->assertSame(1, $contact->activities()->where('type', 'email_reply')->count());
    }

    public function test_emails_that_are_not_replies_from_a_subscriber_are_ignored(): void
    {
        $this->contactFor($this->subscriber);

        $other = User::factory()->create();
        $this->subscriberOf($other, 'piotr@example.com');

        $removed = $this->subscriberOf($this->user, 'removed@example.com');
        $this->contactFor($removed)->delete();

        $this->subscriberOf($this->user, 'biuro@firma.pl');

        $emails = [
            'new conversation' => $this->rawEmail(['In-Reply-To' => null, 'References' => null, 'Subject' => 'Pytanie o ofertę']),
            'out-of-office header' => $this->rawEmail(['Auto-Submitted' => 'auto-replied']),
            'out-of-office subject' => $this->rawEmail(['Subject' => 'Odpowiedź automatyczna: Oferta na wrzesień']),
            'newsletter' => $this->rawEmail(['List-Unsubscribe' => '<mailto:unsubscribe@example.com>']),
            'delivery report' => $this->rawEmail(['Content-Type' => 'multipart/report; report-type=delivery-status; boundary="b"']),
            'unknown sender' => $this->rawEmail(['From' => 'Jan <jan@example.com>']),
            'another user\'s subscriber' => $this->rawEmail(['From' => 'piotr@example.com']),
            'contact deleted from the CRM' => $this->rawEmail(['From' => 'removed@example.com']),
            'the reply inbox itself' => $this->rawEmail(['From' => 'Biuro <BIURO@firma.pl>']),
        ];

        $recorded = [];
        $triggers = $this->triggersFiredBy(function () use ($emails, &$recorded) {
            foreach ($emails as $case => $raw) {
                if ($this->processor()->process($this->mailbox, InboundEmail::fromRaw($raw))) {
                    $recorded[] = $case;
                }
            }
        });

        $this->assertSame([], $recorded);
        $this->assertSame([], $triggers);
        $this->assertSame(0, CrmActivity::where('type', 'email_reply')->count());
    }

    public function test_the_inbound_email_is_read_from_raw_headers_and_body(): void
    {
        $email = InboundEmail::fromRaw($this->rawEmail([
            'From' => '=?UTF-8?Q?Anna_Kowalska?= <Anna@Example.com>',
            'Subject' => '=?UTF-8?Q?Odp:_Oferta_na_wrzesie=C5=84?=',
            'In-Reply-To' => null,
            'References' => null,
        ]));

        $this->assertSame('anna@example.com', $email->fromEmail);
        $this->assertSame('Odp: Oferta na wrzesień', $email->subject);
        $this->assertSame('reply-1@mail.example.com', $email->messageId());
        $this->assertTrue($email->isReply(), 'the Odp: prefix marks a reply without In-Reply-To');
        $this->assertFalse($email->isAutomatic());

        $headersOnly = InboundEmail::fromRaw(explode("\r\n\r\n", $this->rawEmail(), 2)[0] . "\r\n\r\n");
        $this->assertSame('anna@example.com', $headersOnly->fromEmail);
        $this->assertTrue($headersOnly->isReply());

        $html = InboundEmail::fromRaw($this->rawEmail(['Content-Type' => 'text/html; charset=UTF-8'],
            '<div>Tak, poproszę&nbsp;o&nbsp;telefon.</div><div class="gmail_quote">On Mon, 14 Sep 2026 Firma wrote:<blockquote>Oferta</blockquote></div>'));
        $this->assertSame('Tak, poproszę o telefon.', $html->excerpt());

        $outlook = InboundEmail::fromRaw($this->rawEmail([], "Proszę o kontakt.\r\n\r\nFrom: Firma <sales@firma.pl>\r\nSent: Monday, September 14, 2026\r\nSubject: Oferta"));
        $this->assertSame('Proszę o kontakt.', $outlook->excerpt());

        // A body the parser rejects, and an unencoded 8-bit subject, still make a storable reply
        $malformed = InboundEmail::fromRaw($this->rawEmail(['Date' => 'yesterday afternoon', 'Subject' => "Re: Zam\xF3wienie"]));
        $this->assertTrue($malformed->isReply());
        $this->assertSame('', $malformed->excerpt());
        $this->assertTrue(mb_check_encoding($malformed->subject, 'UTF-8'));

        $long = InboundEmail::fromRaw($this->rawEmail([], str_repeat('słowo ', 100)));
        $this->assertStringEndsWith('…', $long->excerpt());
        $this->assertLessThanOrEqual(301, mb_strlen($long->excerpt()));
    }

    public function test_a_scan_reads_only_new_messages_and_never_marks_them_read(): void
    {
        $this->contactFor($this->subscriber);
        $this->mailbox->update(['reply_last_uid' => 10, 'reply_uid_validity' => 7]);

        $connection = $this->imapConnection(uidValidity: 7, uidNext: 13);
        // "11:*" also matches the newest message below 11 when nothing is newer
        $connection->shouldReceive('search')->once()->with(['UID', '11:*'], IMAP::ST_UID)
            ->andReturn($this->imapResponse(['10', '11', '12']));
        $connection->shouldReceive('fetch')->once()->with(['UID', 'BODY.PEEK[HEADER]'], [11, 12], null, IMAP::ST_UID)
            ->andReturn($this->imapResponse([
                11 => ['UID' => 11, 'BODY[HEADER]' => $this->headersOf($this->rawEmail(['From' => 'jan@example.com']))],
                12 => ['UID' => 12, 'BODY[HEADER]' => $this->headersOf($this->rawEmail())],
            ]));
        // Only the subscriber's reply is downloaded in full
        $connection->shouldReceive('fetch')->once()->with(['UID', 'BODY.PEEK[]'], [12], null, IMAP::ST_UID)
            ->andReturn($this->imapResponse([12 => ['UID' => 12, 'BODY[]' => $this->rawEmail()]]));

        $stats = $this->replyMailboxService($connection)->scanMailbox($this->mailbox);

        $this->assertSame(['scanned' => 2, 'replies_found' => 1, 'errors' => 0], $stats);
        $this->assertSame(1, CrmActivity::where('type', 'email_reply')->count());

        $this->mailbox->refresh();
        $this->assertSame(12, $this->mailbox->reply_last_uid);
        $this->assertSame(1, $this->mailbox->reply_last_scan_count);
        $this->assertNotNull($this->mailbox->reply_last_scanned_at);
    }

    public function test_a_first_scan_or_a_recreated_folder_looks_back_one_day(): void
    {
        $this->travelTo('2026-09-14 12:00:00');
        $this->mailbox->update(['reply_last_uid' => 500, 'reply_uid_validity' => 3]);

        $connection = $this->imapConnection(uidValidity: 7, uidNext: 41);
        $connection->shouldReceive('search')->once()->with(['SINCE', '13-Sep-2026'], IMAP::ST_UID)
            ->andReturn($this->imapResponse([]));
        $connection->shouldNotReceive('fetch');

        $stats = $this->replyMailboxService($connection)->scanMailbox($this->mailbox);

        $this->assertSame(['scanned' => 0, 'replies_found' => 0, 'errors' => 0], $stats);

        // The next scan continues after the messages that were already in the folder
        $this->mailbox->refresh();
        $this->assertSame(40, $this->mailbox->reply_last_uid);
        $this->assertSame(7, $this->mailbox->reply_uid_validity);
    }

    public function test_mailbox_settings_store_the_reply_inbox(): void
    {
        $settings = [
            'name' => 'Sales',
            'provider' => Mailbox::PROVIDER_SMTP,
            'from_email' => 'sales@firma.pl',
            'from_name' => 'Firma',
            'allowed_types' => [Mailbox::TYPE_BROADCAST],
            'daily_limit' => null,
            'reply_enabled' => true,
            'reply_imap_host' => 'imap.firma.pl',
            'reply_imap_port' => 993,
            'reply_imap_encryption' => 'ssl',
            'reply_imap_folder' => 'INBOX',
            'reply_imap_username' => 'biuro@firma.pl',
            'reply_imap_password' => '',
        ];
        $this->mailbox->update(['reply_last_uid' => 90, 'reply_uid_validity' => 7]);

        // Same inbox, password left empty: the stored password and read position stay
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), $settings)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->mailbox->refresh();
        $this->assertSame(['username' => 'biuro@firma.pl', 'password' => 'secret'], $this->mailbox->getDecryptedReplyCredentials());
        $this->assertStringNotContainsString('secret', $this->mailbox->getRawOriginal('reply_imap_credentials'));
        $this->assertSame(90, $this->mailbox->reply_last_uid);

        // Another server: its UIDs are unrelated, so reading starts over
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), ['reply_imap_host' => 'imap.other.pl', 'reply_imap_password' => 'new'] + $settings)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->mailbox->refresh();
        $this->assertSame('imap.other.pl', $this->mailbox->reply_imap_host);
        $this->assertSame('new', $this->mailbox->getDecryptedReplyCredentials()['password']);
        $this->assertNull($this->mailbox->reply_last_uid);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), ['reply_imap_host' => ''] + $settings)
            ->assertSessionHasErrors('reply_imap_host');
    }

    /**
     * The crm_contact_replied triggers among those fired. The scoring points for a
     * reply can also promote the contact and fire crm_contact_status_changed.
     *
     * @param list<string> $triggers
     * @return list<string>
     */
    private function replyTriggers(array $triggers): array
    {
        return array_values(array_intersect($triggers, ['crm_contact_replied']));
    }

    private function processor(): ReplyProcessingService
    {
        return app(ReplyProcessingService::class);
    }

    private function subscriberOf(User $user, string $email): Subscriber
    {
        return Subscriber::create([
            'user_id' => $user->id,
            'email' => $email,
            'status' => 'active',
        ]);
    }

    private function contactFor(Subscriber $subscriber): CrmContact
    {
        return CrmContact::create([
            'user_id' => $subscriber->user_id,
            'subscriber_id' => $subscriber->id,
            'status' => 'lead',
            'score' => 0,
        ]);
    }

    /**
     * A plain-text reply from anna@example.com, with headers replaced or removed (null).
     */
    private function rawEmail(array $headers = [], ?string $body = null): string
    {
        $headers = array_filter($headers + [
            'From' => 'Anna Kowalska <anna@example.com>',
            'To' => 'biuro@firma.pl',
            'Subject' => 'Re: Oferta na wrzesień',
            'Message-ID' => '<reply-1@mail.example.com>',
            'In-Reply-To' => '<offer-1@firma.pl>',
            'References' => '<offer-1@firma.pl>',
            'Date' => 'Mon, 14 Sep 2026 10:00:00 +0200',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ], fn ($value) => $value !== null);

        $body ??= "Dzień dobry,\r\njestem zainteresowana ofertą.\r\n\r\n"
            . "W dniu 13.09.2026 o 10:00, Firma <sales@firma.pl> pisze:\r\n"
            . "> Przesyłamy ofertę na wrzesień.\r\n";

        return implode("\r\n", array_map(fn ($name, $value) => "{$name}: {$value}", array_keys($headers), $headers))
            . "\r\n\r\n" . $body;
    }

    private function headersOf(string $raw): string
    {
        return explode("\r\n\r\n", $raw, 2)[0] . "\r\n\r\n";
    }

    /**
     * Dispatch against a recording AutomationService.
     *
     * @return list<string> trigger names passed to processEvent, in call order
     */
    private function triggersFiredBy(callable $callback): array
    {
        $recorder = new class(app(AutomationActionExecutor::class)) extends AutomationService {
            /** @var list<string> */
            public array $triggers = [];

            public function processEvent(string $triggerEvent, array $context): void
            {
                $this->triggers[] = $triggerEvent;
            }
        };

        $this->app->instance(AutomationService::class, $recorder);

        try {
            $callback();
        } finally {
            $this->app->forgetInstance(AutomationService::class);
        }

        return $recorder->triggers;
    }

    /**
     * An IMAP connection that opens INBOX read-only and fails on SELECT.
     */
    private function imapConnection(int $uidValidity, int $uidNext): ProtocolInterface&Mockery\MockInterface
    {
        $connection = Mockery::mock(ProtocolInterface::class);
        $connection->shouldReceive('examineFolder')->once()->with('INBOX')
            ->andReturn($this->imapResponse(['uidvalidity' => $uidValidity, 'uidnext' => $uidNext, 'exists' => 40]));
        $connection->shouldNotReceive('selectFolder');

        return $connection;
    }

    private function imapResponse(array $result): Response
    {
        return Response::empty()->setCanBeEmpty(true)->setResult($result);
    }

    private function replyMailboxService(ProtocolInterface $connection): ReplyMailboxService
    {
        $folder = Mockery::mock(Folder::class);
        $folder->path = 'INBOX';

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect')->andReturnSelf();
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($folder);
        $client->shouldReceive('getConnection')->andReturn($connection);
        $client->shouldReceive('disconnect')->andReturnSelf();

        return new class($this->processor(), $client) extends ReplyMailboxService {
            public function __construct(ReplyProcessingService $replyProcessor, private Client $client)
            {
                parent::__construct($replyProcessor);
            }

            protected function createImapClient(Mailbox $mailbox, array $credentials): Client
            {
                return $this->client;
            }
        };
    }
}
