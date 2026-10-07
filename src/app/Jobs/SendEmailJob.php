<?php

namespace App\Jobs;

use App\Events\EmailBounced;
use App\Helpers\EmailPlainText;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\MessageTrackedLink;
use App\Models\Subscriber;
use App\Services\Mail\BounceProcessingService;
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

            // Validate mailbox can send this message type. A mailbox whose
            // "allowed types" exclude this type (e.g. broadcast-only mailbox vs
            // an autoresponder message) must not be used — but rather than
            // giving up, fall back to any active mailbox of this user that IS
            // allowed to send the type. Without that fallback the send failed
            // with a misleading "no mailbox configured" error, which silently
            // blocked entire autoresponder queues while broadcasts kept working.
            $messageType = $this->message->type ?? 'broadcast';
            if ($mailbox && !$providerService->validateMailboxForType($mailbox, $messageType)) {
                Log::warning("Mailbox {$mailbox->id} cannot send message type: {$messageType}", [
                    'mailbox_allowed_types' => $mailbox->allowed_types,
                    'message_id' => $this->message->id,
                ]);

                $fallback = $providerService->getBestMailbox($this->message->user_id, $messageType);

                if ($fallback) {
                    Log::info("Falling back to mailbox allowed for type {$messageType}", [
                        'from_mailbox_id' => $mailbox->id,
                        'to_mailbox_id' => $fallback->id,
                    ]);
                }

                $mailbox = $fallback;
            }

            $content = $this->message->content;
            $subject = $this->message->subject;
            // The message's own text part goes with its own HTML only; content
            // replaced by a translation gets text generated from that HTML.
            $plainText = $this->message->plain_text;

            // Language-specific content: check subscriber's language preference
            $subscriberLanguage = $this->subscriber->language;
            if ($subscriberLanguage) {
                $translation = $this->message->getTranslationForLanguage($subscriberLanguage);
                if ($translation) {
                    $subject = $translation->subject;
                    if ($translation->content) {
                        $content = $translation->content;
                        $plainText = null;
                    }
                    if ($translation->preheader) {
                        $this->message->preheader = $translation->preheader;
                    }
                    Log::debug('Language translation applied', [
                        'subscriber_id' => $this->subscriber->id,
                        'language' => $subscriberLanguage,
                        'translation_id' => $translation->id,
                    ]);
                }
            }

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

            if ($plainText !== null && trim($plainText) !== '') {
                $plainText = $placeholderService->processEmailContent($plainText, '', $this->subscriber, $unsubscribeList)['content'];
            }

            // Click and open tracking: the message's switch, else its mailbox's
            $trackingEnabled = $this->message->tracking_enabled ?? $mailbox?->tracking_enabled ?? true;

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

            // text/plain alternative, generated from the HTML before the links
            // are rewritten for tracking, so it carries the real link targets
            $plainText = EmailPlainText::forEmail($plainText, $content);

            // 3. Link Tracking Replacement
            // Load tracked links configuration for this message
            $trackedLinksConfig = $this->message->trackedLinks()->get()->keyBy(function ($link) {
                return $link->url_hash;
            });

            $content = !$trackingEnabled ? $content : preg_replace_callback('/href=["\']([^"\']+)["\']/', function ($matches) use ($hash, $trackedLinksConfig) {
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
            if ($trackingEnabled) {
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

            // Mailbox is required - throw exception if not available.
            // Name the actual cause: "no mailbox at all" and "no mailbox that is
            // allowed to send this type" are different configuration problems.
            if (!$mailbox) {
                $hasAnyMailbox = Mailbox::forUser($this->message->user_id)->active()->exists();

                throw new \RuntimeException(
                    $hasAnyMailbox
                        ? "Żadna aktywna skrzynka pocztowa nie ma włączonego typu wysyłki \"{$messageType}\". Włącz ten typ w ustawieniach skrzynki (Ustawienia > Skrzynki pocztowe > Dozwolone typy)."
                        : "Brak skonfigurowanej skrzynki pocztowej. Skonfiguruj skrzynkę w ustawieniach przed wysyłką wiadomości."
                );
            }

            // Use custom mailbox provider
            $provider = $providerService->getProvider($mailbox);
            // Resolve custom headers — for the mailbox that actually sends
            $headers = $this->resolveHeaders($placeholderService, $mailbox);

            $provider->send(
                $this->subscriber->email,
                $recipientName ?: $this->subscriber->email,
                $subject,
                $content,
                $headers,
                $attachments,
                $plainText,
                $trackingEnabled
            );

            // Track sent count for rate limiting
            $mailbox->incrementSentCount();

            Log::info("Email sent via {$mailbox->provider} ({$mailbox->name}) to {$this->subscriber->email}");

            // 7. Update queue entry status on successful delivery
            $this->markQueueEntryAsSent();

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $bounceService = app(BounceProcessingService::class);

            // Intelligent retry for transient SMTP failures (issue #21).
            // Throttling / connection-limit / greylisting responses (e.g. the
            // "421 Too many connections" a rate-limited provider returns when the
            // worker opens too many sessions at once) are transient: release the
            // job back to the queue with a backoff delay so it is retried instead
            // of being marked as a permanent failure. Permanent 5xx errors fall
            // through to the bounce/fail path below.
            if (config('netsendo.email.transient_retry', true)
                && $this->attempts() < $this->tries
                && $bounceService->isTransientError($errorMsg)
            ) {
                $base = (int) config('netsendo.email.transient_backoff_base', 15);
                $jitter = (int) config('netsendo.email.transient_backoff_jitter', 15);
                $delay = ($base * $this->attempts()) + ($jitter > 0 ? random_int(0, $jitter) : 0);

                Log::warning("Transient SMTP error for {$this->subscriber->email} — releasing back to queue", [
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'retry_in_seconds' => $delay,
                    'error' => $errorMsg,
                ]);

                // Leave the queue entry as 'queued' (do NOT mark failed) so it is
                // picked up again after the backoff delay.
                $this->release($delay);

                return;
            }

            Log::error("Failed to send email to {$this->subscriber->email}: " . $errorMsg);

            // Detect inline SMTP bounce from error codes (5xx = hard bounce)
            try {
                if ($bounceService->isHardBounceError($errorMsg)) {
                    Log::info("Inline hard bounce detected for {$this->subscriber->email}", [
                        'error' => $errorMsg,
                    ]);
                    $bounceService->processBounce(
                        email: $this->subscriber->email,
                        bounceType: EmailBounced::TYPE_HARD,
                        bounceReason: mb_substr($errorMsg, 0, 255),
                        messageId: (string) $this->message->id,
                        provider: 'smtp_inline'
                    );
                } elseif ($bounceService->isSoftBounceError($errorMsg)) {
                    Log::info("Inline soft bounce detected for {$this->subscriber->email}", [
                        'error' => $errorMsg,
                    ]);
                    $bounceService->processBounce(
                        email: $this->subscriber->email,
                        bounceType: EmailBounced::TYPE_SOFT,
                        bounceReason: mb_substr($errorMsg, 0, 255),
                        messageId: (string) $this->message->id,
                        provider: 'smtp_inline'
                    );
                }
            } catch (\Exception $bounceEx) {
                Log::warning("Failed to process inline bounce: " . $bounceEx->getMessage());
            }

            // Mark queue entry as failed
            $this->markQueueEntryAsFailed($errorMsg);

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
     * Handle a permanent job failure (retries exhausted, worker killed the job).
     *
     * Without this the entry would stay `queued` forever: only this job can
     * move it out of that state, and the cron loop only picks up `planned`
     * entries — so the recipient would silently never be sent to and never
     * show up as failed in the stats.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->markQueueEntryAsFailed(
            'Job failed permanently: ' . ($exception?->getMessage() ?? 'unknown error')
        );

        Log::error('SendEmailJob failed permanently', [
            'message_id' => $this->message->id ?? null,
            'subscriber_id' => $this->subscriber->id ?? null,
            'entry_id' => $this->queueEntryId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Resolve custom headers (Return-Path < Global < List < Mailbox Custom < API Custom),
     * plus the RFC 8058 one-click List-Unsubscribe pair for list mail.
     *
     * $mailbox is the mailbox that sends — resolved in handle() from the job,
     * the message, the list default or the account default. Forbidden headers
     * (From, To, Subject, etc.) are silently filtered out to prevent spoofing
     * or breaking MIME structure. Names are matched case-insensitively, and
     * `list_unsubscribe` / `list_unsubscribe_post` (the settings keys) are
     * List-Unsubscribe / List-Unsubscribe-Post, so a later source replaces an
     * earlier one instead of sending the header twice.
     */
    private function resolveHeaders(PlaceholderService $placeholderService, ?Mailbox $mailbox = null): array
    {
        $mailbox ??= $this->mailbox ?? $this->message->mailbox;

        // Headers that must not be overridden by users
        $forbiddenHeaders = [
            'from', 'to', 'cc', 'bcc', 'subject', 'date',
            'mime-version', 'content-type', 'content-transfer-encoding',
            'message-id', 'return-path',
        ];

        // lower-case name => [name, value]
        $rawHeaders = [];
        $put = function ($key, $value) use (&$rawHeaders, $forbiddenHeaders) {
            if (!is_string($key) || $value === null || $value === '' || !is_scalar($value)) {
                return;
            }
            $name = self::canonicalHeaderName($key);
            if ($name === '' || in_array(strtolower($name), $forbiddenHeaders, true)) {
                return;
            }
            $rawHeaders[strtolower($name)] = [$name, (string) $value];
        };

        // List context: the list this subscriber gets the message from
        $list = $this->resolveListContext();

        // 1. Global Defaults (settings.sending.headers)
        $userSettings = $this->message->user->settings ?? [];
        if (isset($userSettings['sending']['headers']) && is_array($userSettings['sending']['headers'])) {
            foreach ($userSettings['sending']['headers'] as $key => $value) {
                $put($key, $value);
            }
        }

        // 2. List Settings (Overrides) — the list in context, else the message's first list
        $settingsList = $list ?? $this->message->contactLists->sortBy('id')->first();
        if ($settingsList && isset($settingsList->settings['sending']['headers']) && is_array($settingsList->settings['sending']['headers'])) {
            foreach ($settingsList->settings['sending']['headers'] as $key => $value) {
                $put($key, $value);
            }
        }

        // 3. Mailbox-level custom headers (overrides global/list)
        if ($mailbox && !empty($mailbox->custom_headers) && is_array($mailbox->custom_headers)) {
            foreach ($mailbox->custom_headers as $header) {
                if (!empty($header['key']) && isset($header['value'])) {
                    $put(trim($header['key']), $header['value']);
                }
            }
        }

        // 4. API per-message custom headers (highest custom priority, overrides mailbox)
        if (!empty($this->message->custom_headers) && is_array($this->message->custom_headers)) {
            foreach ($this->message->custom_headers as $key => $value) {
                $put(is_string($key) ? trim($key) : $key, $value);
            }
        }

        // A one-click URL: list-scoped when the list is known, else the
        // account-wide opt-out. Both answer GET with a page and POST
        // List-Unsubscribe=One-Click by unsubscribing (UnsubscribeController).
        $oneClickUrl = $list
            ? $placeholderService->generateUnsubscribeLink($this->subscriber, $list)
            : $placeholderService->generateGlobalUnsubscribeLink($this->subscriber);

        $additionalData = [
            'unsubscribe_link' => $oneClickUrl,
            'unsubscribe_url' => $oneClickUrl,
            'unsubscribe' => $oneClickUrl,
            'unsubscribe_global' => $placeholderService->generateGlobalUnsubscribeLink($this->subscriber),
            'manage' => $placeholderService->generateManageLink($this->subscriber),
        ];

        $finalHeaders = [];
        foreach ($rawHeaders as [$name, $value]) {
            // Process placeholder replacement in header values too
            $processedValue = $placeholderService->replacePlaceholders($value, $this->subscriber, $additionalData);
            if ($processedValue !== '') {
                $finalHeaders[$name] = $processedValue;
            }
        }

        // RFC 8058: list mail (broadcasts and autoresponders) can be left in one click
        if ($this->wantsListUnsubscribe()) {
            if (!isset($finalHeaders['List-Unsubscribe'])) {
                $finalHeaders['List-Unsubscribe'] = '<' . $oneClickUrl . '>';
                $finalHeaders['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
            } elseif (!isset($finalHeaders['List-Unsubscribe-Post'])
                && str_contains($finalHeaders['List-Unsubscribe'], $oneClickUrl)) {
                // A configured header carrying our URL: that URL takes the POST
                $finalHeaders['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
            }
        }

        // Return-Path for bounce mailbox handling (system-level, not user-overridable):
        // bounces go to the monitored IMAP mailbox of the mailbox that sends
        if ($mailbox && $mailbox->bounce_enabled) {
            $bounceEmail = $mailbox->getBounceEmail();
            if ($bounceEmail) {
                $finalHeaders['Return-Path'] = $bounceEmail;
            }
        }

        return $finalHeaders;
    }

    /**
     * The list this subscriber receives the message from: the message's only
     * list, or — for a message to several lists — the one of them the
     * subscriber is an active member of. Null when that is not one list.
     */
    private function resolveListContext(): ?ContactList
    {
        $lists = $this->message->contactLists;

        if (!$lists || $lists->isEmpty()) {
            return null;
        }

        if ($lists->count() === 1) {
            return $lists->first();
        }

        $memberOf = $this->subscriber->contactLists()
            ->whereIn('contact_lists.id', $lists->pluck('id'))
            ->wherePivot('status', 'active')
            ->pluck('contact_lists.id');

        return $memberOf->count() === 1 ? $lists->firstWhere('id', $memberOf->first()) : null;
    }

    /**
     * One-click unsubscribe headers are added to list mail: broadcasts and
     * autoresponders with at least one list. A single API send without a
     * list (POST /api/v1/email/send) may be transactional and gets them only
     * when it passes its own List-Unsubscribe.
     */
    private function wantsListUnsubscribe(): bool
    {
        return config('netsendo.email.list_unsubscribe', true)
            && in_array($this->message->type ?? 'broadcast', ['broadcast', 'autoresponder'], true)
            && $this->message->contactLists->isNotEmpty();
    }

    /**
     * Header name as sent: the List-Unsubscribe pair spelled the RFC way
     * whatever the source wrote (list_unsubscribe, list-unsubscribe, ...).
     */
    private static function canonicalHeaderName(string $key): string
    {
        $key = trim($key);

        return match (str_replace('_', '-', strtolower($key))) {
            'list-unsubscribe' => 'List-Unsubscribe',
            'list-unsubscribe-post' => 'List-Unsubscribe-Post',
            default => $key,
        };
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
