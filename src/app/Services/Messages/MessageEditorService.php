<?php

namespace App\Services\Messages;

use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\AutomationRule;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\MessageTrackedLink;
use App\Models\Subscriber;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;

/**
 * Message authoring logic shared by the web editor (MessageController) and the
 * public API (Api\V1\MessageController): trigger → automation rule sync,
 * tracked links, A/B config, duplication, activation, test sends and preview
 * rendering. Both controllers call the same code so a message configured over
 * the API behaves exactly like one saved in the editor.
 */
class MessageEditorService
{
    /**
     * Trigger types a message accepts (Message::trigger_type).
     */
    public const TRIGGER_TYPES = [
        'signup',
        'anniversary',
        'birthday',
        'inactivity',
        'page_visit',
        'custom',
        'recent_subscribers',
        'opened_message',
        'not_opened_message',
    ];

    public function __construct(
        protected PlaceholderService $placeholderService,
        protected MailProviderService $providerService,
    ) {
    }

    /**
     * Sync message trigger with AutomationRule.
     * Creates/updates an automation rule when message has a trigger configured,
     * removes it when the trigger was cleared.
     */
    public function syncTrigger(Message $message, array $data): void
    {
        $triggerType = $data['trigger_type'] ?? null;

        if (empty($triggerType)) {
            // Remove automation rule if trigger was removed
            AutomationRule::where('trigger_source', 'message')
                ->where('trigger_source_id', $message->id)
                ->delete();
            return;
        }

        // Map message trigger types to AutomationRule trigger events
        $triggerEventMap = [
            'signup' => 'subscriber_signup',
            'anniversary' => 'subscription_anniversary',
            'inactivity' => 'subscriber_inactive',
            'birthday' => 'subscriber_birthday',
            'page_visit' => 'page_visited',
            'custom' => 'tag_added', // Custom allows any trigger
        ];

        $triggerEvent = $triggerEventMap[$triggerType] ?? $triggerType;

        // Build trigger config
        $triggerConfig = $data['trigger_config'] ?? [];

        // Add list_id from message if not set
        if (empty($triggerConfig['list_id']) && $message->contactLists->isNotEmpty()) {
            $triggerConfig['list_id'] = $message->contactLists->first()->id;
        }

        AutomationRule::updateOrCreate(
            [
                'trigger_source' => 'message',
                'trigger_source_id' => $message->id,
            ],
            [
                'user_id' => $message->user_id,
                'name' => "Auto: {$message->subject}",
                'description' => "Automatyzacja utworzona z wiadomości #{$message->id}",
                'trigger_event' => $triggerEvent,
                'trigger_config' => $triggerConfig,
                'conditions' => [],
                'condition_logic' => 'all',
                'actions' => [
                    [
                        'type' => 'send_email',
                        'config' => ['message_id' => $message->id]
                    ]
                ],
                'is_active' => in_array($message->status, ['sent', 'scheduled']),
            ]
        );
    }

    /**
     * The automation rule created from this message's trigger, if any.
     */
    public function triggerRule(Message $message): ?AutomationRule
    {
        return AutomationRule::where('trigger_source', 'message')
            ->where('trigger_source_id', $message->id)
            ->first();
    }

