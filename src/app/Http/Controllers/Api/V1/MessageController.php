<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API Controller for managing email/SMS campaigns (Messages)
 *
 * Endpoints:
 * - GET    /api/v1/messages              - List all campaigns
 * - GET    /api/v1/messages/{id}         - Get campaign details
 * - POST   /api/v1/messages              - Create new campaign
 * - PUT    /api/v1/messages/{id}         - Update campaign
 * - DELETE /api/v1/messages/{id}         - Delete campaign
 * - POST   /api/v1/messages/{id}/lists   - Set recipient lists
 * - POST   /api/v1/messages/{id}/exclusions - Set exclusion lists
 * - POST   /api/v1/messages/{id}/schedule - Schedule sending
 * - POST   /api/v1/messages/{id}/send    - Send campaign
 * - GET    /api/v1/messages/{id}/stats   - Get sending statistics
 */
class MessageController extends Controller
{
    /**
     * List all campaigns with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Message::where('user_id', $user->id)
            ->with(['mailbox', 'contactLists', 'excludedLists']);

        // Filter by channel (email/sms)
        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        // Filter by type (broadcast/autoresponder)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by subject
        if ($request->has('search')) {
            $query->where('subject', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);
        $messages = $query->paginate($perPage);

        return response()->json([
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
            'links' => [
                'first' => $messages->url(1),
                'last' => $messages->url($messages->lastPage()),
                'prev' => $messages->previousPageUrl(),
                'next' => $messages->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get a single campaign's details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $message = Message::where('user_id', $user->id)
            ->with(['mailbox', 'contactLists', 'excludedLists', 'template', 'abTest.variants'])
            ->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        return response()->json([
            'data' => $message,
        ]);
    }

    /**
     * Create a new campaign
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'channel' => 'required|in:email,sms',
            'type' => 'required|in:broadcast,autoresponder',
            'content' => 'nullable|string',
            'preheader' => 'nullable|string|max:255',
            'mailbox_id' => [
                'nullable',
                'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('templates', 'id')->where('user_id', $user->id),
            ],
            'day' => 'nullable|integer|min:0', // For autoresponders
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|timezone',
            'contact_list_ids' => 'nullable|array',
            'contact_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'excluded_list_ids' => 'nullable|array',
            'excluded_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
        ]);

        // Create message
        $message = Message::create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'channel' => $validated['channel'],
            'type' => $validated['type'],
            'content' => $validated['content'] ?? '',
            'preheader' => $validated['preheader'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'day' => $validated['day'] ?? 0,
            'time_of_day' => $validated['time_of_day'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'status' => 'draft',
            'is_active' => false,
        ]);

        // Attach contact lists
        if (!empty($validated['contact_list_ids'])) {
            $message->contactLists()->attach($validated['contact_list_ids']);
        }

        // Attach excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $message->excludedLists()->attach($validated['excluded_list_ids']);
        }

        $message->load(['mailbox', 'contactLists', 'excludedLists']);

        return response()->json([
            'data' => $message,
            'message' => 'Campaign created successfully',
        ], 201);
    }

    /**
     * Update an existing campaign
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Prevent editing sent campaigns
        if (in_array($message->status, ['sending', 'sent'])) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Cannot edit a campaign that is sending or already sent',
            ], 409);
        }

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'preheader' => 'nullable|string|max:255',
            'mailbox_id' => [
                'nullable',
                'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('templates', 'id')->where('user_id', $user->id),
            ],
            'day' => 'nullable|integer|min:0',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|timezone',
            'is_active' => 'sometimes|boolean',
        ]);

        $message->update($validated);
        $message->load(['mailbox', 'contactLists', 'excludedLists']);

        return response()->json([
            'data' => $message,
            'message' => 'Campaign updated successfully',
        ]);
    }

    /**
     * Delete a campaign
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Prevent deleting sent campaigns
        if ($message->status === 'sent') {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Cannot delete a sent campaign',
            ], 409);
        }

        $message->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * Set recipient lists for a campaign
     */
    public function setLists(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        $validated = $request->validate([
            'contact_list_ids' => 'required|array',
            'contact_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
        ]);

        $message->contactLists()->sync($validated['contact_list_ids']);
        $message->load(['contactLists']);

        // Update planned recipients count
        $message->update([
            'planned_recipients_count' => $message->getUniqueRecipients()->count(),
            'recipients_calculated_at' => now(),
        ]);

        return response()->json([
            'data' => $message,
            'message' => 'Recipient lists updated',
            'planned_recipients' => $message->planned_recipients_count,
        ]);
    }

