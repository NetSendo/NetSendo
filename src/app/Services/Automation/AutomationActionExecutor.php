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
            $subscriber,
            $subscriber->email
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
}