    /**
     * Sync tracked links configuration for the message.
     * Creates/updates/deletes MessageTrackedLink records based on form data.
     */
    public function syncTrackedLinks(Message $message, array $trackedLinks): void
    {
        $processedHashes = [];

        foreach ($trackedLinks as $linkData) {
            if (empty($linkData['url'])) {
                continue;
            }

            $urlHash = MessageTrackedLink::generateUrlHash($linkData['url']);
            $processedHashes[] = $urlHash;

            $data = [
                'message_id' => $message->id,
                'url' => $linkData['url'],
                'url_hash' => $urlHash,
                'tracking_enabled' => $linkData['tracking_enabled'] ?? true,
                'share_data_enabled' => $linkData['share_data_enabled'] ?? false,
                'shared_fields' => !empty($linkData['shared_fields']) ? $linkData['shared_fields'] : null,
                'subscribe_to_list_ids' => !empty($linkData['subscribe_to_list_ids']) ? $linkData['subscribe_to_list_ids'] : null,
                'unsubscribe_from_list_ids' => !empty($linkData['unsubscribe_from_list_ids']) ? $linkData['unsubscribe_from_list_ids'] : null,
            ];

            // Use updateOrCreate to handle duplicate URLs in the same request
            // (e.g., when pasting content from Word with the same link multiple times)
            MessageTrackedLink::updateOrCreate(
                [
                    'message_id' => $message->id,
                    'url_hash' => $urlHash,
                ],
                $data
            );
        }

        // Delete links that are no longer in the content
        $message->trackedLinks()
            ->whereNotIn('url_hash', $processedHashes)
            ->delete();
    }

    /**
     * Sync A/B test configuration for the message.
     * Creates/updates/deletes AbTest and AbTestVariant records based on form data.
     */
    public function syncAbTest(Message $message, array $config): void
    {
        $enabled = $config['enabled'] ?? false;
        $variants = $config['variants'] ?? [];

        // If A/B testing is disabled, delete any existing test
        if (!$enabled || count($variants) < 2) {
            $existingTest = $message->abTest;
            if ($existingTest) {
                // Only delete if test is still in draft status
                if ($existingTest->status === AbTest::STATUS_DRAFT) {
                    $existingTest->variants()->delete();
                    $existingTest->delete();
                }
            }
            return;
        }

        // Get or create the A/B test
        $test = $message->abTest;
        if (!$test) {
            $test = AbTest::create([
                'message_id' => $message->id,
                'user_id' => $message->user_id,
                'name' => $message->subject . ' - A/B Test',
                'status' => AbTest::STATUS_DRAFT,
                'test_type' => $config['test_type'] ?? 'subject',
                'winning_metric' => $config['winning_metric'] ?? 'open_rate',
                'sample_percentage' => $config['sample_percentage'] ?? 20,
                'test_duration_hours' => $config['test_duration_hours'] ?? 4,
                'auto_select_winner' => $config['auto_select_winner'] ?? true,
                'confidence_threshold' => $config['confidence_threshold'] ?? 95,
            ]);
        } else {
            // Update existing test (only if draft)
            if ($test->status === AbTest::STATUS_DRAFT) {
                $test->update([
                    'test_type' => $config['test_type'] ?? 'subject',
                    'winning_metric' => $config['winning_metric'] ?? 'open_rate',
                    'sample_percentage' => $config['sample_percentage'] ?? 20,
                    'test_duration_hours' => $config['test_duration_hours'] ?? 4,
                    'auto_select_winner' => $config['auto_select_winner'] ?? true,
                    'confidence_threshold' => $config['confidence_threshold'] ?? 95,
                ]);
            }
        }

        // Sync variants (only if test is draft)
        if ($test->status === AbTest::STATUS_DRAFT) {
            $existingVariants = $test->variants()->get()->keyBy('variant_letter');
            $processedLetters = [];

            foreach ($variants as $variantData) {
                $letter = $variantData['variant_letter'];
                $processedLetters[] = $letter;

                $existingVariant = $existingVariants->get($letter);
                $data = [
                    'ab_test_id' => $test->id,
                    'variant_letter' => $letter,
                    'subject' => $variantData['subject'] ?? null,
                    'preheader' => $variantData['preheader'] ?? null,
                    'is_control' => $variantData['is_control'] ?? false,
                ];

                // For control variant, use message's subject/preheader
                if ($data['is_control']) {
                    $data['subject'] = $message->subject;
                    $data['preheader'] = $message->preheader;
                }

                if ($existingVariant) {
                    $existingVariant->update($data);
                } else {
                    AbTestVariant::create($data);
                }
            }

            // Delete variants that were removed
            $test->variants()
                ->whereNotIn('variant_letter', $processedLetters)
                ->delete();
        }
    }

