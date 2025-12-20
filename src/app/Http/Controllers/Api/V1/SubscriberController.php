<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubscriberResource;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Events\SubscriberSignedUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SubscriberController extends Controller
{
    /**
     * Get all subscribers with pagination and filtering
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Subscriber::whereHas('contactList', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['tags', 'fieldValues.customField']);

        // Filter by contact list
        if ($request->has('contact_list_id')) {
            $query->where('contact_list_id', $request->contact_list_id);
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

        $perPage = min($request->get('per_page', 25), 100);

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

        // Validate request
        $validated = $request->validate([
            'email' => 'required|email',
            'contact_list_id' => [
                'required',
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,unsubscribed,bounced',
            'source' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:tags,id',
            'custom_fields' => 'nullable|array',
        ]);

        // Check if subscriber already exists in this list
        $existing = Subscriber::where('email', $validated['email'])
            ->where('contact_list_id', $validated['contact_list_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Subscriber with this email already exists in this list',
                'subscriber_id' => $existing->id,
            ], 409);
        }

        // Create subscriber
        $subscriber = Subscriber::create([
            'email' => $validated['email'],
            'contact_list_id' => $validated['contact_list_id'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'source' => $validated['source'] ?? 'api',
            'ip_address' => $request->ip(),
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

        // Dispatch event for automations
        event(new SubscriberSignedUp($subscriber, $subscriber->contactList, null, 'api'));

        $subscriber->load(['tags', 'fieldValues.customField']);

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

        // Check email uniqueness if changing
        if (isset($validated['email']) && $validated['email'] !== $subscriber->email) {
            $existing = Subscriber::where('email', $validated['email'])
                ->where('contact_list_id', $subscriber->contact_list_id)
                ->where('id', '!=', $subscriber->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'Conflict',
                    'message' => 'Another subscriber with this email already exists in this list',
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

        $subscriber->load(['tags', 'fieldValues.customField']);

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

        $subscriber = Subscriber::whereHas('contactList', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->where('email', $email)
            ->with(['tags', 'fieldValues.customField'])
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
        $subscriber->load(['tags', 'fieldValues.customField']);

        return new SubscriberResource($subscriber);
    }

    /**
     * Find subscriber by ID ensuring it belongs to user's lists
     */
    protected function findSubscriberForUser($user, int $id): ?Subscriber
    {
        return Subscriber::whereHas('contactList', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->find($id);
    }
}
