<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Events\EmailBounced;
use App\Models\Mailbox;
use App\Services\Mail\BounceProcessingService;
use App\Services\Mail\BounceWebhookAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bounce reports pushed by email providers.
 *
 * Every request is authenticated against the secrets of the mailbox in the URL
 * (/webhooks/bounce/{provider}/{mailbox}, see BounceWebhookAuthenticator) and
 * only touches that mailbox owner's subscribers. Unauthenticated requests are
 * logged and answered with 401.
 */
class BounceController extends Controller
{
    public function __construct(
        private BounceProcessingService $bounceProcessor,
        private BounceWebhookAuthenticator $authenticator
    ) {}

    /**
     * Handle bounce webhook from SendGrid (Signed Event Webhook)
     *
     * @see https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/getting-started-event-webhook-security-features
     */
    public function sendgrid(Request $request, string $mailbox)
    {
        $box = $this->authenticatedMailbox($request, 'sendgrid', $mailbox);
        if (!$box) {
            return $this->unauthorized();
        }

        // Process exactly the body that was signed.
        $events = json_decode($request->getContent(), true);
        if (!is_array($events)) {
            return response()->json(['status' => 'ignored']);
        }

        foreach ($events as $event) {
            if (!is_array($event) || !in_array($event['event'] ?? '', ['bounce', 'dropped', 'deferred'])) {
                continue;
            }

            $email = $event['email'] ?? null;
            if (!is_string($email) || $email === '') {
                continue;
            }

            $this->bounceProcessor->processBounce(
                email: $email,
                bounceType: $this->determineBounceType($event),
                bounceReason: (string) ($event['reason'] ?? $event['response'] ?? 'Unknown'),
                messageId: isset($event['message_id']) ? (string) $event['message_id'] : null,
                provider: 'sendgrid',
                userId: $box->user_id
            );
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle bounce webhook from Postmark (HTTP Basic auth or token)
     *
     * @see https://postmarkapp.com/developer/webhooks/bounce-webhook
     */
    public function postmark(Request $request, string $mailbox)
    {
        $box = $this->authenticatedMailbox($request, 'postmark', $mailbox);
        if (!$box) {
            return $this->unauthorized();
        }

        $event = $request->all();

        if (!isset($event['Type']) || !in_array($event['Type'], ['HardBounce', 'SoftBounce', 'SpamComplaint'])) {
            return response()->json(['status' => 'ignored']);
        }

        $email = $event['Email'] ?? null;
        if (!is_string($email) || $email === '') {
            return response()->json(['status' => 'ignored']);
        }

        $bounceType = match ($event['Type']) {
            'HardBounce', 'SpamComplaint' => EmailBounced::TYPE_HARD,
            default => EmailBounced::TYPE_SOFT,
        };

        $this->bounceProcessor->processBounce(
            email: $email,
            bounceType: $bounceType,
            bounceReason: (string) ($event['Description'] ?? $event['Type']),
            messageId: isset($event['MessageID']) ? (string) $event['MessageID'] : null,
            provider: 'postmark',
            userId: $box->user_id
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle bounce webhook from Mailgun (webhook signing key)
     *
     * @see https://documentation.mailgun.com/docs/mailgun/user-manual/events/webhooks/
     */
    public function mailgun(Request $request, string $mailbox)
    {
        $box = $this->authenticatedMailbox($request, 'mailgun', $mailbox);
        if (!$box) {
            return $this->unauthorized();
        }

        $eventData = $request->input('event-data', []);
        if (!is_array($eventData)) {
            return response()->json(['status' => 'ignored']);
        }

        $event = $eventData['event'] ?? '';

        if (!in_array($event, ['failed', 'rejected'])) {
            return response()->json(['status' => 'ignored']);
        }

        $email = $eventData['recipient'] ?? null;
        if (!is_string($email) || $email === '') {
            return response()->json(['status' => 'ignored']);
        }

        $severity = $eventData['severity'] ?? 'permanent';
        $bounceType = $severity === 'permanent'
            ? EmailBounced::TYPE_HARD
            : EmailBounced::TYPE_SOFT;

        $reason = $eventData['delivery-status']['message'] ??
                  $eventData['delivery-status']['description'] ??
                  'Delivery failed';

        $messageId = $eventData['message']['headers']['message-id'] ?? null;

        $this->bounceProcessor->processBounce(
            email: $email,
            bounceType: $bounceType,
            bounceReason: (string) $reason,
            messageId: is_scalar($messageId) ? (string) $messageId : null,
            provider: 'mailgun',
            userId: $box->user_id
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle generic bounce webhook (custom providers, scripts) — token required
     */
    public function generic(Request $request, string $mailbox)
    {
        $box = $this->authenticatedMailbox($request, 'generic', $mailbox);
        if (!$box) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'bounce_type' => 'nullable|in:soft,hard',
            'bounce_reason' => 'nullable|string|max:255',
            'message_id' => 'nullable|integer',
            'subscriber_id' => 'nullable|integer',
        ]);

        $this->bounceProcessor->processBounce(
            email: $validated['email'],
            bounceType: $validated['bounce_type'] ?? EmailBounced::TYPE_HARD,
            bounceReason: $validated['bounce_reason'] ?? 'Manual bounce report',
            messageId: isset($validated['message_id']) ? (string) $validated['message_id'] : null,
            provider: 'generic',
            userId: $box->user_id
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * The old URLs without a mailbox (/webhooks/bounce/{provider}) carry
     * nothing that can be checked, so they are rejected.
     */
    public function legacy(Request $request, string $provider)
    {
        $this->logRejection($request, $provider, null, 'legacy_url');

        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'This bounce webhook URL is no longer accepted. Use the mailbox URL and secret from Settings → Mailboxes → Bounce webhooks.',
        ], 401);
    }

    /**
     * The mailbox in the URL, if the request carries valid credentials for it.
     */
    private function authenticatedMailbox(Request $request, string $provider, string $mailboxId): ?Mailbox
    {
        $mailbox = ctype_digit($mailboxId) ? Mailbox::find((int) $mailboxId) : null;

        $reason = $mailbox
            ? $this->authenticator->authenticate($provider, $mailbox, $request)
            : 'unknown_mailbox';

        if ($reason !== null) {
            $this->logRejection($request, $provider, $mailboxId, $reason);
            return null;
        }

        return $mailbox;
    }

    private function logRejection(Request $request, string $provider, ?string $mailboxId, string $reason): void
    {
        Log::warning('Bounce webhook rejected', [
            'provider' => $provider,
            'mailbox_id' => $mailboxId,
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 200),
        ]);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['error' => 'Unauthorized'], 401);
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