    /**
     * Format existing A/B test as ab_test_config (the shape syncAbTest accepts).
     */
    public function formatAbTestConfig(Message $message): array
    {
        $message->load(['abTest.variants']);

        if (!$message->abTest) {
            return [
                'enabled' => false,
                'test_type' => 'subject',
                'winning_metric' => 'open_rate',
                'sample_percentage' => 20,
                'test_duration_hours' => 4,
                'auto_select_winner' => true,
                'confidence_threshold' => 95,
                'variants' => [],
            ];
        }

        $test = $message->abTest;

        return [
            'enabled' => true,
            'test_type' => $test->test_type ?? 'subject',
            'winning_metric' => $test->winning_metric ?? 'open_rate',
            'sample_percentage' => $test->sample_percentage ?? 20,
            'test_duration_hours' => $test->test_duration_hours ?? 4,
            'auto_select_winner' => $test->auto_select_winner ?? true,
            'confidence_threshold' => $test->confidence_threshold ?? 95,
            'variants' => $test->variants->map(fn($v) => [
                'variant_letter' => $v->variant_letter,
                'subject' => $v->subject,
                'preheader' => $v->preheader,
                'is_control' => $v->is_control,
            ])->values()->toArray(),
        ];
    }

    /**
     * Copy a message as a fresh draft: lists, exclusions, field filters,
     * tracked links and translations come along; sending state does not.
     */
    public function duplicate(Message $message, ?string $subject = null): Message
    {
        // Create a copy of the message
        $newMessage = $message->replicate();
        $newMessage->subject = $subject ?? ('[KOPIA] ' . $message->subject);
        $newMessage->status = 'draft';
        $newMessage->send_at = null;
        $newMessage->scheduled_at = null; // Reset - new message needs fresh scheduling
        $newMessage->sent_count = 0; // Critical: reset sent counter so queue can be populated
        $newMessage->planned_recipients_count = null; // Reset - will be calculated when activated
        $newMessage->recipients_calculated_at = null; // Reset - needs fresh calculation
        $newMessage->recipients_snapshot = false; // A copy has no queue entries - it targets its lists
        $newMessage->created_at = now();
        $newMessage->updated_at = now();
        $newMessage->save();

        // Copy contact list associations
        $newMessage->contactLists()->sync($message->contactLists->pluck('id'));

        // Copy excluded list associations
        $newMessage->excludedLists()->sync($message->excludedLists->pluck('id'));

        // Copy custom-field audience filters (both sides)
        foreach ($message->fieldFilters as $filter) {
            $newMessage->fieldFilters()->create([
                'custom_field_id' => $filter->custom_field_id,
                'mode' => $filter->mode,
                'operator' => $filter->operator,
                'values' => $filter->values,
                'sort_order' => $filter->sort_order,
            ]);
        }

        // Copy tracked links configuration (preserve all settings)
        foreach ($message->trackedLinks as $trackedLink) {
            MessageTrackedLink::create([
                'message_id' => $newMessage->id,
                'url' => $trackedLink->url,
                'url_hash' => $trackedLink->url_hash,
                'tracking_enabled' => $trackedLink->tracking_enabled,
                'share_data_enabled' => $trackedLink->share_data_enabled,
                'shared_fields' => $trackedLink->shared_fields,
                'subscribe_to_list_ids' => $trackedLink->subscribe_to_list_ids,
                'unsubscribe_from_list_ids' => $trackedLink->unsubscribe_from_list_ids,
            ]);
        }

        // Copy message translations (multi-language)
        foreach ($message->translations as $translation) {
            $newMessage->translations()->create([
                'language' => $translation->language,
                'subject' => $translation->subject,
                'preheader' => $translation->preheader,
                'content' => $translation->content,
            ]);
        }

        return $newMessage;
    }

