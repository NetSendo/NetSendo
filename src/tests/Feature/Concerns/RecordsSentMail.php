<?php

namespace Tests\Feature\Concerns;

use App\Jobs\SendEmailJob;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Subscriber;
use App\Services\Mail\GmailOAuthService;
use App\Services\Mail\MailProviderInterface;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;

/**
 * Runs SendEmailJob against a provider that records what it was asked to
 * send, with the real mailbox resolution of MailProviderService.
 */
trait RecordsSentMail
{
    /** @var array<int, array{to: string, subject: string, html: string, headers: array, text: ?string, tracking: ?bool, mailbox_id: int}> */
    protected array $sentMail = [];

    protected function recordSentMail(): void
    {
        $sent = &$this->sentMail;

        $this->app->instance(MailProviderService::class, new class(app(GmailOAuthService::class), $sent) extends MailProviderService {
            private array $sent;

            public function __construct(GmailOAuthService $gmail, array &$sent)
            {
                parent::__construct($gmail);
                $this->sent = &$sent;
            }

            public function getProvider(Mailbox $mailbox): MailProviderInterface
            {
                $sent = &$this->sent;

                return new class($mailbox, $sent) implements MailProviderInterface {
                    private array $sent;

                    public function __construct(private Mailbox $mailbox, array &$sent)
                    {
                        $this->sent = &$sent;
                    }

                    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = [], array $attachments = [], ?string $textContent = null, ?bool $trackingEnabled = null): bool
                    {
                        $this->sent[] = [
                            'to' => $to,
                            'subject' => $subject,
                            'html' => $htmlContent,
                            'headers' => $headers,
                            'text' => $textContent,
                            'tracking' => $trackingEnabled,
                            'mailbox_id' => $this->mailbox->id,
                        ];

                        return true;
                    }

                    public function testConnection(?string $toEmail = null): array
                    {
                        return ['success' => true, 'message' => 'ok'];
                    }

                    public function getProviderName(): string
                    {
                        return 'Recording';
                    }
                };
            }
        });
    }

    /**
     * Run the job in-process, as a queue worker would, and return what was sent.
     */
    protected function runSendJob(Message $message, Subscriber $subscriber, ?Mailbox $mailbox = null): ?array
    {
        $before = count($this->sentMail);

        (new SendEmailJob($message->fresh(), $subscriber->fresh(), $mailbox))
            ->handle(app(MailProviderService::class), app(PlaceholderService::class));

        return count($this->sentMail) > $before ? end($this->sentMail) : null;
    }
}
