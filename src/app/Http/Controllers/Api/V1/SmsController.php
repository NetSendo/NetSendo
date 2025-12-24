<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\SmsProvider;
use App\Jobs\SendSmsJob;
use App\Services\WebhookDispatcher;
use App\Services\Sms\SmsProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SmsController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected SmsProviderService $smsProviderService
    ) {}

    /**
     * Send a single SMS message
     *
     * @group SMS
     * @authenticated
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('sms:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have sms:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:50',
            'message' => 'required|string|max:1600',
            'provider_id' => 'nullable|integer|exists:sms_providers,id',
            'subscriber_id' => 'nullable|integer',
            'schedule_at' => 'nullable|date|after:now',
        ]);

        // Get SMS provider
        $provider = null;
        if (!empty($validated['provider_id'])) {
            $provider = SmsProvider::forUser($user->id)->find($validated['provider_id']);
            if (!$provider) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'SMS provider not found',
                ], 404);
            }
        } else {
            $provider = $this->smsProviderService->getBestProvider($user->id);
        }

        if (!$provider) {
            return response()->json([
                'error' => 'No Provider',
                'message' => 'No active SMS provider available. Please configure an SMS provider first.',
            ], 422);
        }

        // Find or create a temporary subscriber for the phone number
        $subscriber = null;
        if (!empty($validated['subscriber_id'])) {
            $subscriber = Subscriber::where('user_id', $user->id)->find($validated['subscriber_id']);
        }

        if (!$subscriber) {
            // Create temporary subscriber
            $subscriber = Subscriber::firstOrCreate(
                [
                    'phone' => $validated['phone'],
                    'user_id' => $user->id,
                ],
                [
                    'email' => null,
                    'source' => 'api_sms',
                    'is_active_global' => true,
                ]
            );
        }

        // Create message record
        $message = Message::create([
            'user_id' => $user->id,
            'type' => 'sms',
            'subject' => 'API SMS',
            'content' => $validated['message'],
            'status' => 'queued',
        ]);

        // Dispatch webhook for queued
        $this->webhookDispatcher->dispatch($user->id, 'sms.queued', [
            'message_id' => $message->id,
            'phone' => $validated['phone'],
            'content' => $validated['message'],
            'provider' => $provider->name,
            'scheduled_at' => $validated['schedule_at'] ?? null,
        ]);

        // Schedule or send immediately
        $delay = !empty($validated['schedule_at'])
            ? now()->diffInSeconds($validated['schedule_at'])
            : 0;

        SendSmsJob::dispatch($message, $subscriber, $provider)
            ->delay($delay > 0 ? now()->addSeconds($delay) : null);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'phone' => $validated['phone'],
                'status' => 'queued',
                'provider' => $provider->name,
                'scheduled_at' => $validated['schedule_at'] ?? null,
            ],
            'message' => 'SMS queued successfully',
        ], 202);
    }

    /**
     * Send batch SMS to list or subscribers
     *
     * @group SMS
     * @authenticated
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('sms:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have sms:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:1600',
            'list_id' => 'nullable|integer',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer',
            'subscriber_ids' => 'nullable|array',
            'subscriber_ids.*' => 'integer',
            'provider_id' => 'nullable|integer|exists:sms_providers,id',
        ]);

        // Must provide at least one targeting option
        if (empty($validated['list_id']) && empty($validated['tag_ids']) && empty($validated['subscriber_ids'])) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'You must provide list_id, tag_ids, or subscriber_ids',
            ], 422);
        }

        // Get SMS provider
        $provider = null;
        if (!empty($validated['provider_id'])) {
            $provider = SmsProvider::forUser($user->id)->find($validated['provider_id']);
        } else {
            $provider = $this->smsProviderService->getBestProvider($user->id);
        }

        if (!$provider) {
            return response()->json([
                'error' => 'No Provider',
                'message' => 'No active SMS provider available.',
            ], 422);
        }

        // Build subscriber query
        $subscriberQuery = Subscriber::where('user_id', $user->id)
            ->whereNotNull('phone')
            ->where('is_active_global', true);

        if (!empty($validated['list_id'])) {
            $list = ContactList::forUser($user->id)->sms()->find($validated['list_id']);
            if (!$list) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'SMS list not found',
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

        $subscribers = $subscriberQuery->get();

        if ($subscribers->isEmpty()) {
            return response()->json([
                'error' => 'No Recipients',
                'message' => 'No subscribers found matching the criteria with valid phone numbers.',
            ], 422);
        }

        // Create message record
        $message = Message::create([
            'user_id' => $user->id,
            'type' => 'sms',
            'subject' => 'API Batch SMS',
            'content' => $validated['message'],
            'status' => 'queued',
        ]);

        // Queue SMS for each subscriber
        $queuedCount = 0;
        foreach ($subscribers as $subscriber) {
            SendSmsJob::dispatch($message, $subscriber, $provider);
            $queuedCount++;
        }

        // Dispatch webhook
        $this->webhookDispatcher->dispatch($user->id, 'sms.queued', [
            'message_id' => $message->id,
            'type' => 'batch',
            'recipients_count' => $queuedCount,
            'provider' => $provider->name,
        ]);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'queued_count' => $queuedCount,
                'status' => 'queued',
                'provider' => $provider->name,
            ],
            'message' => "Batch SMS queued for {$queuedCount} recipients",
        ], 202);
    }

    /**
     * Get SMS message status
     *
     * @group SMS
     * @authenticated
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('sms:read')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have sms:read permission',
            ], 403);
        }

        $message = Message::where('user_id', $user->id)
            ->where('type', 'sms')
            ->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'SMS message not found',
            ], 404);
        }

        // Get queue entries if available
        $queueStats = $message->queueEntries()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'data' => [
                'id' => $message->id,
                'status' => $message->status,
                'content' => $message->content,
                'created_at' => $message->created_at->toISOString(),
                'stats' => [
                    'pending' => $queueStats['pending'] ?? 0,
                    'sent' => $queueStats['sent'] ?? 0,
                    'failed' => $queueStats['failed'] ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Get available SMS providers for the user
     *
     * @group SMS
     * @authenticated
     */
    public function providers(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('sms:read')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have sms:read permission',
            ], 403);
        }

        $providers = SmsProvider::forUser($user->id)
            ->active()
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'provider' => $p->provider,
                'is_default' => $p->is_default,
                'from_number' => $p->from_number,
                'from_name' => $p->from_name,
                'daily_limit' => $p->daily_limit,
                'sent_today' => $p->sent_today,
            ]);

        return response()->json([
            'data' => $providers,
        ]);
    }
}
