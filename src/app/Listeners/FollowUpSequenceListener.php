<?php

namespace App\Listeners;

use App\Events\CrmContactCreated;
use App\Events\CrmDealCreated;
use App\Events\CrmDealStageChanged;
use App\Events\CrmTaskCompleted;
use App\Models\CrmFollowUpSequence;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener for automatic follow-up sequence enrollment based on CRM events.
 */
class FollowUpSequenceListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle CRM deal created event.
     * Enrolls the deal's contact in sequences triggered by 'on_deal_created'.
     */
    public function handleDealCreated(CrmDealCreated $event): void
    {
        $deal = $event->deal;
        $contact = $deal->contact;

        if (!$contact) {
            Log::debug('FollowUpSequenceListener: Deal has no contact, skipping enrollment', [
                'deal_id' => $deal->id,
            ]);
            return;
        }

        $sequences = CrmFollowUpSequence::forUser($deal->user_id)
            ->active()
            ->byTrigger('on_deal_created')
            ->get();

        foreach ($sequences as $sequence) {
            $this->enrollContactIfNotEnrolled($sequence, $contact, [
                'triggered_by' => 'deal_created',
                'deal_id' => $deal->id,
            ]);
        }
    }

    /**
     * Handle CRM contact created event.
     * Enrolls the contact in sequences triggered by 'on_contact_created'.
     */
    public function handleContactCreated(CrmContactCreated $event): void
    {
        $contact = $event->contact;

        $sequences = CrmFollowUpSequence::forUser($contact->user_id)
            ->active()
            ->byTrigger('on_contact_created')
            ->get();

        foreach ($sequences as $sequence) {
            $this->enrollContactIfNotEnrolled($sequence, $contact, [
                'triggered_by' => 'contact_created',
            ]);
        }
    }

    /**
     * Handle CRM task completed event.
     * Enrolls the task's contact in sequences triggered by 'on_task_completed'.
     */
    public function handleTaskCompleted(CrmTaskCompleted $event): void
    {
        $task = $event->task;
        $contact = $task->contact;

        if (!$contact) {
            return;
        }

        $sequences = CrmFollowUpSequence::forUser($task->user_id)
            ->active()
            ->byTrigger('on_task_completed')
            ->get();

        foreach ($sequences as $sequence) {
            $this->enrollContactIfNotEnrolled($sequence, $contact, [
                'triggered_by' => 'task_completed',
                'task_id' => $task->id,
            ]);
        }
    }

    /**
     * Handle CRM deal stage changed event.
     * Enrolls the deal's contact in sequences triggered by 'on_deal_stage_changed'.
     */
    public function handleDealStageChanged(CrmDealStageChanged $event): void
    {
        $deal = $event->deal;
        $contact = $deal->contact;

        if (!$contact) {
            return;
        }

        $sequences = CrmFollowUpSequence::forUser($deal->user_id)
            ->active()
            ->byTrigger('on_deal_stage_changed')
            ->get();

        foreach ($sequences as $sequence) {
            // Check if sequence has stage condition in metadata
            $triggerConditions = $sequence->trigger_conditions ?? [];
            $targetStageId = $triggerConditions['stage_id'] ?? null;

            // If stage_id is specified, only enroll if it matches the new stage
            if ($targetStageId && $targetStageId != $event->newStage->id) {
                continue;
            }

            $this->enrollContactIfNotEnrolled($sequence, $contact, [
                'triggered_by' => 'deal_stage_changed',
                'deal_id' => $deal->id,
                'from_stage' => $event->oldStage->name,
                'to_stage' => $event->newStage->name,
            ]);
        }
    }

    /**
     * Enroll a contact in a sequence if not already enrolled.
     */
    protected function enrollContactIfNotEnrolled(
        CrmFollowUpSequence $sequence,
        $contact,
        array $metadata = []
    ): void {
        if ($sequence->isContactEnrolled($contact)) {
            Log::debug('FollowUpSequenceListener: Contact already enrolled', [
                'contact_id' => $contact->id,
                'sequence_id' => $sequence->id,
            ]);
            return;
        }

        try {
            $enrollment = $sequence->enrollContact($contact);

            // Store trigger metadata
            if (!empty($metadata)) {
                $enrollment->update([
                    'metadata' => array_merge($enrollment->metadata ?? [], $metadata),
                ]);
            }

            Log::info('FollowUpSequenceListener: Contact enrolled in sequence', [
                'contact_id' => $contact->id,
                'sequence_id' => $sequence->id,
                'enrollment_id' => $enrollment->id,
                'trigger' => $metadata['triggered_by'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('FollowUpSequenceListener: Failed to enroll contact', [
                'contact_id' => $contact->id,
                'sequence_id' => $sequence->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            CrmDealCreated::class => 'handleDealCreated',
            CrmContactCreated::class => 'handleContactCreated',
            CrmTaskCompleted::class => 'handleTaskCompleted',
            CrmDealStageChanged::class => 'handleDealStageChanged',
        ];
    }
}
