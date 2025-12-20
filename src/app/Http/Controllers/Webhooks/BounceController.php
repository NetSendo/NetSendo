<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\Message;
use App\Events\EmailBounced;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BounceController extends Controller
{
    /**
     * Handle bounce webhook from SendGrid
     * 
     * @see https://docs.sendgrid.com/for-developers/tracking-events/event
     */
    public function sendgrid(Request $request)
    {
        $events = $request->all();

        foreach ($events as $event) {
            if (!in_array($event['event'] ?? '', ['bounce', 'dropped', 'deferred'])) {
                continue;
            }

            $email = $event['email'] ?? null;
            if (!$email) {
                continue;
            }

            $this->processBounce(
                email: $email,
                bounceType: $this->determineBounceType($event),
                bounceReason: $event['reason'] ?? $event['response'] ?? 'Unknown',
                messageId: $event['message_id'] ?? null,
                provider: 'sendgrid'
            );
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle bounce webhook from Postmark
     * 
     * @see https://postmarkapp.com/developer/webhooks/bounce-webhook
     */
    public function postmark(Request $request)
    {
        $event = $request->all();

        if (!isset($event['Type']) || !in_array($event['Type'], ['HardBounce', 'SoftBounce', 'SpamComplaint'])) {
            return response()->json(['status' => 'ignored']);
        }

        $email = $event['Email'] ?? null;
        if (!$email) {
            return response()->json(['status' => 'ignored']);
        }

        $bounceType = match ($event['Type']) {
            'HardBounce', 'SpamComplaint' => EmailBounced::TYPE_HARD,
            default => EmailBounced::TYPE_SOFT,
        };

        $this->processBounce(
            email: $email,
            bounceType: $bounceType,
            bounceReason: $event['Description'] ?? $event['Type'],
            messageId: $event['MessageID'] ?? null,
            provider: 'postmark'
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle bounce webhook from Mailgun
     * 
     * @see https://documentation.mailgun.com/en/latest/user_manual.html#webhooks
     */
    public function mailgun(Request $request)
    {
        $eventData = $request->input('event-data', []);
        $event = $eventData['event'] ?? '';

        if (!in_array($event, ['failed', 'rejected'])) {
            return response()->json(['status' => 'ignored']);
        }

        $email = $eventData['recipient'] ?? null;
        if (!$email) {
            return response()->json(['status' => 'ignored']);
        }

        $severity = $eventData['severity'] ?? 'permanent';
        $bounceType = $severity === 'permanent' 
            ? EmailBounced::TYPE_HARD 
            : EmailBounced::TYPE_SOFT;

        $reason = $eventData['delivery-status']['message'] ?? 
                  $eventData['delivery-status']['description'] ?? 
                  'Delivery failed';

        $this->processBounce(
            email: $email,
            bounceType: $bounceType,
            bounceReason: $reason,
            messageId: $eventData['message']['headers']['message-id'] ?? null,
            provider: 'mailgun'
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle generic bounce webhook (for manual testing or custom providers)
     */
    public function generic(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'bounce_type' => 'nullable|in:soft,hard',
            'bounce_reason' => 'nullable|string|max:255',
            'message_id' => 'nullable|integer',
            'subscriber_id' => 'nullable|integer',
        ]);

        $this->processBounce(
            email: $validated['email'],
            bounceType: $validated['bounce_type'] ?? EmailBounced::TYPE_HARD,
            bounceReason: $validated['bounce_reason'] ?? 'Manual bounce report',
            messageId: $validated['message_id'] ?? null,
            provider: 'generic'
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process a bounce event
     */
    protected function processBounce(
        string $email,
        string $bounceType,
        string $bounceReason,
        ?string $messageId,
        string $provider
    ): void {
        Log::info("Bounce received from {$provider}", [
            'email' => $email,
            'type' => $bounceType,
            'reason' => $bounceReason,
        ]);

        // Find subscriber by email
        $subscriber = Subscriber::where('email', $email)->first();

        if (!$subscriber) {
            Log::warning("Bounce for unknown subscriber: {$email}");
            return;
        }

        // Find message if we have an ID
        $message = null;
        if ($messageId) {
            $message = Message::find($messageId);
        }

        // Update subscriber status for hard bounces
        if ($bounceType === EmailBounced::TYPE_HARD) {
            $subscriber->update(['status' => 'bounced']);
        }

        // Dispatch event for automations
        event(new EmailBounced(
            messageId: $message?->id ?? 0,
            subscriberId: $subscriber->id,
            bounceType: $bounceType,
            bounceReason: $bounceReason
        ));
    }

    /**
     * Determine bounce type from SendGrid event
     */
    protected function determineBounceType(array $event): string
    {
        $eventType = $event['event'] ?? '';
        $bounceType = $event['type'] ?? '';

        // Hard bounces
        if ($eventType === 'bounce' && $bounceType === 'bounce') {
            return EmailBounced::TYPE_HARD;
        }

        if ($eventType === 'dropped') {
            return EmailBounced::TYPE_HARD;
        }

        // Soft bounces (deferred, temporary failures)
        return EmailBounced::TYPE_SOFT;
    }
}
