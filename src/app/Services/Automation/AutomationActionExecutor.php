<?php

namespace App\Services\Automation;

use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\Tag;
use App\Models\Funnel;
use App\Models\Message;
use App\Jobs\SendEmailJob;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AutomationActionExecutor
{
    /**
     * Execute an action.
     */
    public function execute(array $config, ?Subscriber $subscriber, array $context): mixed
    {
        $type = $config['type'] ?? '';

        // Merge nested config with main config for backward compatibility
        // Frontend sends: {type: 'unsubscribe', config: {list_id: 123}}
        // Action methods expect: {list_id: 123}
        $actionConfig = array_merge($config, $config['config'] ?? []);

        return match ($type) {
            'send_email' => $this->sendEmail($actionConfig, $subscriber, $context),
            'add_tag' => $this->addTag($actionConfig, $subscriber),
            'remove_tag' => $this->removeTag($actionConfig, $subscriber),
            'move_to_list' => $this->moveToList($actionConfig, $subscriber, $context),
            'copy_to_list' => $this->copyToList($actionConfig, $subscriber),
            'unsubscribe' => $this->unsubscribe($actionConfig, $subscriber, $context),
            'call_webhook' => $this->callWebhook($actionConfig, $subscriber, $context),
            'start_funnel' => $this->startFunnel($actionConfig, $subscriber),
            'update_field' => $this->updateField($actionConfig, $subscriber),
            'notify_admin' => $this->notifyAdmin($actionConfig, $subscriber, $context),
            // CRM Actions
            'crm_create_task' => $this->createCrmTask($actionConfig, $subscriber, $context),
            'crm_update_score' => $this->updateCrmScore($actionConfig, $subscriber, $context),
            'crm_move_deal' => $this->moveCrmDeal($actionConfig, $context),
            'crm_assign_owner' => $this->assignCrmOwner($actionConfig, $context),
            'crm_convert_to_contact' => $this->convertToCrmContact($actionConfig, $subscriber, $context),
            'crm_log_activity' => $this->logCrmActivity($actionConfig, $context),
            'crm_update_contact_status' => $this->updateCrmContactStatus($actionConfig, $context),
            'crm_create_deal' => $this->createCrmDeal($actionConfig, $subscriber, $context),
            default => throw new \InvalidArgumentException("Unknown action type: {$type}"),
        };
    }

    /**
     * Send an email to subscriber.
     */
    protected function sendEmail(array $config, ?Subscriber $subscriber, array $context): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for send_email action');
        }

        $messageId = $config['message_id'] ?? null;
        $message = Message::find($messageId);

        if (!$message) {
            throw new \InvalidArgumentException("Message not found: {$messageId}");
        }

        // Queue the email
        SendEmailJob::dispatch(
            $message,
            $subscriber
        );

        return ['message_id' => $messageId, 'queued' => true];
    }

    /**
     * Add a tag to subscriber.
     */
    protected function addTag(array $config, ?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for add_tag action');
        }

        $tagId = $config['tag_id'] ?? null;
        $tagName = $config['tag_name'] ?? null;

        if ($tagId) {
            $tag = Tag::find($tagId);
        } elseif ($tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
        } else {
            throw new \InvalidArgumentException('Tag ID or name required');
        }

        if ($tag) {
            // Use method that automatically dispatches TagAdded event
            $subscriber->addTag($tag);
        }

        return ['tag_id' => $tag?->id, 'tag_name' => $tag?->name];
    }

    /**
     * Remove a tag from subscriber.
     */
    protected function removeTag(array $config, ?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for remove_tag action');
        }

        $tagId = $config['tag_id'] ?? null;

        if (!$tagId) {
            throw new \InvalidArgumentException('Tag ID required');
        }

        $tag = Tag::find($tagId);
        if ($tag) {
            // Use method that automatically dispatches TagRemoved event
            $subscriber->removeTag($tag);
        }

        return ['tag_id' => $tagId, 'removed' => true];
    }

    /**
     * Move subscriber to another list (unsubscribe from current, subscribe to new).
     */
    protected function moveToList(array $config, ?Subscriber $subscriber, array $context): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for move_to_list action');
        }

        $targetListId = $config['list_id'] ?? null;
        $sourceListId = $context['list_id'] ?? null;

        if (!$targetListId) {
            throw new \InvalidArgumentException('Target list ID required');
        }

        $targetList = ContactList::find($targetListId);
        if (!$targetList) {
            throw new \InvalidArgumentException("Target list not found: {$targetListId}");
        }

        // Unsubscribe from source list
        if ($sourceListId) {
            $subscriber->contactLists()->updateExistingPivot($sourceListId, [
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }

        // Subscribe to target list with resubscription behavior
        $existingPivot = $subscriber->contactLists()->where('contact_list_id', $targetListId)->first();

        if ($existingPivot) {
            $wasActive = $existingPivot->pivot->status === 'active';
            $shouldResetDate = !$wasActive || ($targetList->resubscription_behavior ?? 'reset_date') === 'reset_date';

            $pivotData = [
                'status' => 'active',
                'unsubscribed_at' => null,
            ];

            if ($shouldResetDate) {
                $pivotData['subscribed_at'] = now();
            }

            $subscriber->contactLists()->updateExistingPivot($targetListId, $pivotData);
        } else {
            $subscriber->contactLists()->attach($targetListId, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        return [
            'source_list_id' => $sourceListId,
            'target_list_id' => $targetListId,
        ];
    }

    /**
     * Copy subscriber to another list.
     */
    protected function copyToList(array $config, ?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for copy_to_list action');
        }

        $targetListId = $config['list_id'] ?? null;

        if (!$targetListId) {
            throw new \InvalidArgumentException('Target list ID required');
        }

        $targetList = ContactList::find($targetListId);
        if (!$targetList) {
            throw new \InvalidArgumentException("Target list not found: {$targetListId}");
        }

        // Subscribe to target list with resubscription behavior
        $existingPivot = $subscriber->contactLists()->where('contact_list_id', $targetListId)->first();

        if ($existingPivot) {
            $wasActive = $existingPivot->pivot->status === 'active';
            $shouldResetDate = !$wasActive || ($targetList->resubscription_behavior ?? 'reset_date') === 'reset_date';

            $pivotData = [
                'status' => 'active',
                'unsubscribed_at' => null,
            ];

            if ($shouldResetDate) {
                $pivotData['subscribed_at'] = now();
            }

            $subscriber->contactLists()->updateExistingPivot($targetListId, $pivotData);
        } else {
            $subscriber->contactLists()->attach($targetListId, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        return ['target_list_id' => $targetListId, 'copied' => true];
    }

    /**
     * Unsubscribe from a list.
     */
    protected function unsubscribe(array $config, ?Subscriber $subscriber, array $context): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for unsubscribe action');
        }

        $listId = $config['list_id'] ?? $context['list_id'] ?? null;

        if (!$listId) {
            throw new \InvalidArgumentException('List ID required');
        }

        $subscriber->contactLists()->updateExistingPivot($listId, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return ['list_id' => $listId, 'unsubscribed' => true];
    }

    /**
     * Call a webhook URL.
     */
    protected function callWebhook(array $config, ?Subscriber $subscriber, array $context): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');

        if (!$url) {
            throw new \InvalidArgumentException('Webhook URL required');
        }

        $payload = [
            'event' => $context,
            'subscriber' => $subscriber ? [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'phone' => $subscriber->phone,
            ] : null,
            'timestamp' => now()->toIso8601String(),
        ];

        // Add custom headers if configured
        $headers = $config['headers'] ?? [];

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->{strtolower($method)}($url, $payload);

            return [
                'url' => $url,
                'status_code' => $response->status(),
                'success' => $response->successful(),
            ];
        } catch (\Exception $e) {
            Log::warning('Webhook call failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'url' => $url,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Start a funnel for subscriber.
     */
    protected function startFunnel(array $config, ?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for start_funnel action');
        }

        $funnelId = $config['funnel_id'] ?? null;

        if (!$funnelId) {
            throw new \InvalidArgumentException('Funnel ID required');
        }

        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            throw new \InvalidArgumentException("Funnel not found: {$funnelId}");
        }

        // Enroll subscriber in funnel
        $funnelService = app(FunnelExecutionService::class);
        $funnelService->enrollSubscriber($funnel, $subscriber);

        return ['funnel_id' => $funnelId, 'enrolled' => true];
    }

    /**
     * Update a custom field value.
     */
    protected function updateField(array $config, ?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for update_field action');
        }

        $field = $config['field'] ?? null;
        $value = $config['value'] ?? '';

        if (!$field) {
            throw new \InvalidArgumentException('Field name required');
        }

        // Built-in fields
        if (in_array($field, ['first_name', 'last_name', 'phone'])) {
            $subscriber->update([$field => $value]);
            return ['field' => $field, 'value' => $value, 'updated' => true];
        }

        // Custom field
        $customField = \App\Models\CustomField::where('slug', $field)->first();
        if ($customField) {
            $subscriber->fieldValues()->updateOrCreate(
                ['custom_field_id' => $customField->id],
                ['value' => $value]
            );
        }

        return ['field' => $field, 'value' => $value, 'updated' => true];
    }

    /**
     * Send notification to admin.
     */
    protected function notifyAdmin(array $config, ?Subscriber $subscriber, array $context): array
    {
        $email = $config['email'] ?? null;
        $subject = $config['subject'] ?? 'Powiadomienie o automatyzacji';
        $message = $config['message'] ?? '';

        if (!$email) {
            throw new \InvalidArgumentException('Admin email required');
        }

        // Replace placeholders in message
        $replacements = [
            '{{subscriber_email}}' => $subscriber?->email ?? '-',
            '{{subscriber_name}}' => trim(($subscriber?->first_name ?? '') . ' ' . ($subscriber?->last_name ?? '')) ?: '-',
            '{{list_name}}' => $context['list_name'] ?? '-',
            '{{trigger_event}}' => $context['trigger_event'] ?? '-',
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $message);

        Mail::raw($message, function ($mail) use ($email, $subject) {
            $mail->to($email)->subject($subject);
        });

        return ['email' => $email, 'sent' => true];
    }

    // ===== CRM ACTIONS =====

    /**
     * Create a CRM task.
     */
    protected function createCrmTask(array $config, ?Subscriber $subscriber, array $context): array
    {
        $userId = $context['user_id'] ?? null;
        if (!$userId) {
            throw new \InvalidArgumentException('User ID required for crm_create_task action');
        }

        $task = \App\Models\CrmTask::create([
            'user_id' => $userId,
            'owner_id' => $config['owner_id'] ?? $context['owner_id'] ?? $userId,
            'crm_contact_id' => $config['contact_id'] ?? $context['contact_id'] ?? null,
            'crm_deal_id' => $config['deal_id'] ?? $context['deal_id'] ?? null,
            'title' => $this->replacePlaceholders($config['title'] ?? 'Automatyczne zadanie', $subscriber, $context),
            'description' => $this->replacePlaceholders($config['description'] ?? '', $subscriber, $context),
            'type' => $config['task_type'] ?? 'follow_up',
            'priority' => $config['priority'] ?? 'medium',
            'status' => 'pending',
            'due_date' => now()->addDays((int) ($config['due_days'] ?? 2)),
        ]);

        return ['task_id' => $task->id, 'created' => true];
    }

    /**
     * Update CRM contact lead score.
     */
    protected function updateCrmScore(array $config, ?Subscriber $subscriber, array $context): array
    {
        $contactId = $config['contact_id'] ?? $context['contact_id'] ?? null;
        $delta = (int) ($config['score_delta'] ?? $config['delta'] ?? 0);
        $setAbsolute = $config['set_absolute'] ?? false;
        $absoluteScore = (int) ($config['absolute_score'] ?? 0);

        if (!$contactId) {
            // Try to find contact from subscriber
            if ($subscriber) {
                $contact = \App\Models\CrmContact::where('subscriber_id', $subscriber->id)->first();
                $contactId = $contact?->id;
            }
        }

        if (!$contactId) {
            throw new \InvalidArgumentException('Contact ID required for crm_update_score action');
        }

        $contact = \App\Models\CrmContact::find($contactId);
        if (!$contact) {
            throw new \InvalidArgumentException("Contact not found: {$contactId}");
        }

        $oldScore = $contact->score;

        if ($setAbsolute) {
            $contact->update(['score' => $absoluteScore]);
        } else {
            $contact->updateScore($delta);
        }

        return [
            'contact_id' => $contactId,
            'old_score' => $oldScore,
            'new_score' => $contact->fresh()->score,
        ];
    }

    /**
     * Move CRM deal to a specific stage.
     */
    protected function moveCrmDeal(array $config, array $context): array
    {
        $dealId = $config['deal_id'] ?? $context['deal_id'] ?? null;
        $targetStageId = $config['stage_id'] ?? $config['target_stage_id'] ?? null;

        if (!$dealId) {
            throw new \InvalidArgumentException('Deal ID required for crm_move_deal action');
        }

        if (!$targetStageId) {
            throw new \InvalidArgumentException('Target stage ID required for crm_move_deal action');
        }

        $deal = \App\Models\CrmDeal::find($dealId);
        if (!$deal) {
            throw new \InvalidArgumentException("Deal not found: {$dealId}");
        }

        $targetStage = \App\Models\CrmStage::find($targetStageId);
        if (!$targetStage) {
            throw new \InvalidArgumentException("Stage not found: {$targetStageId}");
        }

        $oldStageId = $deal->crm_stage_id;
        $deal->moveToStage($targetStage);

        return [
            'deal_id' => $dealId,
            'old_stage_id' => $oldStageId,
            'new_stage_id' => $targetStageId,
        ];
    }

    /**
     * Assign owner to CRM contact or deal.
     */
    protected function assignCrmOwner(array $config, array $context): array
    {
        $ownerId = $config['owner_id'] ?? $config['new_owner_id'] ?? null;
        $dealId = $config['deal_id'] ?? $context['deal_id'] ?? null;
        $contactId = $config['contact_id'] ?? $context['contact_id'] ?? null;

        if (!$ownerId) {
            throw new \InvalidArgumentException('Owner ID required for crm_assign_owner action');
        }

        $results = ['owner_id' => $ownerId];

        if ($dealId) {
            $deal = \App\Models\CrmDeal::find($dealId);
            if ($deal) {
                $deal->update(['owner_id' => $ownerId]);
                $results['deal_id'] = $dealId;
            }
        }

        if ($contactId) {
            $contact = \App\Models\CrmContact::find($contactId);
            if ($contact) {
                $contact->update(['owner_id' => $ownerId]);
                $results['contact_id'] = $contactId;
            }
        }

        return $results;
    }

    /**
     * Convert subscriber to CRM contact.
     */
    protected function convertToCrmContact(array $config, ?Subscriber $subscriber, array $context): array
    {
        if (!$subscriber) {
            throw new \InvalidArgumentException('Subscriber required for crm_convert_to_contact action');
        }

        // Check if contact already exists
        $existingContact = \App\Models\CrmContact::where('subscriber_id', $subscriber->id)->first();
        if ($existingContact) {
            return ['contact_id' => $existingContact->id, 'already_exists' => true];
        }

        $contact = \App\Models\CrmContact::createFromSubscriber($subscriber, [
            'status' => $config['status'] ?? 'lead',
            'source' => $config['source'] ?? $context['source'] ?? 'automation',
            'owner_id' => $config['owner_id'] ?? $context['user_id'] ?? null,
            'crm_company_id' => $config['company_id'] ?? null,
        ]);

        // Dispatch event for further automations
        event(new \App\Events\CrmContactCreated($contact));

        return ['contact_id' => $contact->id, 'created' => true];
    }

    /**
     * Log CRM activity.
     */
    protected function logCrmActivity(array $config, array $context): array
    {
        $userId = $context['user_id'] ?? null;
        $dealId = $config['deal_id'] ?? $context['deal_id'] ?? null;
        $contactId = $config['contact_id'] ?? $context['contact_id'] ?? null;

        if (!$userId) {
            throw new \InvalidArgumentException('User ID required for crm_log_activity action');
        }

        $activityData = [
            'user_id' => $userId,
            'created_by_id' => $context['created_by_id'] ?? $userId,
            'type' => $config['activity_type'] ?? 'note',
            'content' => $config['content'] ?? 'Aktywność z automatyzacji',
            'metadata' => $config['metadata'] ?? [],
        ];

        if ($dealId) {
            $deal = \App\Models\CrmDeal::find($dealId);
            if ($deal) {
                $activity = $deal->activities()->create($activityData);
                return ['activity_id' => $activity->id, 'subject' => 'deal'];
            }
        }

        if ($contactId) {
            $contact = \App\Models\CrmContact::find($contactId);
            if ($contact) {
                $activity = $contact->activities()->create($activityData);
                return ['activity_id' => $activity->id, 'subject' => 'contact'];
            }
        }

        throw new \InvalidArgumentException('Deal ID or Contact ID required for crm_log_activity action');
    }

    /**
     * Update CRM contact status.
     */
    protected function updateCrmContactStatus(array $config, array $context): array
    {
        $contactId = $config['contact_id'] ?? $context['contact_id'] ?? null;
        $newStatus = $config['status'] ?? $config['new_status'] ?? null;

        if (!$contactId) {
            throw new \InvalidArgumentException('Contact ID required for crm_update_contact_status action');
        }

        if (!$newStatus) {
            throw new \InvalidArgumentException('New status required for crm_update_contact_status action');
        }

        $contact = \App\Models\CrmContact::find($contactId);
        if (!$contact) {
            throw new \InvalidArgumentException("Contact not found: {$contactId}");
        }

        $oldStatus = $contact->status;
        $contact->update(['status' => $newStatus]);

        // Dispatch event for further automations
        event(new \App\Events\CrmContactStatusChanged($contact, $oldStatus, $newStatus));

        return [
            'contact_id' => $contactId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ];
    }

    /**
     * Create a new CRM deal.
     */
    protected function createCrmDeal(array $config, ?Subscriber $subscriber, array $context): array
    {
        $userId = $context['user_id'] ?? null;
        if (!$userId) {
            throw new \InvalidArgumentException('User ID required for crm_create_deal action');
        }

        // Get default pipeline if not specified
        $pipelineId = $config['pipeline_id'] ?? null;
        if (!$pipelineId) {
            $defaultPipeline = \App\Models\CrmPipeline::where('user_id', $userId)->first();
            $pipelineId = $defaultPipeline?->id;
        }

        if (!$pipelineId) {
            throw new \InvalidArgumentException('Pipeline ID required for crm_create_deal action');
        }

        // Get first stage if not specified
        $stageId = $config['stage_id'] ?? null;
        if (!$stageId) {
            $firstStage = \App\Models\CrmStage::where('crm_pipeline_id', $pipelineId)
                ->orderBy('order')
                ->first();
            $stageId = $firstStage?->id;
        }

        // Get or create contact from subscriber
        $contactId = $config['contact_id'] ?? $context['contact_id'] ?? null;
        if (!$contactId && $subscriber) {
            $contact = \App\Models\CrmContact::where('subscriber_id', $subscriber->id)->first();
            if (!$contact) {
                $contact = \App\Models\CrmContact::createFromSubscriber($subscriber);
            }
            $contactId = $contact->id;
        }

        $deal = \App\Models\CrmDeal::create([
            'user_id' => $userId,
            'crm_pipeline_id' => $pipelineId,
            'crm_stage_id' => $stageId,
            'crm_contact_id' => $contactId,
            'crm_company_id' => $config['company_id'] ?? null,
            'owner_id' => $config['owner_id'] ?? $context['owner_id'] ?? $userId,
            'name' => $this->replacePlaceholders($config['name'] ?? 'Nowy deal', $subscriber, $context),
            'value' => (float) ($config['value'] ?? 0),
            'currency' => $config['currency'] ?? 'PLN',
            'expected_close_date' => $config['expected_close_days']
                ? now()->addDays((int) $config['expected_close_days'])
                : null,
            'status' => 'open',
        ]);

        // Dispatch event for further automations
        event(new \App\Events\CrmDealCreated($deal));

        return ['deal_id' => $deal->id, 'created' => true];
    }

    /**
     * Replace placeholders in text with actual values.
     */
    protected function replacePlaceholders(string $text, ?Subscriber $subscriber, array $context): string
    {
        $replacements = [
            '{{subscriber_email}}' => $subscriber?->email ?? $context['email'] ?? '',
            '{{subscriber_name}}' => trim(($subscriber?->first_name ?? '') . ' ' . ($subscriber?->last_name ?? '')) ?: '',
            '{{first_name}}' => $subscriber?->first_name ?? $context['first_name'] ?? '',
            '{{last_name}}' => $subscriber?->last_name ?? $context['last_name'] ?? '',
            '{{deal_name}}' => $context['deal_name'] ?? '',
            '{{deal_value}}' => $context['deal_value'] ?? '',
            '{{stage_name}}' => $context['new_stage_name'] ?? $context['stage_name'] ?? '',
            '{{pipeline_name}}' => $context['pipeline_name'] ?? '',
            '{{task_title}}' => $context['task_title'] ?? '',
            '{{date}}' => now()->format('Y-m-d'),
            '{{datetime}}' => now()->format('Y-m-d H:i'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
