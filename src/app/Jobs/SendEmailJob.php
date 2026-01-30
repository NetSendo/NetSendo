<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\MessageTrackedLink;
use App\Models\Subscriber;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;
use App\Services\EmailImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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

            // A/B Test: Apply variant content if variant is assigned to this queue entry
            if ($this->queueEntryId) {
                $queueEntry = MessageQueueEntry::find($this->queueEntryId);
                if ($queueEntry && $queueEntry->ab_test_variant_id) {
                    $variant = \App\Models\AbTestVariant::find($queueEntry->ab_test_variant_id);
                    if ($variant) {
                        if ($variant->subject) {
                            $subject = $variant->subject;
                            Log::debug('A/B Test: Using variant subject', [
                                'entry_id' => $this->queueEntryId,
                                'variant_letter' => $variant->variant_letter,
                                'subject' => $subject,
                            ]);
                        }
                        if ($variant->preheader) {
                            $this->message->preheader = $variant->preheader;
                        }
                    }
                }
            }

            // Generate HMAC Hash for security
            $hash = hash_hmac('sha256', "{$this->message->id}.{$this->subscriber->id}", config('app.key'));

            // Determine list context for unsubscribe link
            // If message is sent to exactly one list, unsubscribe from that list
            // If sent to multiple lists, show preferences page (list = null)
            $contactLists = $this->message->contactLists;
            $unsubscribeList = ($contactLists && $contactLists->count() === 1)
                ? $contactLists->first()
                : null;

            // 1. Variable Replacement using PlaceholderService (supports custom fields)
            $processed = $placeholderService->processEmailContent($content, $subject, $this->subscriber, $unsubscribeList);
            $content = $processed['content'];
            $subject = $processed['subject'];

            // 2. Preheader Processing - use preheader from Message field, not from HTML content
            $preheader = $this->message->preheader;
            if (!empty($preheader)) {
                // Process placeholders in preheader (including [[!fname]] vocative)
                $preheader = $placeholderService->replacePlaceholders($preheader, $this->subscriber, [
                    'unsubscribe_link' => $placeholderService->generateUnsubscribeLink($this->subscriber, $unsubscribeList),
                    'unsubscribe_url' => $placeholderService->generateUnsubscribeLink($this->subscriber, $unsubscribeList),
                ]);

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
            // Load tracked links configuration for this message
            $trackedLinksConfig = $this->message->trackedLinks()->get()->keyBy(function ($link) {
                return $link->url_hash;
            });

            $content = preg_replace_callback('/href=["\']([^"\']+)["\']/', function ($matches) use ($hash, $trackedLinksConfig) {
                $url = $matches[1];

                // Skip special links
                if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, '#') || str_contains($url, 'unsubscribe')) {
                    return 'href="' . $url . '"';
                }

                // Check if this URL has custom tracking configuration
                $urlHash = MessageTrackedLink::generateUrlHash($url);
                $linkConfig = $trackedLinksConfig->get($urlHash);

                // If tracking is explicitly disabled for this link, skip
                if ($linkConfig && !$linkConfig->tracking_enabled) {
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

            // 5. Convert images marked with class="img_to_b64" to inline base64
            if (config('netsendo.email.convert_inline_images', true)) {
                $imageService = app(EmailImageService::class);
                if ($imageService->hasImagesToProcess($content)) {
                    $content = $imageService->processInlineImages($content);
                    Log::debug("Processed inline images for email to {$this->subscriber->email}");
                }
            }

            // 6. Send Email using Mailbox Provider or Default Laravel Mailer
            $recipientName = trim(($this->subscriber->first_name ?? '') . ' ' . ($this->subscriber->last_name ?? ''));

            // Prepare attachments array
            $attachments = $this->message->attachments->map(fn($a) => [
                'path' => $a->getFullPath(),
                'name' => $a->original_name,
                'mime_type' => $a->mime_type,
            ])->filter(fn($a) => file_exists($a['path']))->values()->toArray();

            // Mailbox is required - throw exception if not available
            if (!$mailbox) {
                throw new \RuntimeException(
                    "Brak skonfigurowanej skrzynki pocztowej. Skonfiguruj skrzynkę w ustawieniach przed wysyłką wiadomości."
                );
            }

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

            // 7. Update queue entry status on successful delivery
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

        // Refresh the message model to get current state from database
        // This is important because the serialized model may be stale
        // (e.g., after resendToFailed changed status back to 'scheduled')
        $this->message->refresh();

        // Increment sent_count on the message
        $this->message->increment('sent_count');

        // For broadcast messages: check if all entries are processed
        if ($this->message->type === 'broadcast') {
            $pendingCount = $this->message->queueEntries()
                ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                ->count();

            if ($pendingCount === 0) {
                // Only update to 'sent' if currently 'scheduled'
                // (avoid overwriting other statuses like 'draft')
                if ($this->message->status === 'scheduled') {
                    $this->message->update(['status' => 'sent']);
                    Log::info("Broadcast message {$this->message->id} marked as sent - all entries processed");
                }
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

        // Determine list context for unsubscribe link
        $contactLists = $this->message->contactLists;
        $unsubscribeList = ($contactLists && $contactLists->count() === 1)
            ? $contactLists->first()
            : null;

        // Generate placeholders
        $unsubscribeLink = $placeholderService->generateUnsubscribeLink($this->subscriber, $unsubscribeList);
        $additionalData = [
            'unsubscribe_link' => $unsubscribeLink,
            'unsubscribe_url' => $unsubscribeLink,
            'unsubscribe' => $unsubscribeLink,
            'manage' => $placeholderService->generateManageLink($this->subscriber),
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
     *
     * Priority hierarchy:
     * 1. Explicitly passed mailbox (e.g., from automation)
     * 2. Message's assigned mailbox_id
     * 3. First contact list's default_mailbox_id
     * 4. User's global default or best available mailbox
     */
    private function resolveMailbox(MailProviderService $providerService): ?Mailbox
    {
        // Priority 1: Explicitly passed mailbox
        if ($this->mailbox && $this->mailbox->is_active) {
            Log::debug("Mailbox resolved: Priority 1 - Explicit mailbox", [
                'mailbox_id' => $this->mailbox->id,
                'mailbox_name' => $this->mailbox->name,
            ]);
            return $this->mailbox;
        }

        // Priority 2: Message's assigned mailbox
        // Load mailbox relation if not loaded to avoid null issues
        if ($this->message->mailbox_id) {
            $messageMailbox = $this->message->mailbox ?? Mailbox::find($this->message->mailbox_id);
            if ($messageMailbox && $messageMailbox->is_active) {
                Log::debug("Mailbox resolved: Priority 2 - Message's mailbox", [
                    'message_id' => $this->message->id,
                    'mailbox_id' => $messageMailbox->id,
                    'mailbox_name' => $messageMailbox->name,
                ]);
                return $messageMailbox;
            }
        }

        // Priority 3: Contact list's default mailbox
        // Use first contact list that has a default_mailbox_id set
        $contactLists = $this->message->contactLists;
        if ($contactLists && $contactLists->isNotEmpty()) {
            foreach ($contactLists as $list) {
                if ($list->default_mailbox_id) {
                    $listMailbox = $list->defaultMailbox ?? Mailbox::find($list->default_mailbox_id);
                    if ($listMailbox && $listMailbox->is_active) {
                        Log::debug("Mailbox resolved: Priority 3 - List's default mailbox", [
                            'message_id' => $this->message->id,
                            'list_id' => $list->id,
                            'list_name' => $list->name,
                            'mailbox_id' => $listMailbox->id,
                            'mailbox_name' => $listMailbox->name,
                        ]);
                        return $listMailbox;
                    }
                }
            }
        }

        // Priority 4: User's best available mailbox for this message type
        if ($this->message->user_id) {
            $bestMailbox = $providerService->getBestMailbox(
                $this->message->user_id,
                $this->message->type ?? 'broadcast'
            );

            if ($bestMailbox) {
                Log::debug("Mailbox resolved: Priority 4 - User's best mailbox", [
                    'message_id' => $this->message->id,
                    'user_id' => $this->message->user_id,
                    'mailbox_id' => $bestMailbox->id,
                    'mailbox_name' => $bestMailbox->name,
                ]);
            } else {
                Log::warning("Mailbox resolved: No mailbox found for message", [
                    'message_id' => $this->message->id,
                    'user_id' => $this->message->user_id,
                ]);
            }

            return $bestMailbox;
        }

        Log::warning("Mailbox resolved: No user_id on message, cannot resolve mailbox", [
            'message_id' => $this->message->id,
        ]);

        return null;
    }
}
