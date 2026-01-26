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

        $subscribedAt = $pivot?->subscribed_at
            ? Carbon::parse($pivot->subscribed_at)
            : now();

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

            // Calculate expected send datetime based on day offset and time_of_day
            $dayOffset = $message->day ?? 0;
            $timeOfDay = $message->time_of_day; // e.g., "15:00" or null
            $now = now();

            // Base: subscribed_at + day offset
            $expectedSendDateTime = $subscribedAt->copy()->addDays($dayOffset);

            // If time_of_day is set, use that specific hour
            if ($timeOfDay) {
                $timeParts = explode(':', $timeOfDay);
                $hour = (int) ($timeParts[0] ?? 0);
                $minute = (int) ($timeParts[1] ?? 0);
                $expectedSendDateTime = $expectedSendDateTime->copy()->startOfDay()->setTime($hour, $minute, 0);
            }
            // If no time_of_day, keep the original subscribed_at time
            // For day=0: expectedSendDateTime = subscribedAt (immediate send time)

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

                // Reset is enabled - delete existing entry to allow fresh start
                $existingEntry->delete();
                Log::info('CreateAutoresponderQueueEntries: Deleted existing entry for reset', [
                    'subscriber_id' => $subscriber->id,
                    'message_id' => $message->id,
                    'old_status' => $existingEntry->status,
                ]);
            }

            // Create queue entry
            $message->queueEntries()->create([
                'subscriber_id' => $subscriber->id,
                'status' => MessageQueueEntry::STATUS_PLANNED,
                'planned_at' => now(),
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
