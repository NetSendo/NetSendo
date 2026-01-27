<?php

namespace App\Http\Controllers;

use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\EmailReadSession;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\MessageTrackedLink;
use App\Models\Subscriber;
use App\Events\EmailOpened;
use App\Events\EmailClicked;
use App\Events\ReadTimeThresholdReached;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    public function trackOpen($messageId, $subscriberId, $hash, Request $request)
    {
        if (!$this->verifyHash($messageId, $subscriberId, $hash)) {
            abort(403);
        }

        try {
            // Check if subscriber exists to prevent foreign key constraint violation
            if (!Subscriber::where('id', $subscriberId)->exists()) {
                Log::warning('Track open: Subscriber not found', [
                    'subscriber_id' => $subscriberId,
                    'message_id' => $messageId,
                ]);
                // Still return tracking pixel, just don't record the open
                return $this->returnTrackingPixel();
            }

            // Get variant ID from queue entry if this is an A/B test
            $variantId = MessageQueueEntry::where('message_id', $messageId)
                ->where('subscriber_id', $subscriberId)
                ->value('ab_test_variant_id');

            EmailOpen::create([
                'message_id' => $messageId,
                'subscriber_id' => $subscriberId,
                'ab_test_variant_id' => $variantId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'opened_at' => now(),
            ]);

            // Dispatch event for automations
            event(new EmailOpened(
                (int) $messageId,
                (int) $subscriberId,
                $request->ip(),
                $request->userAgent()
            ));
        } catch (\Exception $e) {
            Log::error('Failed to track open: ' . $e->getMessage());
        }

        return $this->returnTrackingPixel();
    }

    public function trackClick($messageId, $subscriberId, $hash, Request $request)
    {
        if (!$this->verifyHash($messageId, $subscriberId, $hash)) {
            abort(403);
        }

        $url = $request->query('url');

        if (!$url) {
            abort(404);
        }

        // Decode URL if it was encoded (it should be allowed in query params, but just in case)
        // Usually, the browser handles decoding, but if we encoded it in the email link, we get the raw URL.

        try {
            // Check if subscriber exists to prevent foreign key constraint violation
            if (!Subscriber::where('id', $subscriberId)->exists()) {
                Log::warning('Track click: Subscriber not found', [
                    'subscriber_id' => $subscriberId,
                    'message_id' => $messageId,
                    'url' => $url,
                ]);
                // Still redirect to URL, just don't record the click
                return redirect()->away($url);
            }

            // Get variant ID from queue entry if this is an A/B test
            $variantId = MessageQueueEntry::where('message_id', $messageId)
                ->where('subscriber_id', $subscriberId)
                ->value('ab_test_variant_id');

            EmailClick::create([
                'message_id' => $messageId,
                'subscriber_id' => $subscriberId,
                'ab_test_variant_id' => $variantId,
                'url' => $url,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'clicked_at' => now(),
            ]);

            // Dispatch event for automations
            event(new EmailClicked(
                (int) $messageId,
                (int) $subscriberId,
                $url,
                $request->ip(),
                $request->userAgent()
            ));
        } catch (\Exception $e) {
            Log::error('Failed to track click: ' . $e->getMessage());
        }

        // Process tracked link actions (subscribe/unsubscribe to lists, data sharing)
        $finalUrl = $this->processTrackedLinkActions($messageId, $subscriberId, $url);

        return redirect()->away($finalUrl);
    }

    /**
     * Process tracked link actions: subscribe/unsubscribe to lists and data sharing.
     */
    protected function processTrackedLinkActions(int $messageId, int $subscriberId, string $url): string
    {
        try {
            // Find the tracked link configuration for this URL
            $trackedLink = MessageTrackedLink::findByUrl($messageId, $url);

            if (!$trackedLink) {
                return $url;
            }

            $subscriber = Subscriber::find($subscriberId);
            if (!$subscriber) {
                return $url;
            }

            // Handle subscribe to lists
            if (!empty($trackedLink->subscribe_to_list_ids)) {
                foreach ($trackedLink->subscribe_to_list_ids as $listId) {
                    // Check if subscriber is already on this list
                    $existing = $subscriber->contactLists()->where('contact_lists.id', $listId)->first();

                    if (!$existing) {
                        // Add subscriber to list
                        $subscriber->contactLists()->attach($listId, [
                            'status' => 'active',
                            'subscribed_at' => now(),
                            'source' => 'tracked_link_click',
                        ]);
                        Log::info("Subscriber {$subscriberId} added to list {$listId} via tracked link click");
                    } elseif ($existing->pivot->status !== 'active') {
                        // Reactivate if previously unsubscribed
                        $subscriber->contactLists()->updateExistingPivot($listId, [
                            'status' => 'active',
                            'subscribed_at' => now(),
                        ]);
                        Log::info("Subscriber {$subscriberId} reactivated on list {$listId} via tracked link click");
                    }
                }
            }

            // Handle unsubscribe from lists
            if (!empty($trackedLink->unsubscribe_from_list_ids)) {
                foreach ($trackedLink->unsubscribe_from_list_ids as $listId) {
                    $subscriber->contactLists()->updateExistingPivot($listId, [
                        'status' => 'unsubscribed',
                        'unsubscribed_at' => now(),
                    ]);
                    Log::info("Subscriber {$subscriberId} unsubscribed from list {$listId} via tracked link click");
                }
            }

            // Handle data sharing - append subscriber data to URL
            if ($trackedLink->share_data_enabled) {
                $url = $trackedLink->buildUrlWithSharedData($subscriber);
            }

            return $url;
        } catch (\Exception $e) {
            Log::error('Failed to process tracked link actions: ' . $e->getMessage());
            return $url;
        }
    }

    /**
     * Start tracking a read session for an email.
     * Called when email is opened in browser/webmail.
     */
    public function startReadSession($messageId, $subscriberId, $hash, Request $request)
    {
        if (!$this->verifyHash($messageId, $subscriberId, $hash)) {
            return response()->json(['error' => 'Invalid hash'], 403);
        }

        try {
            $sessionId = Str::uuid()->toString();

            EmailReadSession::create([
                'message_id' => $messageId,
                'subscriber_id' => $subscriberId,
                'session_id' => $sessionId,
                'started_at' => now(),
                'is_active' => true,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start read session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to start session'], 500);
        }
    }

    /**
     * Heartbeat endpoint to keep session alive and update visibility events.
     * Called periodically by JavaScript in the email.
     */
    public function heartbeat(Request $request)
    {
        $sessionId = $request->input('session_id');
        $visibilityEvents = $request->input('visibility_events', []);

        if (!$sessionId) {
            return response()->json(['error' => 'Missing session_id'], 400);
        }

        try {
            $session = EmailReadSession::where('session_id', $sessionId)
                ->where('is_active', true)
                ->first();

            if (!$session) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            // Update visibility events if provided
            if (!empty($visibilityEvents)) {
                $existingEvents = $session->visibility_events ?? [];
                $session->visibility_events = array_merge($existingEvents, $visibilityEvents);
                $session->save();
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to process heartbeat: ' . $e->getMessage());
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    /**
     * End a read session and calculate total read time.
     * Called when user closes the email or navigates away.
     */
    public function endReadSession(Request $request)
    {
        $sessionId = $request->input('session_id');
        $readTimeSeconds = $request->input('read_time_seconds');
        $visibilityEvents = $request->input('visibility_events', []);

        if (!$sessionId) {
            return response()->json(['error' => 'Missing session_id'], 400);
        }

        try {
            $session = EmailReadSession::where('session_id', $sessionId)
                ->where('is_active', true)
                ->first();

            if (!$session) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            // Update visibility events if provided
            if (!empty($visibilityEvents)) {
                $existingEvents = $session->visibility_events ?? [];
                $session->visibility_events = array_merge($existingEvents, $visibilityEvents);
            }

            // End the session
            $session->endSession($readTimeSeconds);

            // Check if we need to dispatch threshold event
            $this->checkReadTimeThreshold($session);

            return response()->json([
                'success' => true,
                'read_time_seconds' => $session->read_time_seconds,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to end read session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    /**
     * Check if read time threshold triggers any automations.
     */
    protected function checkReadTimeThreshold(EmailReadSession $session): void
    {
        try {
            $message = Message::find($session->message_id);

            if (!$message) {
                return;
            }

            // Dispatch event for automation processing
            event(new ReadTimeThresholdReached(
                $session->message_id,
                $session->subscriber_id,
                $session->read_time_seconds ?? 0,
                $message->user_id
            ));
        } catch (\Exception $e) {
            Log::error('Failed to check read time threshold: ' . $e->getMessage());
        }
    }

    /**
     * Generate tracking hash for security.
     */
    public static function generateHash($messageId, $subscriberId): string
    {
        return hash_hmac('sha256', "{$messageId}.{$subscriberId}", config('app.key'));
    }

    private function verifyHash($messageId, $subscriberId, $hash)
    {
        $expectedHash = hash_hmac('sha256', "{$messageId}.{$subscriberId}", config('app.key'));
        return hash_equals($expectedHash, $hash);
    }

    /**
     * Return a 1x1 transparent GIF tracking pixel.
     */
    private function returnTrackingPixel()
    {
        $content = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');

        return response($content)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
