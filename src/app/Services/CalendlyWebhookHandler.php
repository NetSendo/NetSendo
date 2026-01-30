<?php

namespace App\Services;

use App\Models\CalendlyEvent;
use App\Models\CalendlyIntegration;
use App\Models\ContactList;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmTask;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalendlyWebhookHandler
{
    public function __construct(
        private CalendlyService $calendlyService,
        private WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * Handle invitee.created webhook event.
     */
    public function handleInviteeCreated(array $payload, CalendlyIntegration $integration): void
    {
        Log::info('Processing Calendly invitee.created webhook', [
            'integration_id' => $integration->id,
        ]);

        $eventData = $payload['payload'] ?? [];
        $scheduledEvent = $eventData['scheduled_event'] ?? [];
        $invitee = $eventData;

        DB::transaction(function () use ($eventData, $scheduledEvent, $invitee, $integration, $payload) {
            // Create or update the Calendly event record
            $calendlyEvent = $this->createOrUpdateEvent($eventData, $scheduledEvent, $invitee, $integration, $payload);

            // Find or create subscriber
            $subscriber = $this->findOrCreateSubscriber($invitee, $integration);
            $calendlyEvent->update(['subscriber_id' => $subscriber->id]);

            // Add to mailing lists
            if ($integration->getSetting('mailing_lists.enabled', true)) {
                $this->addToMailingLists($subscriber, $calendlyEvent, $integration);
            }

            // Apply tags
            $this->applyTags($subscriber, $calendlyEvent, $integration);

            // CRM integration
            if ($integration->getSetting('crm.enabled', true)) {
                $crmContact = $this->syncWithCrm($subscriber, $calendlyEvent, $integration);
                $calendlyEvent->update(['crm_contact_id' => $crmContact?->id]);

                // Create CRM task for the meeting
                if ($integration->getSetting('crm.create_tasks', true) && $crmContact) {
                    $task = $this->createMeetingTask($calendlyEvent, $crmContact, $integration);
                    $calendlyEvent->update(['crm_task_id' => $task?->id]);
                }
            }

            // Trigger automation rules
            if ($integration->getSetting('automation.trigger_on_booking', true)) {
                $this->dispatchWebhook($integration, 'calendly.booking_created', $calendlyEvent, $subscriber);
            }
        });
    }

    /**
     * Handle invitee.canceled webhook event.
     */
    public function handleInviteeCanceled(array $payload, CalendlyIntegration $integration): void
    {
        Log::info('Processing Calendly invitee.canceled webhook', [
            'integration_id' => $integration->id,
        ]);

        $eventData = $payload['payload'] ?? [];
        $cancellation = $eventData['cancellation'] ?? [];

        $calendlyEvent = CalendlyEvent::where('calendly_invitee_uri', $eventData['uri'] ?? null)->first();

        if (!$calendlyEvent) {
            Log::warning('Calendly event not found for cancellation', [
                'invitee_uri' => $eventData['uri'] ?? null,
            ]);
            return;
        }

        $calendlyEvent->markAsCanceled(
            $cancellation['reason'] ?? null,
            $cancellation['canceled_by'] ?? null
        );

        // Update CRM activity
        if ($calendlyEvent->crm_contact_id) {
            $this->logCrmActivity($calendlyEvent, 'calendly_canceled', 'Meeting canceled');
        }

        // Cancel CRM task if exists
        if ($calendlyEvent->crm_task_id) {
            CrmTask::where('id', $calendlyEvent->crm_task_id)->update([
                'status' => 'canceled',
                'notes' => 'Canceled via Calendly: ' . ($cancellation['reason'] ?? 'No reason provided'),
            ]);
        }

        // Trigger automation
        if ($integration->getSetting('automation.trigger_on_cancellation', true)) {
            $this->dispatchWebhook($integration, 'calendly.booking_canceled', $calendlyEvent, $calendlyEvent->subscriber);
        }
    }

    /**
     * Handle invitee.no_show webhook event.
     */
    public function handleInviteeNoShow(array $payload, CalendlyIntegration $integration): void
    {
        Log::info('Processing Calendly invitee.no_show webhook', [
            'integration_id' => $integration->id,
        ]);

        $eventData = $payload['payload'] ?? [];

        $calendlyEvent = CalendlyEvent::where('calendly_invitee_uri', $eventData['uri'] ?? null)->first();

        if (!$calendlyEvent) {
            Log::warning('Calendly event not found for no-show', [
                'invitee_uri' => $eventData['uri'] ?? null,
            ]);
            return;
        }

        $calendlyEvent->markAsNoShow();

        // Update CRM activity
        if ($calendlyEvent->crm_contact_id) {
            $this->logCrmActivity($calendlyEvent, 'calendly_no_show', 'Invitee marked as no-show');
        }

        // Update CRM task
        if ($calendlyEvent->crm_task_id) {
            CrmTask::where('id', $calendlyEvent->crm_task_id)->update([
                'status' => 'completed',
                'notes' => 'Marked as no-show',
            ]);
        }

        // Trigger automation
        if ($integration->getSetting('automation.trigger_on_no_show', false)) {
            $this->dispatchWebhook($integration, 'calendly.no_show', $calendlyEvent, $calendlyEvent->subscriber);
        }
    }

    /**
     * Create or update the Calendly event record.
     */
    private function createOrUpdateEvent(
        array $eventData,
        array $scheduledEvent,
        array $invitee,
        CalendlyIntegration $integration,
        array $rawPayload
    ): CalendlyEvent {
        $eventUri = $scheduledEvent['uri'] ?? null;
        $inviteeUri = $invitee['uri'] ?? null;
        $eventType = $scheduledEvent['event_type'] ?? null;

        return CalendlyEvent::updateOrCreate(
            ['calendly_event_uri' => $eventUri],
            [
                'calendly_integration_id' => $integration->id,
                'user_id' => $integration->user_id,
                'calendly_invitee_uri' => $inviteeUri,
                'event_type_uri' => $eventType,
                'event_type_name' => $scheduledEvent['name'] ?? null,
                'event_type_slug' => $this->extractSlugFromName($scheduledEvent['name'] ?? ''),
                'invitee_email' => $invitee['email'] ?? '',
                'invitee_name' => $invitee['name'] ?? null,
                'invitee_timezone' => $invitee['timezone'] ?? null,
                'start_time' => $scheduledEvent['start_time'] ?? now(),
                'end_time' => $scheduledEvent['end_time'] ?? now(),
                'status' => CalendlyEvent::STATUS_SCHEDULED,
                'location' => $scheduledEvent['location'] ?? null,
                'questions_and_answers' => $invitee['questions_and_answers'] ?? null,
                'raw_payload' => $rawPayload,
            ]
        );
    }

    /**
     * Find or create a subscriber from invitee data.
     */
    private function findOrCreateSubscriber(array $invitee, CalendlyIntegration $integration): Subscriber
    {
        $email = $invitee['email'] ?? '';
        $name = $invitee['name'] ?? '';

        // Parse name into first and last
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? null;
        $lastName = $nameParts[1] ?? null;

        $subscriber = Subscriber::where('user_id', $integration->user_id)
            ->where('email', $email)
            ->first();

        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'user_id' => $integration->user_id,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'source' => 'calendly',
                'status' => 'active',
                'is_active_global' => true,
                'subscribed_at' => now(),
            ]);

            Log::info('Created new subscriber from Calendly booking', [
                'subscriber_id' => $subscriber->id,
                'email' => $email,
            ]);
        } else {
            // Update name if not set
            if (!$subscriber->first_name && $firstName) {
                $subscriber->update(['first_name' => $firstName]);
            }
            if (!$subscriber->last_name && $lastName) {
                $subscriber->update(['last_name' => $lastName]);
            }
        }

        // Store custom field answers from Calendly
        if (!empty($invitee['questions_and_answers'])) {
            $this->storeCustomFieldAnswers($subscriber, $invitee['questions_and_answers']);
        }

        return $subscriber;
    }

    /**
     * Store custom field answers from Calendly booking form.
     */
    private function storeCustomFieldAnswers(Subscriber $subscriber, array $questionsAndAnswers): void
    {
        foreach ($questionsAndAnswers as $qa) {
            $question = $qa['question'] ?? '';
            $answer = $qa['answer'] ?? '';

            if (empty($question) || empty($answer)) {
                continue;
            }

            // Map common questions to subscriber fields
            $fieldName = $this->mapQuestionToField($question);
            if ($fieldName) {
                $subscriber->setCustomFieldValue($fieldName, $answer);
            }
        }
    }

    /**
     * Map Calendly question to custom field name.
     */
    private function mapQuestionToField(string $question): ?string
    {
        $lowerQuestion = strtolower($question);

        $mappings = [
            'phone' => 'phone',
            'telefon' => 'phone',
            'numer telefonu' => 'phone',
            'company' => 'company',
            'firma' => 'company',
            'organization' => 'company',
        ];

        foreach ($mappings as $keyword => $field) {
            if (str_contains($lowerQuestion, $keyword)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Add subscriber to configured mailing lists.
     */
    private function addToMailingLists(Subscriber $subscriber, CalendlyEvent $event, CalendlyIntegration $integration): void
    {
        $listIds = $integration->getListIdsForEventType($event->event_type_uri ?? '');

        foreach ($listIds as $listId) {
            $list = ContactList::find($listId);

            if (!$list || $list->user_id !== $integration->user_id) {
                continue;
            }

            // Check if already subscribed
            if (!$list->subscribers()->where('subscriber_id', $subscriber->id)->exists()) {
                $list->subscribers()->attach($subscriber->id, [
                    'status' => 'active',
                    'source' => 'calendly',
                    'subscribed_at' => now(),
                ]);

                Log::info('Added subscriber to mailing list from Calendly', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $listId,
                ]);
            }
        }
    }

    /**
     * Apply configured tags to subscriber.
     */
    private function applyTags(Subscriber $subscriber, CalendlyEvent $event, CalendlyIntegration $integration): void
    {
        $tagIds = $integration->getTagIdsForEventType($event->event_type_uri ?? '');

        foreach ($tagIds as $tagId) {
            $tag = Tag::find($tagId);

            if ($tag) {
                $subscriber->addTag($tag);
            }
        }

        // Also add a dynamic tag based on event type
        if ($event->event_type_slug) {
            $dynamicTagName = "calendly_{$event->event_type_slug}";
            $dynamicTag = Tag::firstOrCreate(
                ['name' => $dynamicTagName, 'user_id' => $integration->user_id],
                ['name' => $dynamicTagName, 'user_id' => $integration->user_id]
            );
            $subscriber->addTag($dynamicTag);
        }
    }

    /**
     * Sync booking with CRM.
     */
    private function syncWithCrm(Subscriber $subscriber, CalendlyEvent $event, CalendlyIntegration $integration): ?CrmContact
    {
        // Find or create CRM contact
        $crmContact = CrmContact::where('subscriber_id', $subscriber->id)->first();

        if (!$crmContact) {
            $crmContact = CrmContact::createFromSubscriber($subscriber, [
                'status' => $integration->getSetting('crm.default_status', 'lead'),
                'source' => 'calendly',
                'owner_id' => $integration->getSetting('crm.default_owner_id'),
            ]);

            Log::info('Created CRM contact from Calendly booking', [
                'crm_contact_id' => $crmContact->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        // Log the booking activity
        $this->logCrmActivity($event, 'calendly_booking', 'Meeting booked via Calendly', $crmContact);

        return $crmContact;
    }

    /**
     * Create a CRM task for the meeting.
     */
    private function createMeetingTask(CalendlyEvent $event, CrmContact $contact, CalendlyIntegration $integration): ?CrmTask
    {
        $meetingUrl = $event->meeting_url;
        $description = "Calendly: {$event->event_type_name}";

        if ($meetingUrl) {
            $description .= "\nLink: {$meetingUrl}";
        }

        $task = CrmTask::create([
            'user_id' => $integration->user_id,
            'crm_contact_id' => $contact->id,
            'title' => "Meeting: {$event->event_type_name}",
            'description' => $description,
            'type' => 'meeting',
            'status' => 'scheduled',
            'priority' => 'medium',
            'due_date' => $event->start_time,
            'assigned_to' => $integration->getSetting('crm.default_owner_id'),
            'metadata' => [
                'calendly_event_id' => $event->id,
                'calendly_event_uri' => $event->calendly_event_uri,
                'invitee_email' => $event->invitee_email,
                'meeting_url' => $meetingUrl,
            ],
        ]);

        Log::info('Created CRM task for Calendly meeting', [
            'task_id' => $task->id,
            'crm_contact_id' => $contact->id,
        ]);

        return $task;
    }

    /**
     * Log a CRM activity.
     */
    private function logCrmActivity(CalendlyEvent $event, string $type, string $content, ?CrmContact $contact = null): void
    {
        $contact = $contact ?? $event->crmContact;

        if (!$contact) {
            return;
        }

        $contact->logActivity($type, $content, [
            'calendly_event_id' => $event->id,
            'calendly_event_uri' => $event->calendly_event_uri,
            'event_type' => $event->event_type_name,
            'start_time' => $event->start_time?->toISOString(),
            'end_time' => $event->end_time?->toISOString(),
        ]);
    }

    /**
     * Dispatch webhook to NetSendo automation system.
     */
    private function dispatchWebhook(CalendlyIntegration $integration, string $eventName, CalendlyEvent $event, ?Subscriber $subscriber): void
    {
        $data = [
            'event' => [
                'id' => $event->id,
                'calendly_event_uri' => $event->calendly_event_uri,
                'event_type_name' => $event->event_type_name,
                'start_time' => $event->start_time?->toISOString(),
                'end_time' => $event->end_time?->toISOString(),
                'status' => $event->status,
                'meeting_url' => $event->meeting_url,
            ],
            'invitee' => [
                'email' => $event->invitee_email,
                'name' => $event->invitee_name,
                'timezone' => $event->invitee_timezone,
            ],
            'subscriber' => $subscriber ? [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
            ] : null,
        ];

        $this->webhookDispatcher->dispatch($integration->user_id, $eventName, $data);
    }

    /**
     * Extract a slug from event type name.
     */
    private function extractSlugFromName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
    }
}
