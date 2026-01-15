<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubscriberResource;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Events\SubscriberSignedUp;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use App\Services\GenderService;

class SubscriberController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher
    ) {}
    /**
     * Get all subscribers with pagination and filtering
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })->with(['tags', 'fieldValues.customField', 'contactLists']);

        // Filter by contact list
        if ($request->has('contact_list_id')) {
            $query->whereHas('contactLists', fn($q) => $q->where('contact_lists.id', $request->contact_list_id));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by email (partial match)
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        // Filter by tag
        if ($request->has('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);

        return SubscriberResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single subscriber
     */
    public function show(Request $request, int $id): SubscriberResource|JsonResponse
    {
        $subscriber = $this->findSubscriberForUser($request->user(), $id);

        if (!$subscriber) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $subscriber->load(['tags', 'fieldValues.customField']);

        return new SubscriberResource($subscriber);
    }

    /**
     * Create a new subscriber
     */
    public function store(Request $request): SubscriberResource|JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('subscribers:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have subscribers:write permission',
            ], 403);
        }

        // First validate contact_list_id to check list type
        $request->validate([
            'contact_list_id' => [
                'required',
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
        ]);

        // Get the list to determine validation rules
        $list = ContactList::find($request->contact_list_id);

        // Build validation rules based on list type
        $emailRule = $list->type === 'email' ? 'required|email' : 'nullable|email';
        $phoneRule = $list->type === 'sms' ? 'required|string|max:50' : 'nullable|string|max:50';

        // Validate request with dynamic rules
        $validated = $request->validate([
            'email' => $emailRule,
            'contact_list_id' => [
                'required',
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => $phoneRule,
            'status' => 'nullable|in:active,inactive,unsubscribed,bounced',
            'source' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:tags,id',
            'custom_fields' => 'nullable|array',
            // User data fields (for proxy scenarios like n8n)
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string|max:500',
            'device' => 'nullable|string|max:50',
        ]);

        // Check if subscriber already exists (by email OR phone for this user)
        $existing = null;
        if (!empty($validated['email'])) {
            $existing = Subscriber::withTrashed()->where('email', $validated['email'])
                ->where('user_id', $user->id)
                ->first();
        }

        // For SMS lists, also check by phone
        if (!$existing && !empty($validated['phone']) && $list->type === 'sms') {
            $existing = Subscriber::withTrashed()->where('phone', $validated['phone'])
                ->where('user_id', $user->id)
                ->first();
        }

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            // Check if already subscribed to this list
            $existingPivot = $existing->contactLists()
                ->where('contact_list_id', $validated['contact_list_id'])
                ->first();

            $wasSubscribed = $existingPivot !== null;
            $previousStatus = $existingPivot?->pivot?->status;

            if (!$wasSubscribed) {
                // Add the list to existing subscriber (new subscription)
                $existing->contactLists()->attach($validated['contact_list_id'], [
                    'status' => 'active',
                    'subscribed_at' => now(),
                    'source' => $validated['source'] ?? 'api',
                ]);

                // Dispatch subscriber.subscribed webhook
                $this->webhookDispatcher->dispatch($user->id, 'subscriber.subscribed', [
                    'subscriber' => (new SubscriberResource($existing->fresh(['tags', 'fieldValues.customField', 'contactLists'])))->toArray($request),
                    'list_id' => $list->id,
                    'list_name' => $list->name,
                ]);
            } elseif ($previousStatus !== 'active') {
                // Re-activate subscription
                $existing->contactLists()->updateExistingPivot($validated['contact_list_id'], [
                    'status' => 'active',
                    'subscribed_at' => now(),
                    'source' => $validated['source'] ?? 'api',
                ]);

                // Dispatch subscriber.resubscribed webhook
                $this->webhookDispatcher->dispatch($user->id, 'subscriber.resubscribed', [
                    'subscriber' => (new SubscriberResource($existing->fresh(['tags', 'fieldValues.customField', 'contactLists'])))->toArray($request),
                    'list_id' => $list->id,
                    'list_name' => $list->name,
                    'previous_status' => $previousStatus,
                ]);
            } else {
                // Already active on this list - dispatch update webhook if data was updated
                $this->webhookDispatcher->dispatch($user->id, 'subscriber.updated', [
                    'subscriber' => (new SubscriberResource($existing->fresh(['tags', 'fieldValues.customField', 'contactLists'])))->toArray($request),
                    'list_id' => $list->id,
                    'list_name' => $list->name,
                ]);
            }

            // Update subscriber data if provided
            $updateData = collect($validated)->only(['first_name', 'last_name', 'phone'])->filter()->toArray();
            if (!empty($updateData)) {
                $existing->update($updateData);
            }

            // Handle custom fields
            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $fieldName => $value) {
                    $existing->setCustomFieldValue($fieldName, $value);
                }
            }

            // Handle tags
            if (!empty($validated['tags'])) {
                $existing->syncTagsWithEvents($validated['tags']);
            }

            $existing->load(['tags', 'fieldValues.customField', 'contactLists']);

            return (new SubscriberResource($existing))
                ->response()
                ->setStatusCode(200);
        }

        // Create subscriber
        $subscriber = Subscriber::create([
            'user_id' => $user->id,
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active_global' => ($validated['status'] ?? 'active') === 'active',
            'source' => $validated['source'] ?? 'api',
            'ip_address' => $validated['ip_address'] ?? $request->ip(),
            'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
            'device' => $validated['device'] ?? null,
            'subscribed_at' => now(),
        ]);

        // Auto-detect gender from first name if not set
        if (empty($subscriber->gender) && !empty($subscriber->first_name)) {
            $genderService = app(GenderService::class);
            $detectedGender = $genderService->detectGender(
                $subscriber->first_name,
                'PL',
                $user->id
            );
            if ($detectedGender) {
                $subscriber->gender = $detectedGender;
                $subscriber->save();
            }
        }

        // Attach to contact list
        $subscriber->contactLists()->attach($validated['contact_list_id'], [
            'status' => $validated['status'] ?? 'active',
            'subscribed_at' => now(),
        ]);

        // Handle tags
        if (!empty($validated['tags'])) {
            $subscriber->syncTagsWithEvents($validated['tags']);
        }

        // Handle custom fields
        if (!empty($validated['custom_fields'])) {
            foreach ($validated['custom_fields'] as $fieldName => $value) {
                $subscriber->setCustomFieldValue($fieldName, $value);
            }
        }

        // Dispatch event for automations (use first list for the event)
        $contactList = $subscriber->contactLists()->first();
        event(new SubscriberSignedUp($subscriber, $contactList, null, 'api'));

        // Dispatch webhook - subscriber.created
        $this->webhookDispatcher->dispatch($user->id, 'subscriber.created', [
            'subscriber' => (new SubscriberResource($subscriber))->toArray($request),
            'list_id' => $contactList?->id,
            'list_name' => $contactList?->name,
        ]);

        // Dispatch webhook - subscriber.subscribed (for new subscription to list)
        $this->webhookDispatcher->dispatch($user->id, 'subscriber.subscribed', [
            'subscriber' => (new SubscriberResource($subscriber))->toArray($request),
            'list_id' => $contactList?->id,
            'list_name' => $contactList?->name,
        ]);

        $subscriber->load(['tags', 'fieldValues.customField', 'contactLists']);

        return (new SubscriberResource($subscriber))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a subscriber
     */
    public function update(Request $request, int $id): SubscriberResource|JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('subscribers:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have subscribers:write permission',
            ], 403);
        }

        $subscriber = $this->findSubscriberForUser($user, $id);

        if (!$subscriber) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $validated = $request->validate([
            'email' => 'sometimes|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,unsubscribed,bounced',
            'source' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
        ]);

        // Check email uniqueness if changing (within user's subscribers)
        if (isset($validated['email']) && $validated['email'] !== $subscriber->email) {
            $existing = Subscriber::where('email', $validated['email'])
                ->where('user_id', $user->id)
                ->where('id', '!=', $subscriber->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'Conflict',
                    'message' => 'Another subscriber with this email already exists',
                ], 409);
            }
        }

        // Update subscriber
        $subscriber->update(collect($validated)->except('custom_fields')->toArray());

        // Handle custom fields
        if (!empty($validated['custom_fields'])) {
            foreach ($validated['custom_fields'] as $fieldName => $value) {
                $subscriber->setCustomFieldValue($fieldName, $value);
            }
        }

        $subscriber->load(['tags', 'fieldValues.customField', 'contactLists']);

        // Dispatch webhook
        $this->webhookDispatcher->dispatch($user->id, 'subscriber.updated', [
            'subscriber' => (new SubscriberResource($subscriber))->toArray($request),
        ]);

        return new SubscriberResource($subscriber);
    }

    /**
     * Delete a subscriber (soft delete)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('subscribers:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have subscribers:write permission',
            ], 403);
        }

        $subscriber = $this->findSubscriberForUser($user, $id);

        if (!$subscriber) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        // Dispatch webhook before deleting
        $this->webhookDispatcher->dispatch($user->id, 'subscriber.deleted', [
            'subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
        ]);

        $subscriber->delete();

        return response()->json([
            'message' => 'Subscriber deleted successfully',
        ]);
    }

    /**
     * Find subscriber by email
     */
    public function findByEmail(Request $request, string $email): SubscriberResource|JsonResponse
    {
        $user = $request->user();

        $subscriber = Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })
            ->where('email', $email)
            ->with(['tags', 'fieldValues.customField', 'contactLists'])
            ->first();

        if (!$subscriber) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        return new SubscriberResource($subscriber);
    }

    /**
     * Sync tags for a subscriber
     */
    public function syncTags(Request $request, int $id): SubscriberResource|JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('subscribers:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have subscribers:write permission',
            ], 403);
        }

        $subscriber = $this->findSubscriberForUser($user, $id);

        if (!$subscriber) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $validated = $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'integer|exists:tags,id',
        ]);

        // Verify tags belong to user
        $userTagIds = Tag::where('user_id', $user->id)->pluck('id')->toArray();
        $requestedTagIds = $validated['tags'];
        $invalidTags = array_diff($requestedTagIds, $userTagIds);

        if (!empty($invalidTags)) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'Some tags do not belong to you',
                'invalid_tag_ids' => array_values($invalidTags),
            ], 422);
        }

        $subscriber->syncTagsWithEvents($requestedTagIds);
        $subscriber->load(['tags', 'fieldValues.customField', 'contactLists']);

        return new SubscriberResource($subscriber);
    }

    /**
     * Create multiple subscribers in batch
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify permission
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('subscribers:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have subscribers:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'subscribers' => 'required|array|min:1|max:1000',
            'subscribers.*.email' => 'nullable|email',
            'subscribers.*.phone' => 'nullable|string|max:50',
            'subscribers.*.contact_list_id' => [
                'required',
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'subscribers.*.first_name' => 'nullable|string|max:255',
            'subscribers.*.last_name' => 'nullable|string|max:255',
            'subscribers.*.status' => 'nullable|in:active,inactive',
            'subscribers.*.source' => 'nullable|string|max:255',
            'subscribers.*.tags' => 'nullable|array',
            'subscribers.*.tags.*' => 'integer',
            'subscribers.*.custom_fields' => 'nullable|array',
            // User data fields (for proxy scenarios like n8n)
            'subscribers.*.ip_address' => 'nullable|ip',
            'subscribers.*.user_agent' => 'nullable|string|max:500',
            'subscribers.*.device' => 'nullable|string|max:50',
        ]);

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Pre-load lists for validation
        $listIds = collect($validated['subscribers'])->pluck('contact_list_id')->unique();
        $lists = ContactList::whereIn('id', $listIds)->get()->keyBy('id');

        foreach ($validated['subscribers'] as $index => $subData) {
            try {
                $list = $lists->get($subData['contact_list_id']);

                if (!$list) {
                    $results['errors'][] = [
                        'index' => $index,
                        'error' => 'Contact list not found',
                    ];
                    continue;
                }

                // Validate required fields based on list type
                if ($list->type === 'email' && empty($subData['email'])) {
                    $results['errors'][] = [
                        'index' => $index,
                        'error' => 'Email is required for email lists',
                    ];
                    continue;
                }
                if ($list->type === 'sms' && empty($subData['phone'])) {
                    $results['errors'][] = [
                        'index' => $index,
                        'error' => 'Phone is required for SMS lists',
                    ];
                    continue;
                }

                // Find existing subscriber
                $existing = null;
                if (!empty($subData['email'])) {
                    $existing = Subscriber::withTrashed()->where('email', $subData['email'])
                        ->where('user_id', $user->id)
                        ->first();
                }
                if (!$existing && !empty($subData['phone']) && $list->type === 'sms') {
                    $existing = Subscriber::withTrashed()->where('phone', $subData['phone'])
                        ->where('user_id', $user->id)
                        ->first();
                }

                $isNew = !$existing;
                $wasSubscribed = false;
                $previousStatus = null;

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $subscriber = $existing;
                    $existingPivot = $subscriber->contactLists()
                        ->where('contact_list_id', $subData['contact_list_id'])
                        ->first();

                    $wasSubscribed = $existingPivot !== null;
                    $previousStatus = $existingPivot?->pivot?->status;

                    if ($wasSubscribed && $previousStatus === 'active') {
                        // Already active on this list, skip
                        $results['skipped']++;
                        continue;
                    }

                    // Update subscriber data if provided
                    $updateData = collect($subData)->only(['first_name', 'last_name', 'phone'])->filter()->toArray();
                    if (!empty($updateData)) {
                        $subscriber->update($updateData);
                    }

                    $results['updated']++;
                } else {
                    $subscriber = Subscriber::create([
                        'user_id' => $user->id,
                        'email' => $subData['email'] ?? null,
                        'first_name' => $subData['first_name'] ?? null,
                        'last_name' => $subData['last_name'] ?? null,
                        'phone' => $subData['phone'] ?? null,
                        'is_active_global' => ($subData['status'] ?? 'active') === 'active',
                        'source' => $subData['source'] ?? 'api_batch',
                        'ip_address' => $subData['ip_address'] ?? $request->ip(),
                        'user_agent' => $subData['user_agent'] ?? $request->userAgent(),
                        'device' => $subData['device'] ?? null,
                        'subscribed_at' => now(),
                    ]);

                    // Auto-detect gender from first name if not set
                    if (empty($subscriber->gender) && !empty($subscriber->first_name)) {
                        $genderService = app(GenderService::class);
                        $detectedGender = $genderService->detectGender(
                            $subscriber->first_name,
                            'PL',
                            $user->id
                        );
                        if ($detectedGender) {
                            $subscriber->gender = $detectedGender;
                            $subscriber->save();
                        }
                    }

                    $results['created']++;
                }

                // Attach to contact list
                $subscriber->contactLists()->syncWithoutDetaching([
                    $subData['contact_list_id'] => [
                        'status' => $subData['status'] ?? 'active',
                        'subscribed_at' => now(),
                    ]
                ]);

                // Handle tags
                if (!empty($subData['tags'])) {
                    // Verify tags belong to user
                    $userTagIds = Tag::where('user_id', $user->id)->pluck('id')->toArray();
                    $validTags = array_intersect($subData['tags'], $userTagIds);
                    if (!empty($validTags)) {
                        $subscriber->syncTagsWithEvents($validTags);
                    }
                }

                // Handle custom fields
                if (!empty($subData['custom_fields'])) {
                    foreach ($subData['custom_fields'] as $fieldName => $value) {
                        $subscriber->setCustomFieldValue($fieldName, $value);
                    }
                }

                // Prepare subscriber data for webhooks
                $subscriberData = (new SubscriberResource($subscriber->fresh(['tags', 'fieldValues.customField', 'contactLists'])))->toArray($request);

                // Dispatch webhooks (async via queue)
                if ($isNew) {
                    event(new SubscriberSignedUp($subscriber, $list, null, 'api_batch'));

                    $this->webhookDispatcher->dispatch($user->id, 'subscriber.created', [
                        'subscriber' => $subscriberData,
                        'list_id' => $list->id,
                        'list_name' => $list->name,
                        'source' => 'api_batch',
                    ]);

                    // New subscriber = new subscription
                    $this->webhookDispatcher->dispatch($user->id, 'subscriber.subscribed', [
                        'subscriber' => $subscriberData,
                        'list_id' => $list->id,
                        'list_name' => $list->name,
                        'source' => 'api_batch',
                    ]);
                } elseif (!$wasSubscribed) {
                    // Existing subscriber, new list subscription
                    $this->webhookDispatcher->dispatch($user->id, 'subscriber.subscribed', [
                        'subscriber' => $subscriberData,
                        'list_id' => $list->id,
                        'list_name' => $list->name,
                        'source' => 'api_batch',
                    ]);
                } elseif ($wasSubscribed && $previousStatus !== 'active') {
                    // Resubscription (was inactive/unsubscribed)
                    $this->webhookDispatcher->dispatch($user->id, 'subscriber.resubscribed', [
                        'subscriber' => $subscriberData,
                        'list_id' => $list->id,
                        'list_name' => $list->name,
                        'previous_status' => $previousStatus,
                        'source' => 'api_batch',
                    ]);
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'email' => $subData['email'] ?? null,
                    'phone' => $subData['phone'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => $results,
            'message' => sprintf(
                'Batch completed: %d created, %d updated, %d skipped, %d errors',
                $results['created'],
                $results['updated'],
                $results['skipped'],
                count($results['errors'])
            ),
        ], 200);
    }

    /**
     * Find subscriber by ID ensuring it belongs to user's lists
     */
    protected function findSubscriberForUser($user, int $id): ?Subscriber
    {
        return Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })->find($id);
    }
}

