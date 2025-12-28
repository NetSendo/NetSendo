<?php

namespace App\Mail;

use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\SystemEmail;
use App\Services\PlaceholderService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic Mailable for system emails.
 * Renders any SystemEmail template with placeholder replacement.
 */
class SystemEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public ContactList $list,
        public SystemEmail $systemEmail,
        public array $extraData = []
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->replacePlaceholders($this->systemEmail->subject);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $content = $this->replacePlaceholders($this->systemEmail->content);

        return new Content(
            htmlString: $this->wrapInEmailTemplate($content),
        );
    }

    /**
     * Replace all placeholders in content.
     */
    protected function replacePlaceholders(string $content): string
    {
        // Standard placeholders
        $replacements = [
            '[[list-name]]' => $this->list->name,
            '[[email]]' => $this->subscriber->email ?? '',
            '[[first-name]]' => $this->subscriber->first_name ?? '',
            '[[fname]]' => $this->subscriber->first_name ?? '',
            '[[last-name]]' => $this->subscriber->last_name ?? '',
            '[[lname]]' => $this->subscriber->last_name ?? '',
            '[[phone]]' => $this->subscriber->phone ?? '',
            '[[date]]' => now()->format('Y-m-d H:i'),
        ];

        // Extra data placeholders (activation-link, unsubscribe-link, etc.)
        foreach ($this->extraData as $key => $value) {
            $replacements["[[{$key}]]"] = $value;
        }

        // Apply replacements
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        // Use PlaceholderService for custom fields if available
        try {
            $placeholderService = app(PlaceholderService::class);
            $content = $placeholderService->replacePlaceholders($content, $this->subscriber);
        } catch (\Exception $e) {
            // PlaceholderService not available, skip
        }

        return $content;
    }

    /**
     * Wrap content in a simple, professional email template.
     */
    protected function wrapInEmailTemplate(string $content): string
    {
        $appName = config('app.name', 'NetSendo');
        $listName = e($this->list->name);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$listName}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .content-box {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 40px;
        }
        h1, h2, h3 {
            color: #1a1a1a;
            margin-top: 0;
        }
        a {
            color: #667eea;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 16px 0;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            font-size: 12px;
            color: #888888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="content-box">
            {$content}
        </div>
        <div class="footer">
            <p>This message was sent automatically by {$appName}.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
