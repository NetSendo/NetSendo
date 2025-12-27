<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Services\Mail\MailProviderService;
use App\Services\PlaceholderService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * Send a single email to a subscriber
     *
     * @group Email
     * @authenticated
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('email:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have email:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'preheader' => 'nullable|string|max:500',
            'mailbox_id' => 'nullable|integer|exists:mailboxes,id',
            'subscriber_id' => 'nullable|integer',
            'schedule_at' => 'nullable|date|after:now',
        ]);

        // Get mailbox
        $mailbox = null;
        if (!empty($validated['mailbox_id'])) {
            $mailbox = Mailbox::where('user_id', $user->id)
                ->active()
                ->find($validated['mailbox_id']);
            if (!$mailbox) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Mailbox not found or inactive',
                ], 404);
            }
        } else {
            $mailbox = Mailbox::getDefaultFor($user->id);
        }

        if (!$mailbox) {
            return response()->json([
                'error' => 'No Mailbox',
                'message' => 'No active mailbox available. Please configure a mailbox first.',
            ], 422);
        }

        // Find or create subscriber
        $subscriber = null;
        if (!empty($validated['subscriber_id'])) {
            $subscriber = Subscriber::where('user_id', $user->id)
                ->find($validated['subscriber_id']);
        }

        if (!$subscriber) {
            $subscriber = Subscriber::firstOrCreate(
                [
                    'email' => $validated['email'],
                    'user_id' => $user->id,
                ],
                [
                    'source' => 'api_email',
                    'is_active_global' => true,
                ]
            );
        }

        // Calculate scheduled_at
        $scheduledAt = !empty($validated['schedule_at'])
            ? \Carbon\Carbon::parse($validated['schedule_at'])->setTimezone('UTC')
            : now();

        // Create message
        $message = Message::create([
            'user_id' => $user->id,
            'type' => 'broadcast',
            'channel' => 'email',
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'preheader' => $validated['preheader'] ?? null,
            'mailbox_id' => $mailbox->id,
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        // Create queue entry for single recipient
        $message->queueEntries()->create([
            'subscriber_id' => $subscriber->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
            'planned_at' => $scheduledAt,
        ]);

        $message->update(['planned_recipients_count' => 1]);

        // Dispatch webhook
        $this->webhookDispatcher->dispatch($user->id, 'email.queued', [
            'message_id' => $message->id,
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'mailbox' => $mailbox->name,
            'scheduled_at' => $validated['schedule_at'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'status' => 'queued',
                'mailbox' => $mailbox->name,
                'scheduled_at' => $validated['schedule_at'] ?? null,
            ],
            'message' => 'Email queued successfully',
        ], 202);
    }

    /**
     * Send batch email to list or subscribers
     *
     * @group Email
     * @authenticated
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('email:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have email:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'preheader' => 'nullable|string|max:500',
            'list_id' => 'nullable|integer',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer',
            'subscriber_ids' => 'nullable|array',
            'subscriber_ids.*' => 'integer',
            'mailbox_id' => 'nullable|integer|exists:mailboxes,id',
            'schedule_at' => 'nullable|date|after:now',
            'excluded_list_ids' => 'nullable|array',
            'excluded_list_ids.*' => 'integer',
        ]);

        // Must provide at least one targeting option
        if (empty($validated['list_id']) && empty($validated['tag_ids']) && empty($validated['subscriber_ids'])) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'You must provide list_id, tag_ids, or subscriber_ids',
            ], 422);
        }

        // Get mailbox
        $mailbox = null;
        if (!empty($validated['mailbox_id'])) {
            $mailbox = Mailbox::where('user_id', $user->id)->active()->find($validated['mailbox_id']);
        } else {
            $mailbox = Mailbox::getDefaultFor($user->id);
        }

        if (!$mailbox) {
            return response()->json([
                'error' => 'No Mailbox',
                'message' => 'No active mailbox available.',
            ], 422);
        }

        // Build subscriber query - only email lists
        $subscriberQuery = Subscriber::where('user_id', $user->id)
            ->whereNotNull('email')
            ->where('is_active_global', true);

        if (!empty($validated['list_id'])) {
            $list = ContactList::forUser($user->id)->email()->find($validated['list_id']);
            if (!$list) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Email list not found',
                ], 404);
            }
            $subscriberQuery->whereHas('contactLists', fn($q) => $q->where('contact_lists.id', $list->id));
        }

        if (!empty($validated['tag_ids'])) {
            $subscriberQuery->whereHas('tags', fn($q) => $q->whereIn('tags.id', $validated['tag_ids']));
        }

        if (!empty($validated['subscriber_ids'])) {
            $subscriberQuery->whereIn('id', $validated['subscriber_ids']);
        }

        // Exclude subscribers from excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $subscriberQuery->whereDoesntHave('contactLists', fn($q) =>
                $q->whereIn('contact_lists.id', $validated['excluded_list_ids'])
            );
        }

        $subscribers = $subscriberQuery->get();

        if ($subscribers->isEmpty()) {
            return response()->json([
                'error' => 'No Recipients',
                'message' => 'No subscribers found matching the criteria with valid email addresses.',
            ], 422);
        }

        // Calculate scheduled_at
        $scheduledAt = !empty($validated['schedule_at'])
            ? \Carbon\Carbon::parse($validated['schedule_at'])->setTimezone('UTC')
            : now();

        // Create message
        $message = Message::create([
            'user_id' => $user->id,
            'type' => 'broadcast',
            'channel' => 'email',
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'preheader' => $validated['preheader'] ?? null,
            'mailbox_id' => $mailbox->id,
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        // Attach list if provided
        if (!empty($validated['list_id'])) {
            $message->contactLists()->attach($validated['list_id']);
        }

        // Create queue entries
        $queuedCount = 0;
        foreach ($subscribers as $subscriber) {
            $message->queueEntries()->create([
                'subscriber_id' => $subscriber->id,
                'status' => MessageQueueEntry::STATUS_PLANNED,
                'planned_at' => $scheduledAt,
            ]);
            $queuedCount++;
        }

        $message->update(['planned_recipients_count' => $queuedCount]);

        // Dispatch webhook
        $this->webhookDispatcher->dispatch($user->id, 'email.queued', [
            'message_id' => $message->id,
            'type' => 'batch',
            'recipients_count' => $queuedCount,
            'subject' => $validated['subject'],
            'mailbox' => $mailbox->name,
            'scheduled_at' => $validated['schedule_at'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'queued_count' => $queuedCount,
                'subject' => $validated['subject'],
                'status' => 'queued',
                'mailbox' => $mailbox->name,
                'scheduled_at' => $validated['schedule_at'] ?? null,
            ],
            'message' => "Batch email queued for {$queuedCount} recipients",
        ], 202);
    }

    /**
     * Get email message status
     *
     * @group Email
     * @authenticated
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('email:read')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have email:read permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)
            ->where('channel', 'email')
            ->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Email message not found',
            ], 404);
        }

        $queueStats = $message->getQueueStats();

        return response()->json([
            'data' => [
                'id' => $message->id,
                'subject' => $message->subject,
                'status' => $message->status,
                'scheduled_at' => $message->scheduled_at?->toISOString(),
                'created_at' => $message->created_at->toISOString(),
                'stats' => [
                    'planned' => $queueStats['planned'] ?? 0,
                    'queued' => $queueStats['queued'] ?? 0,
                    'sent' => $queueStats['sent'] ?? 0,
                    'failed' => $queueStats['failed'] ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Get available mailboxes for the user
     *
     * @group Email
     * @authenticated
     */
    public function mailboxes(Request $request): JsonResponse
    {
        $user = $request->user();

        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('email:read')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have email:read permission',
            ], 403);
        }

        $mailboxes = Mailbox::where('user_id', $user->id)
            ->active()
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'provider' => $m->provider,
                'from_email' => $m->from_email,
                'from_name' => $m->from_name,
                'is_default' => $m->is_default,
            ]);

        return response()->json([
            'data' => $mailboxes,
        ]);
    }
}