    /**
     * Set the active flag of a queue (autoresponder) message.
     *
     * The send pipeline (signup listener + cron) only looks at messages with
     * status `scheduled`. Flipping is_active alone left a draft queue message
     * labelled "Active" in the UI while nothing was ever scheduled or sent for
     * it, so activation promotes the draft as well.
     */
    public function setActive(Message $message, bool $active): Message
    {
        $message->is_active = $active;

        if ($message->is_active && $message->status === 'draft') {
            $message->status = 'scheduled';
            $message->scheduled_at = $message->scheduled_at ?? now();
        }

        $message->save();

        return $message;
    }

    /**
     * Planned recipient figures for one message, as shown in the message list.
     *
     * @return array{id: int, recipients_count: int, skipped_count: int, queue_stats: array|null}
     */
    public function recipientCounts(Message $message): array
    {
        return [
            'id' => $message->id,
            'recipients_count' => $message->status === 'sent'
                ? ($message->planned_recipients_count ?? $message->sent_count ?? 0)
                : ($message->contactLists->count() > 0 ? $message->getUniqueRecipients()->count() : 0),
            'skipped_count' => $message->type === 'autoresponder'
                ? ($message->getQueueScheduleStats()['missed'] ?? 0)
                : 0,
            'queue_stats' => $message->type === 'autoresponder'
                ? $message->getQueueStats()
                : null,
        ];
    }

    /**
     * Re-plan every failed queue entry. Returns how many were reset (0 = none
     * failed, nothing changed).
     */
    public function resendToFailed(Message $message): int
    {
        $failedCount = $message->queueEntries()
            ->where('status', MessageQueueEntry::STATUS_FAILED)
            ->count();

        if ($failedCount === 0) {
            return 0;
        }

        // Reset message status to scheduled if it was sent
        if ($message->status === 'sent') {
            $message->update([
                'status' => 'scheduled',
                'scheduled_at' => now(),
            ]);
        }

        // Reset all failed entries to planned
        $message->queueEntries()
            ->where('status', MessageQueueEntry::STATUS_FAILED)
            ->update([
                'status' => MessageQueueEntry::STATUS_PLANNED,
                'planned_at' => now(),
                'queued_at' => null,
                'sent_at' => null,
                'error_message' => null,
            ]);

        // Update planned recipients count
        $message->update([
            'planned_recipients_count' => $message->queueEntries()
                ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                ->count() + $message->sent_count,
        ]);

        return $failedCount;
    }

    /**
     * Queue a queue message for the subscribers it missed (joined before the
     * day offset existed / was reached), due immediately.
     *
     * @param array<int, array{id: int}> $missedSubscribers from getQueueScheduleStats()
     * @return array{created: int, already_exists: int}
     */
    public function queueMissedRecipients(Message $message, array $missedSubscribers): array
    {
        $created = 0;
        $alreadyExists = 0;

        foreach ($missedSubscribers as $subscriberData) {
            // Check if queue entry already exists
            $existing = $message->queueEntries()
                ->where('subscriber_id', $subscriberData['id'])
                ->first();

            if ($existing) {
                // If skipped or failed, reset to planned
                if (in_array($existing->status, [MessageQueueEntry::STATUS_SKIPPED, MessageQueueEntry::STATUS_FAILED])) {
                    $existing->update([
                        'status' => MessageQueueEntry::STATUS_PLANNED,
                        'planned_at' => now(),
                        'scheduled_for' => now(),
                        'error_message' => null,
                    ]);
                    $created++;
                } else {
                    $alreadyExists++;
                }
            } else {
                // Create new entry, due immediately
                try {
                    $message->queueEntries()->create([
                        'subscriber_id' => $subscriberData['id'],
                        'status' => MessageQueueEntry::STATUS_PLANNED,
                        'planned_at' => now(),
                        'scheduled_for' => now(),
                    ]);
                    $created++;
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // Backfilled concurrently by CRON or the signup listener (issue #30)
                    $alreadyExists++;
                }
            }
        }

        // Ensure message is scheduled for processing
        if ($message->status !== 'scheduled') {
            $message->update([
                'status' => 'scheduled',
                'scheduled_at' => now(),
            ]);
        }

        return ['created' => $created, 'already_exists' => $alreadyExists];
    }