    /**
     * Set exclusion lists for a campaign
     */
    public function setExclusions(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        $validated = $request->validate([
            'excluded_list_ids' => 'required|array',
            'excluded_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
        ]);

        $message->excludedLists()->sync($validated['excluded_list_ids']);
        $message->load(['excludedLists']);

        // Update planned recipients count (exclusions affect count)
        $message->update([
            'planned_recipients_count' => $message->getUniqueRecipients()->count(),
            'recipients_calculated_at' => now(),
        ]);

        return response()->json([
            'data' => $message,
            'message' => 'Exclusion lists updated',
            'planned_recipients' => $message->planned_recipients_count,
        ]);
    }

    /**
     * Schedule a campaign for sending
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Validate campaign is ready
        if ($message->contactLists->isEmpty()) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Campaign must have at least one recipient list',
            ], 422);
        }

        if ($message->channel === 'email' && !$message->getEffectiveMailbox()) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Campaign must have a mailbox configured',
            ], 422);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'timezone' => 'nullable|timezone',
        ]);

        // Determine timezone: request → message → user → UTC
        $timezone = $validated['timezone']
            ?? $message->effective_timezone
            ?? $user->timezone
            ?? 'UTC';

        // Convert user's local time to UTC for storage
        $scheduledAtUtc = \Carbon\Carbon::parse($validated['scheduled_at'], $timezone)
            ->setTimezone('UTC');

        $message->update([
            'scheduled_at' => $scheduledAtUtc,
            'status' => 'scheduled',
        ]);

        // Sync planned recipients
        $message->syncPlannedRecipients();

        return response()->json([
            'data' => $message->fresh(['mailbox', 'contactLists', 'excludedLists']),
            'message' => 'Campaign scheduled successfully',
            'scheduled_at' => $message->scheduled_at->toIso8601String(),
            'timezone_used' => $timezone,
        ]);
    }

    /**
     * Send a campaign immediately (or activate autoresponder)
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('messages:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have messages:write permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Validate campaign is ready
        if ($message->contactLists->isEmpty()) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Campaign must have at least one recipient list',
            ], 422);
        }

        if ($message->channel === 'email' && !$message->getEffectiveMailbox()) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Campaign must have a mailbox configured',
            ], 422);
        }

        if (empty($message->content)) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Campaign must have content',
            ], 422);
        }

        if ($message->isQueueType()) {
            // Activate autoresponder
            $message->update([
                'is_active' => true,
                'status' => 'active',
            ]);

            return response()->json([
                'data' => $message->fresh(['mailbox', 'contactLists', 'excludedLists']),
                'message' => 'Autoresponder activated',
            ]);
        }

        // For broadcasts, queue for immediate sending
        $message->update([
            'scheduled_at' => now(),
            'status' => 'sending',
        ]);

        // Sync planned recipients
        $syncResult = $message->syncPlannedRecipients();

        return response()->json([
            'data' => $message->fresh(['mailbox', 'contactLists', 'excludedLists']),
            'message' => 'Campaign queued for sending',
            'recipients_added' => $syncResult['added'],
        ]);
    }

    /**
     * Get campaign statistics
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $message = Message::where('user_id', $user->id)->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        $queueStats = $message->getQueueStats();

        // Calculate sent count for open/click rate calculations
        $totalSent = $queueStats['sent'] > 0 ? $queueStats['sent'] : ($message->sent_count ?: 1);

        // Get opens and clicks from tracking tables
        $opens = \App\Models\EmailOpen::where('message_id', $message->id)->count();
        $uniqueOpens = \App\Models\EmailOpen::where('message_id', $message->id)->distinct('subscriber_id')->count('subscriber_id');
        $clicks = \App\Models\EmailClick::where('message_id', $message->id)->count();
        $uniqueClicks = \App\Models\EmailClick::where('message_id', $message->id)->distinct('subscriber_id')->count('subscriber_id');

        $response = [
            'id' => $message->id,
            'subject' => $message->subject,
            'status' => $message->status,
            'type' => $message->type,
            'sent_count' => $message->sent_count,
            'planned_recipients_count' => $message->planned_recipients_count,
            'queue_stats' => $queueStats,
            // Open/Click statistics
            'opens' => $opens,
            'unique_opens' => $uniqueOpens,
            'open_rate' => round(($uniqueOpens / $totalSent) * 100, 1),
            'clicks' => $clicks,
            'unique_clicks' => $uniqueClicks,
            'click_rate' => round(($uniqueClicks / $totalSent) * 100, 1),
            'click_to_open_rate' => $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 1) : 0,
        ];

        // Add schedule stats for autoresponders
        if ($message->isQueueType()) {
            $response['schedule_stats'] = $message->getQueueScheduleStats();
        }

        return response()->json([
            'data' => $response,
        ]);
    }
}
