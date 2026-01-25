<?php

namespace App\Mail;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for CRM task reminder notifications.
 */
class TaskReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CrmTask $task
    ) {}

    public function envelope(): Envelope
    {
        $subject = __('crm.reminders.email_subject', [
            'title' => $this->task->title,
            'default' => 'Przypomnienie: ' . $this->task->title,
        ]);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtmlContent(),
        );
    }

    /**
     * Build the HTML content for the reminder email.
     */
    /**
     * Build the HTML content for the reminder email.
     */
    protected function buildHtmlContent(): string
    {
        $task = $this->task;
        $appName = config('app.name', 'NetSendo');
        $appUrl = config('app.url');

        $contactName = '';
        if ($task->contact?->subscriber) {
            $contactName = trim(
                ($task->contact->subscriber->first_name ?? '') . ' ' .
                ($task->contact->subscriber->last_name ?? $task->contact->subscriber->email)
            );
        }

        $dealInfo = '';
        if ($task->deal) {
            $dealLabel = __('crm.reminders.deal', ['name' => e($task->deal->name)]);
            // fallback if translation fails or just simple label
            if ($dealLabel === 'crm.reminders.deal') {
                $dealLabel = '<strong>Deal:</strong> ' . e($task->deal->name);
            }
            $dealInfo = '<p style="margin: 8px 0;">' . $dealLabel . '</p>';
        }

        $dueDate = $task->due_date?->format('d.m.Y H:i') ?? '-';
        $taskUrl = "{$appUrl}/crm/tasks";

        $priorityColors = [
            'high' => '#ef4444',
            'medium' => '#f59e0b',
            'low' => '#22c55e',
        ];
        $priorityColor = $priorityColors[$task->priority] ?? '#64748b';

        $priorityKey = "crm.reminders.priorities.{$task->priority}";
        $priorityLabel = __($priorityKey);
        if ($priorityLabel === $priorityKey) {
            $priorityLabel = ucfirst($task->priority);
        }

        $typeKey = "crm.reminders.types.{$task->type}";
        $typeLabel = __($typeKey);
        if ($typeLabel === $typeKey) {
            $typeLabel = ucfirst($task->type);
        }

        // Translation strings
        $titleText = __('crm.reminders.title');
        $subtitleText = __('crm.reminders.subtitle');
        $typeCaption = __('crm.reminders.type');
        $dateCaption = __('crm.reminders.due_date');
        $priorityCaption = __('crm.reminders.priority');
        $buttonText = __('crm.reminders.view_in_crm');
        $footerAuto = __('crm.reminders.footer_auto', ['appName' => $appName]);
        $footerSettings = __('crm.reminders.footer_settings');

        return <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$titleText}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1e293b;
            background-color: #f1f5f9;
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
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 32px;
            border: 1px solid #e2e8f0;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px;
        }
        .task-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            border-left: 4px solid {$priorityColor};
        }
        .task-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin: 0 0 16px;
        }
        .task-meta {
            font-size: 14px;
            color: #64748b;
        }
        .task-meta p {
            margin: 8px 0;
        }
        .priority-badge {
            display: inline-block;
            background: {$priorityColor};
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            margin: 16px 0;
            text-align: center;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="content-box">
            <div class="header">
                <h1>{$titleText}</h1>
                <p style="color: #64748b; margin: 0;">{$subtitleText}</p>
            </div>

            <div class="task-card">
                <div class="task-title">{$task->title}</div>
                <div class="task-meta">
                    <p>📋 <strong>{$typeCaption}:</strong> {$typeLabel}</p>
                    <p>📅 <strong>{$dateCaption}:</strong> {$dueDate}</p>
                    <p>🎯 <strong>{$priorityCaption}:</strong> <span class="priority-badge">{$priorityLabel}</span></p>
                    {$dealInfo}
                    {$this->getContactInfo($contactName)}
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{$taskUrl}" class="btn">{$buttonText}</a>
            </div>
        </div>
        <div class="footer">
            <p>{$footerAuto}</p>
            <p>{$footerSettings}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get contact info HTML if available.
     */
    protected function getContactInfo(string $contactName): string
    {
        if (empty($contactName)) {
            return '';
        }

        $contactLabel = __('crm.reminders.contact');

        return '<p>👤 <strong>' . $contactLabel . ':</strong> ' . e($contactName) . '</p>';
    }
}
