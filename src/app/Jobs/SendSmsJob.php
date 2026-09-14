<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesSmsWebhooks;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\SmsProvider;
use App\Models\MessageQueueEntry;
use App\Services\Sms\SmsProviderService;
use App\Services\PlaceholderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use DispatchesSmsWebhooks;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public Message $message,
        public Subscriber $subscriber,
        public ?SmsProvider $smsProvider = null,
        public ?int $queueEntryId = null
    ) {}

    public function handle(SmsProviderService $smsProviderService, PlaceholderService $placeholderService): void
    {
        // Get phone number
        $phone = $this->subscriber->phone;
        if (!$phone) {
            $this->failWithReason('Subskrybent nie ma numeru telefonu', 'NO_PHONE');
            return;
        }

        $provider = $this->resolveProvider($smsProviderService);
        if (!$provider) {
            $this->failWithReason('Brak aktywnego dostawcy SMS', 'NO_PROVIDER');
            return;
        }

        // Check provider limits
        if ($provider->hasReachedDailyLimit()) {
            $this->failWithReason('Osiągnięto dzienny limit wysyłek', 'DAILY_LIMIT', $provider);
            return;
        }

        try {
            // Process message content with placeholders
            $content = $placeholderService->replacePlaceholders(
                (string) $this->message->content,
                $this->subscriber
            );

            // Get the actual provider instance
            $smsDriver = $smsProviderService->getProvider($provider);

            // Send SMS
            $result = $smsDriver->send($phone, $content);

            if ($result->success) {
                // Update provider sent count
                $provider->incrementSentCount();

                // Update queue entry if exists
                $this->updateQueueEntry('sent', [
                    'message_id' => $result->messageId,
                    'credits' => $result->credits,
                    'parts' => $result->parts,
                ]);

                Log::info('SMS sent successfully', [
                    'message_id' => $this->message->id,
                    'subscriber_id' => $this->subscriber->id,
                    'sms_message_id' => $result->messageId,
                ]);

                $this->dispatchSmsSent($provider, $content, $result);
            } else {
                $this->failWithReason($result->errorMessage ?? 'Unknown error', $result->errorCode, $provider);
            }
        } catch (\Exception $e) {
            Log::error('SendSmsJob failed', [
                'message_id' => $this->message->id,
                'subscriber_id' => $this->subscriber->id,
                'error' => $e->getMessage(),
            ]);

            $this->recordFailure($e->getMessage(), 'EXCEPTION');

            // Re-throw to trigger retry; failed() reports sms.failed after the last attempt
            throw $e;
        }
    }

    /**
     * Get or find the best SMS provider using hierarchical resolution.
     * Priority: Explicit -> Message -> List -> Global Default -> any active provider
     */
    private function resolveProvider(SmsProviderService $smsProviderService): ?SmsProvider
    {
        return $this->smsProvider
            ?? $this->message->getEffectiveSmsProvider()
            ?? $smsProviderService->getBestProvider($this->message->user_id);
    }

    protected function smsWebhookOrigin(): array
    {
        return [
            'user_id' => $this->message->user_id,
            'message_id' => $this->message->id,
            'funnel_step_id' => null,
        ];
    }

    /**
     * Mark as failed with reason. The job is not retried, so sms.failed goes out.
     */
    private function failWithReason(string $reason, ?string $code = null, ?SmsProvider $provider = null): void
    {
        $this->recordFailure($reason, $code);

        $this->dispatchSmsFailed($provider, $reason, $code);
    }

    /**
     * Log the failure and mark the queue entry as failed.
     */
    private function recordFailure(string $reason, ?string $code): void
    {
        Log::warning('SMS sending failed', [
            'message_id' => $this->message->id,
            'subscriber_id' => $this->subscriber->id,
            'reason' => $reason,
            'code' => $code,
        ]);

        $this->updateQueueEntry('failed', [
            'error' => $reason,
            'error_code' => $code,
        ]);
    }

    /**
     * Update queue entry status.
     */
    private function updateQueueEntry(string $status, array $metadata = []): void
    {
        if (!$this->queueEntryId) {
            return;
        }

        try {
            $entry = MessageQueueEntry::find($this->queueEntryId);
            if ($entry) {
                $entry->update([
                    'status' => $status,
                    'sent_at' => $status === 'sent' ? now() : null,
                    'metadata' => array_merge($entry->metadata ?? [], $metadata),
                ]);

                // On successful send, check if all entries are processed for broadcast
                if ($status === 'sent') {
                    $this->checkAndMarkMessageComplete();
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update queue entry', [
                'queue_entry_id' => $this->queueEntryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if all queue entries are processed and mark message as sent.
     */
    private function checkAndMarkMessageComplete(): void
    {
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
                    Log::info("Broadcast SMS message {$this->message->id} marked as sent - all entries processed");
                }
            }
        }
    }

    /**
     * Handle job failure after all retries.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendSmsJob permanently failed', [
            'message_id' => $this->message->id,
            'subscriber_id' => $this->subscriber->id,
            'error' => $exception->getMessage(),
        ]);

        $this->updateQueueEntry('failed', [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
        ]);

        try {
            $provider = $this->resolveProvider(app(SmsProviderService::class));
        } catch (\Throwable) {
            $provider = null;
        }

        $this->dispatchSmsFailed($provider, $exception->getMessage(), 'EXCEPTION');
    }
}