    /**
     * Subscriber whose data personalises a test send.
     * Priority: 1) subscriber by test email, 2) subscriber_id, 3) first from the lists.
     */
    public function findTestSubscriber(int $userId, ?string $email, ?int $subscriberId, array $listIds = []): ?Subscriber
    {
        $subscriber = null;

        // First, try to find subscriber by the test email address
        if (!empty($email)) {
            $subscriber = Subscriber::where('user_id', $userId)
                ->where('email', $email)
                ->first();
        }

        // Fallback: use subscriber_id from request
        if (!$subscriber && !empty($subscriberId)) {
            $subscriber = Subscriber::where('user_id', $userId)->find($subscriberId);
        }

        // Fallback: use first subscriber from selected lists
        if (!$subscriber && !empty($listIds)) {
            $subscriber = Subscriber::where('user_id', $userId)
                ->whereHas('contactLists', function ($q) use ($listIds) {
                    $q->whereIn('contact_lists.id', $listIds);
                })
                ->first();
        }

        return $subscriber;
    }

    /**
     * Placeholder values used when no real subscriber is available.
     */
    public function sampleData(string $email): array
    {
        return [
            'email' => $email,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'phone' => '+48 123 456 789',
            'device' => 'Desktop',
            'ip_address' => '127.0.0.1',
            'subscribed_at' => now()->format('Y-m-d H:i:s'),
            'confirmed_at' => now()->format('Y-m-d H:i:s'),
            'source' => 'test',
            'unsubscribe_link' => '#',
            'unsubscribe_url' => '#',
        ];
    }

    /**
     * Render subject + HTML the way a recipient sees them.
     *
     * With a subscriber, all placeholders are resolved through PlaceholderService.
     * Without one, `$sampleData` (when given) fills the [[field]] placeholders;
     * pass null to leave them untouched. The preheader is injected as the hidden
     * preview div after <body>.
     *
     * @return array{subject: string, content: string}
     */
    public function render(string $content, string $subject, ?string $preheader, ?Subscriber $subscriber, ?array $sampleData = null): array
    {
        if ($subscriber) {
            $processed = $this->placeholderService->processEmailContent($content, $subject, $subscriber);
            $content = $processed['content'];
            $subject = $processed['subject'];
        } elseif ($sampleData !== null) {
            $replace = fn (string $text) => preg_replace_callback(
                '/\[\[([a-zA-Z_][a-zA-Z0-9_]*)\]\]/',
                function ($matches) use ($sampleData) {
                    $key = $matches[1];
                    return $sampleData[$key] ?? $matches[0];
                },
                $text
            );

            $content = $replace($content);
            $subject = $replace($subject);
        }

        // Inject preheader if provided
        if (!empty($preheader)) {
            // Process placeholders in preheader if subscriber is available
            if ($subscriber) {
                $preheader = $this->placeholderService->replacePlaceholders($preheader, $subscriber);
            }
            $content = $this->injectPreheader($content, $preheader);
        }

        return ['subject' => $subject, 'content' => $content];
    }

    /**
     * Send an already rendered test email through the mailbox's provider.
     * Throws when the provider fails.
     */
    public function deliverTest(Mailbox $mailbox, string $email, string $subject, string $content): void
    {
        $provider = $this->providerService->getProvider($mailbox);

        $provider->send(
            to: $email,
            toName: $email, // Use email as name for test
            subject: '[TEST] ' . $subject,
            htmlContent: $content
        );
    }

    /**
     * Inject preheader into HTML content
     */
    public function injectPreheader(string $content, string $preheader): string
    {
        // Remove existing preheader div from HTML content (if present)
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

        return $content;
    }
}
