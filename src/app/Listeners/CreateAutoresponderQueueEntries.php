<?php

namespace App\Listeners;

use App\Events\SubscriberSignedUp;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Creates queue entries for autoresponder messages when a subscriber joins a list.
 *
 * This listener ensures that new subscribers are added to the queue for any active
 * autoresponder messages on the list they joined. It checks if the subscriber's
 * expected send date (subscribed_at + day offset) is in the future to avoid
 * adding subscribers whose send time has already passed.
 */
class CreateAutoresponderQueueEntries implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(SubscriberSignedUp $event): void
    {
        $subscriber = $event->subscriber;
        $list = $event->list;

        if (!$subscriber || !$list) {
            return;
        }

        Log::info('CreateAutoresponderQueueEntries: Processing new subscriber', [
            'subscriber_id' => $subscriber->id,
            'list_id' => $list->id,
            'source' => $event->source,
        ]);

        // Find active autoresponder messages for this list
        $autoresponders = Message::where('type', 'autoresponder')
            ->where('is_active', true)
            ->where('status', 'scheduled')
            ->whereHas('contactLists', function ($query) use ($list) {
                $query->where('contact_lists.id', $list->id);
            })
            ->get();

        if ($autoresponders->isEmpty()) {
            Log::info('CreateAutoresponderQueueEntries: No active autoresponders for list', [
                'list_id' => $list->id,
            ]);
            return;
        }

        // Get subscriber's subscribed_at from the pivot
        $pivot = $subscriber->contactLists()
            ->where('contact_lists.id', $list->id)
            ->first()
            ?->pivot;

        $signedUpAt = $event->occurredAt ?? now();

        $subscribedAt = $pivot?->subscribed_at
            ? Carbon::parse($pivot->subscribed_at)
            : $signedUpAt;

        // Anchor for the sequence timeline. Normally the pivot subscribed_at
        // (reset by the signup path), but when the list keeps the original date
        // (resubscription_behavior = keep_date) the pivot date is stale — using
        // it would put every day-offset in the past and dump the whole sequence
        // at once. In that case anchor to the actual (re-)signup moment.
        $anchor = $subscribedAt->gte($signedUpAt->copy()->subHours(6))
            ? $subscribedAt
            : $signedUpAt;

        $created = 0;

        foreach ($autoresponders as $message) {
            // Check if subscriber is excluded from this message
            $excludedListIds = $message->excludedLists->pluck('id')->toArray();
            if (!empty($excludedListIds)) {
                $isExcluded = $subscriber->contactLists()
                    ->whereIn('contact_lists.id', $excludedListIds)
                    ->exists();
                if ($isExcluded) {
                    Log::info('CreateAutoresponderQueueEntries: Subscriber excluded', [
                        'subscriber_id' => $subscriber->id,
                        'message_id' => $message->id,
                    ]);
                    continue;
                }
            }

            // Expected send datetime (UTC), anchored to this (re-)signup.
            // Stored on the entry so CRON does not have to re-derive it from the
            // pivot (whose date may later change or be intentionally stale).
            $expectedSendDateTime = $message->calculateExpectedSendAt($anchor, $subscriber);

            // Always add to queue regardless of whether time has passed
            // The cron job will catch up on missed operations (e.g., after container restart)
            // This ensures subscribers are never skipped due to timing issues

            // Check if queue entry already exists
            $existingEntry = $message->queueEntries()
                ->where('subscriber_id', $subscriber->id)
                ->first();

            if ($existingEntry) {
                // Check list's reset_autoresponders_on_resubscription setting
                // Default is true (reset autoresponders on resubscription)
                $shouldReset = $list->reset_autoresponders_on_resubscription ?? true;

                if (!$shouldReset) {
                    // List setting says to keep existing entries
                    Log::info('CreateAutoresponderQueueEntries: Entry exists, skipping (reset disabled)', [
                        'subscriber_id' => $subscriber->id,
                        'message_id' => $message->id,
                        'status' => $existingEntry->status,
                    ]);
                    continue;
                }

                // Reset is enabled - restart this entry for a fresh cycle
                // (unique index on message_id+subscriber_id forbids a second row)
                $oldStatus = $existingEntry->status;
                $existingEntry->update([
                    'status' => MessageQueueEntry::STATUS_PLANNED,
                    'planned_at' => now(),
                    'scheduled_for' => $expectedSendDateTime,
                    'queued_at' => null,
                    'sent_at' => null,
                    'error_message' => null,
                ]);
                Log::info('CreateAutoresponderQueueEntries: Reset existing entry for resubscription', [
                    'subscriber_id' => $subscriber->id,
                    'message_id' => $message->id,
                    'old_status' => $oldStatus,
                    'scheduled_for' => $expectedSendDateTime->format('Y-m-d H:i'),
                ]);
                $created++;
                continue;
            }

            // Create queue entry
            $message->queueEntries()->create([
                'subscriber_id' => $subscriber->id,
                'status' => MessageQueueEntry::STATUS_PLANNED,
                'planned_at' => now(),
                'scheduled_for' => $expectedSendDateTime,
            ]);

            Log::info('CreateAutoresponderQueueEntries: Queue entry created', [
                'subscriber_id' => $subscriber->id,
                'message_id' => $message->id,
                'expected_send_datetime' => $expectedSendDateTime->format('Y-m-d H:i'),
            ]);

            $created++;
        }

        Log::info('CreateAutoresponderQueueEntries: Complete', [
            'subscriber_id' => $subscriber->id,
            'list_id' => $list->id,
            'created' => $created,
        ]);
    }

    /**
     * Determine number of seconds before retrying a failed job.
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    /**
     * Handle a job failure.
     */
    public function failed(SubscriberSignedUp $event, \Throwable $exception): void
    {
        Log::error('CreateAutoresponderQueueEntries failed permanently', [
            'subscriber_id' => $event->subscriber?->id,
            'list_id' => $event->list?->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
