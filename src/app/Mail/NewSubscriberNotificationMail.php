<?php

namespace App\Mail;

use App\Models\Subscriber;
use App\Models\ContactList;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSubscriberNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public ContactList $list,
        public string $messageContent
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.new_subscriber_notification_subject', ['list' => $this->list->name]),
        );
    }

    public function content(): Content
    {
        // Replace placeholders in the system message content
        $content = $this->messageContent;
        $content = str_replace('[[list-name]]', $this->list->name, $content);
        $content = str_replace('[[email]]', $this->subscriber->email, $content);
        $content = str_replace('[[date]]', now()->format('Y-m-d H:i'), $content);
        
        // Add subscriber custom fields if available
        if ($this->subscriber->first_name) {
            $content = str_replace('[[first-name]]', $this->subscriber->first_name, $content);
        }
        if ($this->subscriber->last_name) {
            $content = str_replace('[[last-name]]', $this->subscriber->last_name, $content);
        }

        return new Content(
            htmlString: $this->wrapInEmailTemplate($content),
        );
    }

    /**
     * Wrap content in a simple email template
     */
    protected function wrapInEmailTemplate(string $content): string
    {
        $appName = config('app.name', 'NetSendo');
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$this->list->name}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1, h2 { color: #1a1a1a; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    {$content}
    <div class="footer">
        <p>Ta wiadomość została wysłana automatycznie przez {$appName}.</p>
    </div>
</body>
</html>
HTML;
    }
}
