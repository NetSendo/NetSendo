<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message,
        public Subscriber $subscriber,
        public ?Mailbox $mailbox = null,
        public ?int $queueEntryId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MailProviderService $providerService, PlaceholderService $placeholderService): void
    {
        try {
            // Get the mailbox to use (explicit mailbox, message's mailbox, or user's default)
            $mailbox = $this->resolveMailbox($providerService);

            // Validate mailbox can send this message type
            if ($mailbox && !$providerService->validateMailboxForType($mailbox, $this->message->type ?? 'broadcast')) {
                Log::warning("Mailbox {$mailbox->id} cannot send message type: {$this->message->type}");
                // Fall back to default Laravel mailer
                $mailbox = null;
            }

            $content = $this->message->content;
            $subject = $this->message->subject;

            // Generate HMAC Hash for security
            $hash = hash_hmac('sha256', "{$this->message->id}.{$this->subscriber->id}", config('app.key'));

            // 1. Variable Replacement using PlaceholderService (supports custom fields)
            $processed = $placeholderService->processEmailContent($content, $subject, $this->subscriber);
            $content = $processed['content'];
            $subject = $processed['subject'];

            // 2. Preheader Processing - use preheader from Message field, not from HTML content
            $preheader = $this->message->preheader;
            if (!empty($preheader)) {
                // Remove existing preheader div from HTML content (if present)
                // Match pattern: <!-- Preheader text --> followed by hidden div
                $content = preg_replace(
                    '/<!--\s*Preheader\s+text\s*-->\s*<div\s+style\s*=\s*["\'][^"\']*display\s*:\s*none[^"\']*["\'][^>]*>.*?<\/div>/is',
                    '',
                    $content
                );

                // Also remove any hidden preheader divs without comment
                $content = preg_replace(
                    '/<div\s+style\s*=\s*["\'][^"\']*display\s*:\s*none;\s*max-height:\s*0[^"\']*["\'][^>]*>.*?<\/div>/is',
                    '',
                    $content
                );

                // Create new preheader HTML
                $preheaderHtml = '<!-- Preheader text -->' . "\n" .
                    '<div style="display: none; max-height: 0; overflow: hidden;">' . "\n" .
                    '    ' . htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8') . "\n" .
                    '</div>' . "\n";

                // Insert preheader after <body> tag
                if (preg_match('/<body[^>]*>/i', $content, $matches)) {
                    $content = preg_replace(
                        '/(<body[^>]*>)/i',
                        '$1' . "\n" . $preheaderHtml,
                        $content,
                        1
                    );
                } else {
                    // If no body tag, prepend to content
                    $content = $preheaderHtml . $content;
                }
            }

            // 3. Link Tracking Replacement
            $content = preg_replace_callback('/href=["\']([^"\']+)["\']/', function ($matches) use ($hash) {
                $url = $matches[1];

                // Skip special links
                if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, '#') || str_contains($url, 'unsubscribe')) {
                    return 'href="' . $url . '"';
                }

                // Generate tracking URL
                $trackingUrl = route('tracking.click', [
                    'message' => $this->message->id,
                    'subscriber' => $this->subscriber->id,
                    'hash' => $hash,
                    'url' => $url
                ]);

                return 'href="' . $trackingUrl . '"';
            }, $content);

            // 4. Open Tracking Pixel
            $pixelUrl = route('tracking.open', [
                'message' => $this->message->id,
                'subscriber' => $this->subscriber->id,
                'hash' => $hash,
            ]);

            $pixelHtml = '<img src="' . $pixelUrl . '" alt="" width="1" height="1" border="0" style="height:1px !important;width:1px !important;border-width:0 !important;margin-top:0 !important;margin-bottom:0 !important;margin-right:0 !important;margin-left:0 !important;padding-top:0 !important;padding-bottom:0 !important;padding-right:0 !important;padding-left:0 !important;"/>';

            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', $pixelHtml . '</body>', $content);
            } else {
                $content .= $pixelHtml;
            }

            // 5. Send Email using Mailbox Provider or Default Laravel Mailer
            $recipientName = trim(($this->subscriber->first_name ?? '') . ' ' . ($this->subscriber->last_name ?? ''));

            // Prepare attachments array
            $attachments = $this->message->attachments->map(fn($a) => [
                'path' => $a->getFullPath(),
                'name' => $a->original_name,
                'mime_type' => $a->mime_type,
            ])->filter(fn($a) => file_exists($a['path']))->values()->toArray();

            if ($mailbox) {
                // Use custom mailbox provider
                $provider = $providerService->getProvider($mailbox);
                // Resolve custom headers
                $headers = $this->resolveHeaders($placeholderService);

                $provider->send(
                    $this->subscriber->email,
                    $recipientName ?: $this->subscriber->email,
                    $subject,
                    $content,
                    $headers,
                    $attachments
                );

                // Track sent count for rate limiting
                $mailbox->incrementSentCount();

                Log::info("Email sent via {$mailbox->provider} ({$mailbox->name}) to {$this->subscriber->email}");
            } else {
                // Fall back to default Laravel mailer
                Mail::html($content, function ($mail) use ($subject, $attachments) {
                    $mail->to($this->subscriber->email, ($this->subscriber->first_name . ' ' . $this->subscriber->last_name));
                    $mail->subject($subject);
                    $mail->from(config('mail.from.address'), config('mail.from.name'));

                    // Add attachments
                    foreach ($attachments as $attachment) {
                        $mail->attach($attachment['path'], [
                            'as' => $attachment['name'],
                            'mime' => $attachment['mime_type'],
                        ]);
                    }
                });

                Log::info("Email sent via default mailer to {$this->subscriber->email}");
            }

            // 6. Update queue entry status on successful delivery
            $this->markQueueEntryAsSent();

        } catch (\Exception $e) {
            Log::error("Failed to send email to {$this->subscriber->email}: " . $e->getMessage());

            // Mark queue entry as failed
            $this->markQueueEntryAsFailed($e->getMessage());

            $this->fail($e);
        }
    }

    /**
     * Mark the queue entry as sent and update message statistics
     */
    private function markQueueEntryAsSent(): void
    {
        if (!$this->queueEntryId) {
            return;
        }

        $entry = MessageQueueEntry::find($this->queueEntryId);
        if (!$entry) {
            return;
        }

        $entry->markAsSent();

        // Increment sent_count on the message
        $this->message->increment('sent_count');

        // For broadcast messages: check if all entries are processed
        if ($this->message->type === 'broadcast') {
            $pendingCount = $this->message->queueEntries()
                ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                ->count();

            if ($pendingCount === 0) {
                $this->message->update(['status' => 'sent']);
                Log::info("Broadcast message {$this->message->id} marked as sent - all entries processed");
            }
        }
    }

    /**
     * Mark the queue entry as failed
     */
    private function markQueueEntryAsFailed(string $errorMessage): void
    {
        if (!$this->queueEntryId) {
            return;
        }

        $entry = MessageQueueEntry::find($this->queueEntryId);
        if ($entry) {
            $entry->markAsFailed($errorMessage);
        }
    }

    /**
     * Resolve custom headers (Global < List)
     */
    private function resolveHeaders(PlaceholderService $placeholderService): array
    {
        $rawHeaders = [];

        // 1. Global Defaults
        // Access settings.sending.headers
        $userSettings = $this->message->user->settings ?? [];
        if (isset($userSettings['sending']['headers']) && is_array($userSettings['sending']['headers'])) {
             // Filter empty strings
             $globalHeaders = array_filter($userSettings['sending']['headers'], fn($v) => !empty($v));
             $rawHeaders = array_merge($rawHeaders, $globalHeaders);
        }

        // 2. List Settings (Overrides)
        $list = $this->subscriber->contactList;
        if ($list && isset($list->settings['sending']['headers']) && is_array($list->settings['sending']['headers'])) {
             // Filter empty strings
             $listHeaders = array_filter($list->settings['sending']['headers'], fn($v) => !empty($v));
             $rawHeaders = array_merge($rawHeaders, $listHeaders);
        }

        if (empty($rawHeaders)) {
            return [];
        }

        // Generate placeholders
        $additionalData = [
            'unsubscribe_link' => $placeholderService->generateUnsubscribeLink($this->subscriber),
            'unsubscribe_url' => $placeholderService->generateUnsubscribeLink($this->subscriber),
        ];

        // Process values
        $finalHeaders = [];

        // List-Unsubscribe
        if (!empty($rawHeaders['list_unsubscribe'])) {
            $value = $placeholderService->replacePlaceholders($rawHeaders['list_unsubscribe'], $this->subscriber, $additionalData);
            if (!empty($value)) {
                $finalHeaders['List-Unsubscribe'] = $value;
            }
        }

        // List-Unsubscribe-Post
        if (!empty($rawHeaders['list_unsubscribe_post'])) {
            $value = $placeholderService->replacePlaceholders($rawHeaders['list_unsubscribe_post'], $this->subscriber, $additionalData);
            if (!empty($value)) {
                 $finalHeaders['List-Unsubscribe-Post'] = $value;
            }
        }

        return $finalHeaders;
    }

    /**
     * Resolve which mailbox to use for this email
     */
    private function resolveMailbox(MailProviderService $providerService): ?Mailbox
    {
        // Priority 1: Explicitly passed mailbox
        if ($this->mailbox && $this->mailbox->is_active) {
            return $this->mailbox;
        }

        // Priority 2: Message's assigned mailbox
        if ($this->message->mailbox_id && $this->message->mailbox?->is_active) {
            return $this->message->mailbox;
        }

        // Priority 3: User's best available mailbox for this message type
        if ($this->message->user_id) {
            return $providerService->getBestMailbox(
                $this->message->user_id,
                $this->message->type ?? 'broadcast'
            );
        }

        return null;
    }
}
