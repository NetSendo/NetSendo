<?php

namespace App\Listeners;

use App\Events\CrmDealStageChanged;
use App\Events\CrmTaskOverdue;
use App\Events\CrmContactReplied;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DealStageChangedNotification;
use App\Notifications\TaskOverdueNotification;
use App\Notifications\ContactRepliedNotification;

/**
 * Logs, notifications and activity records for CRM events. Automation rules
 * for these events run in TriggerAutomationsListener.
 */
class CrmEventListener
{
    /**
     * Handle deal stage changed event.
     */
    public function handleDealStageChanged(CrmDealStageChanged $event): void
    {
        Log::info('CRM: Deal stage changed', [
            'deal_id' => $event->deal->id,
            'from' => $event->oldStage->name,
            'to' => $event->newStage->name,
        ]);

        // Send notification to deal owner if configured
        if ($event->deal->owner && $event->newStage->is_won) {
            try {
                $event->deal->owner->notify(new DealStageChangedNotification($event->deal, 'won'));
            } catch (\Exception $e) {
                // Notification class may not exist yet
                Log::debug('CRM: DealStageChangedNotification not available');
            }
        }
    }

    /**
     * Handle task overdue event.
     */
    public function handleTaskOverdue(CrmTaskOverdue $event): void
    {
        Log::info('CRM: Task overdue', [
            'task_id' => $event->task->id,
            'title' => $event->task->title,
            'due_date' => $event->task->due_date,
        ]);

        // Notify task owner
        if ($event->task->owner) {
            try {
                $event->task->owner->notify(new TaskOverdueNotification($event->task));
            } catch (\Exception $e) {
                Log::debug('CRM: TaskOverdueNotification not available');
            }
        }
    }

    /**
     * Handle contact replied event.
     */
    public function handleContactReplied(CrmContactReplied $event): void
    {
        Log::info('CRM: Contact replied', [
            'contact_id' => $event->contact->id,
            'channel' => $event->channel,
        ]);

        // Update contact last activity
        $event->contact->update([
            'last_activity_at' => now(),
        ]);

        // Create activity record
        $event->contact->activities()->create([
            'user_id' => $event->contact->user_id,
            'created_by_id' => null, // System generated
            'type' => 'email_reply',
            'content' => implode("\n\n", array_filter([$event->subject, $event->excerpt]))
                ?: "Kontakt odpowiedział przez {$event->channel}",
            'metadata' => [
                'channel' => $event->channel,
                'message_id' => $event->messageId,
                'subject' => $event->subject,
            ],
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            CrmDealStageChanged::class => 'handleDealStageChanged',
            CrmTaskOverdue::class => 'handleTaskOverdue',
            CrmContactReplied::class => 'handleContactReplied',
        ];
    }
}
