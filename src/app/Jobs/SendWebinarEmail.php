<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Models\WebinarRegistration;
use App\Services\Mail\MailProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class SendWebinarEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TYPE_REGISTRATION = 'registration';
    public const TYPE_REMINDER_24H = 'reminder_24h';
    public const TYPE_REMINDER_1H = 'reminder_1h';
    public const TYPE_REMINDER_15MIN = 'reminder_15min';
    public const TYPE_STARTED = 'started';
    public const TYPE_REPLAY = 'replay';

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    public function __construct(
        public WebinarRegistration $registration,
        public string $emailType,
        public int $mailboxId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MailProviderService $providerService): void
    {
        $mailbox = Mailbox::find($this->mailboxId);

        if (!$mailbox || !$mailbox->is_active) {
            Log::error('SendWebinarEmail: Mailbox not found or inactive', [
                'mailbox_id' => $this->mailboxId,
                'registration_id' => $this->registration->id,
                'email_type' => $this->emailType,
            ]);
            return;
        }

        try {
            $provider = $providerService->getProvider($mailbox);

            $emailData = $this->getEmailData();

            $success = $provider->send(
                to: $this->registration->email,
                toName: $this->registration->first_name ?? '',
                subject: $emailData['subject'],
                htmlContent: $emailData['html'],
                headers: [],
                attachments: []
            );

            if ($success) {
                $mailbox->incrementSentCount();
                Log::info('SendWebinarEmail: Email sent successfully', [
                    'registration_id' => $this->registration->id,
                    'email_type' => $this->emailType,
                    'mailbox' => $mailbox->name,
                ]);
            } else {
                Log::error('SendWebinarEmail: Failed to send email', [
                    'registration_id' => $this->registration->id,
                    'email_type' => $this->emailType,
                    'mailbox' => $mailbox->name,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SendWebinarEmail: Exception while sending', [
                'registration_id' => $this->registration->id,
                'email_type' => $this->emailType,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for retry
        }
    }

    /**
     * Get email subject and rendered HTML content.
     */
    protected function getEmailData(): array
    {
        $webinar = $this->registration->webinar;
        $registration = $this->registration;

        return match ($this->emailType) {
            self::TYPE_REGISTRATION => [
                'subject' => 'Potwierdzenie rejestracji: ' . $webinar->name,
                'html' => $this->renderMarkdownEmail('emails.webinar.registration-confirmation', [
                    'webinar' => $webinar,
                    'registration' => $registration,
                    'watchUrl' => $registration->watch_url,
                ]),
            ],

            self::TYPE_REMINDER_24H => [
                'subject' => 'Przypomnienie: Webinar jutro! - ' . $webinar->name,
                'html' => $this->renderMarkdownEmail('emails.webinar.reminder', [
                    'webinar' => $webinar,
                    'registration' => $registration,
                    'reminderType' => '24h',
                    'watchUrl' => $registration->watch_url,
                ]),
            ],

            self::TYPE_REMINDER_1H => [
                'subject' => 'Za godzinę zaczynamy! - ' . $webinar->name,
                'html' => $this->renderMarkdownEmail('emails.webinar.reminder', [
                    'webinar' => $webinar,
                    'registration' => $registration,
                    'reminderType' => '1h',
                    'watchUrl' => $registration->watch_url,
                ]),
            ],

            self::TYPE_REMINDER_15MIN => [
                'subject' => 'Już za 15 minut! - ' . $webinar->name,
                'html' => $this->renderMarkdownEmail('emails.webinar.reminder', [
                    'webinar' => $webinar,
                    'registration' => $registration,
                    'reminderType' => '15min',
                    'watchUrl' => $registration->watch_url,
                ]),
            ],

            self::TYPE_STARTED => [
                'subject' => '🔴 NA ŻYWO: ' . $webinar->name,
                'html' => $this->renderMarkdownEmail('emails.webinar.started', [
                    'webinar' => $webinar,
                    'registration' => $registration,
                    'watchUrl' => $registration->watch_url,
                ]),
            ],

            self::TYPE_REPLAY => [
                'subject' => 'Nagranie dostępne: ' . $webinar->name,
                'html' => $this->renderMarkdownEmail('emails.webinar.replay-available', [
                    'webinar' => $webinar,
                    'registration' => $registration,
                    'replayUrl' => $registration->replay_url,
                ]),
            ],

            default => throw new \InvalidArgumentException("Unknown email type: {$this->emailType}"),
        };
    }

    /**
     * Render a markdown email template to HTML.
     */
    protected function renderMarkdownEmail(string $view, array $data): string
    {
        $markdown = new \Illuminate\Mail\Markdown(View::getFinder(), config('mail.markdown', []));

        return $markdown->render($view, $data)->toHtml();
    }
}
