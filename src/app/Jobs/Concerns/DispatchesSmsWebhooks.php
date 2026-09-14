<?php

namespace App\Jobs\Concerns;

use App\Models\SmsProvider;
use App\Services\Sms\SmsResult;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * The `sms.sent` and `sms.failed` webhooks of the SMS jobs, with the same fields
 * whichever job sends: a campaign or API SMS has a `message_id`, a funnel SMS a
 * `funnel_step_id`, and the other one is null.
 *
 * Expects the job to have a public `$subscriber`.
 */
trait DispatchesSmsWebhooks
{
    /**
     * The account the SMS is sent for and where it comes from:
     * `['user_id' => int, 'message_id' => ?int, 'funnel_step_id' => ?int]`.
     */
    abstract protected function smsWebhookOrigin(): array;

    /**
     * `sms.sent` — the provider accepted the SMS.
     */
    protected function dispatchSmsSent(SmsProvider $provider, string $content, SmsResult $result): void
    {
        $this->dispatchSmsWebhook('sms.sent', $provider, [
            'content' => $content,
            'provider_message_id' => $result->messageId,
            'credits' => $result->credits,
            'parts' => $result->parts,
        ]);
    }

    /**
     * `sms.failed` — the SMS will not be sent. Not for an attempt that is retried.
     */
    protected function dispatchSmsFailed(?SmsProvider $provider, string $error, ?string $errorCode = null): void
    {
        $this->dispatchSmsWebhook('sms.failed', $provider, [
            'error' => $error,
            'error_code' => $errorCode,
        ]);
    }

    private function dispatchSmsWebhook(string $event, ?SmsProvider $provider, array $details): void
    {
        $origin = $this->smsWebhookOrigin();

        $data = [
            'message_id' => $origin['message_id'] ?? null,
            'funnel_step_id' => $origin['funnel_step_id'] ?? null,
            'subscriber_id' => $this->subscriber->id,
            'phone' => $this->subscriber->phone,
            'provider' => $provider?->name,
        ] + $details;

        // A webhook must not fail the job: a retry would send the SMS again.
        try {
            app(WebhookDispatcher::class)->dispatch($origin['user_id'], $event, $data);
        } catch (\Throwable $e) {
            Log::error('SMS webhook could not be dispatched', [
                'event' => $event,
                'subscriber_id' => $this->subscriber->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
