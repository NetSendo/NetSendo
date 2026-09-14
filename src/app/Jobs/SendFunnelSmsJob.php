<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Services\PlaceholderService;
use App\Services\Sms\SmsProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send the text of a funnel SMS step. Unlike SendSmsJob it needs no message
 * record: the step carries its own text, and a message per step would show up
 * among the account's SMS campaigns.
 */
class SendFunnelSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        public Subscriber $subscriber,
        public string $content,
        public int $userId,
        public ?int $stepId = null
    ) {}

    public function handle(SmsProviderService $smsProviderService, PlaceholderService $placeholderService): void
    {
        $context = ['subscriber_id' => $this->subscriber->id, 'funnel_step_id' => $this->stepId];

        if (blank($this->subscriber->phone)) {
            Log::warning('Funnel SMS not sent: subscriber has no phone number', $context);
            return;
        }

        $provider = $smsProviderService->getBestProvider($this->userId);

        if (!$provider) {
            Log::warning('Funnel SMS not sent: no active SMS provider', $context);
            return;
        }

        if ($provider->hasReachedDailyLimit()) {
            Log::warning('Funnel SMS not sent: the provider reached its daily limit', $context);
            return;
        }

        $content = $placeholderService->replacePlaceholders($this->content, $this->subscriber);
        $result = $smsProviderService->getProvider($provider)->send($this->subscriber->phone, $content);

        if (!$result->success) {
            Log::warning('Funnel SMS sending failed', $context + [
                'reason' => $result->errorMessage ?? 'Unknown error',
                'code' => $result->errorCode,
            ]);
            return;
        }

        $provider->incrementSentCount();

        Log::info('Funnel SMS sent', $context + ['sms_message_id' => $result->messageId]);
    }
}
