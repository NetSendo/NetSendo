<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Resources\Api\V1\ContactListResource;
use App\Http\Resources\Api\V1\SubscriberResource;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContactListController extends Controller
{
    use ManagesContactLists;

    /**
     * Get all contact lists for the user
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = ContactList::forUser($user->id)
            ->withCount(['subscribers' => function ($query) {
                $query->where('contact_list_subscriber.status', 'active');
            }])
            ->with(['group', 'defaultMailbox']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by group
        if ($request->has('group_id')) {
            $query->where('contact_list_group_id', $request->group_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);

        return ContactListResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single contact list
     */
    public function show(Request $request, int $id): ContactListResource|JsonResponse
    {
        $user = $request->user();

        $list = ContactList::forUser($user->id)
            ->withCount(['subscribers' => function ($query) {
                $query->where('contact_list_subscriber.status', 'active');
            }])
            ->with(['group', 'defaultMailbox'])
            ->find($id);

        if (!$list) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Contact list not found',
            ], 404);
        }

        return new ContactListResource($list);
    }

    /**
     * Get subscribers for a contact list
     */
    public function subscribers(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        $list = ContactList::forUser($user->id)->find($id);

        if (!$list) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Contact list not found',
            ], 404);
        }

        $query = $list->subscribers()
            ->with(['tags', 'fieldValues.customField']);

        // Filter by status
        if ($request->has('status')) {
            $query->wherePivot('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);

        return SubscriberResource::collection($query->paginate($perPage));
    }

    /**
     * Create a contact list.
     */
    public function store(Request $request): ContactListResource|JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:email,sms',
            'description' => 'nullable|string|max:1000',
            'contact_list_group_id' => [
                'nullable', 'integer',
                Rule::exists('contact_list_groups', 'id')->where('user_id', $user->id),
            ],
            'default_mailbox_id' => [
                'nullable', 'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'default_sms_provider_id' => [
                'nullable', 'integer',
                Rule::exists('sms_providers', 'id')->where('user_id', $user->id),
            ],
            'is_public' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:64',
            'double_opt_in' => 'nullable|boolean',
            'resubscription_behavior' => 'nullable|in:reset_date,keep_original_date',
            'max_subscribers' => 'nullable|integer|min:0',
            'settings' => 'nullable|array',
        ]);

        $settings = $validated['settings'] ?? [];

        if (array_key_exists('double_opt_in', $validated)) {
            $settings['subscription']['double_optin'] = (bool) $validated['double_opt_in'];
        }

        $list = ContactList::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'email',
            'description' => $validated['description'] ?? null,
            'contact_list_group_id' => $validated['contact_list_group_id'] ?? null,
            'default_mailbox_id' => $validated['default_mailbox_id'] ?? null,
            'default_sms_provider_id' => $validated['default_sms_provider_id'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'timezone' => $validated['timezone'] ?? null,
            'resubscription_behavior' => $validated['resubscription_behavior'] ?? 'reset_date',
            'max_subscribers' => $validated['max_subscribers'] ?? 0,
            'settings' => $settings,
        ]);

        $list->loadCount('subscribers')->load(['group', 'defaultMailbox']);

        return (new ContactListResource($list))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a contact list.
     */
    public function update(Request $request, int $id): ContactListResource|JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $user = $request->user();
        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_list_group_id' => [
                'nullable', 'integer',
                Rule::exists('contact_list_groups', 'id')->where('user_id', $user->id),
            ],
            'default_mailbox_id' => [
                'nullable', 'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'default_sms_provider_id' => [
                'nullable', 'integer',
                Rule::exists('sms_providers', 'id')->where('user_id', $user->id),
            ],
            'is_public' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:64',
            'double_opt_in' => 'nullable|boolean',
            'resubscription_behavior' => 'nullable|in:reset_date,keep_original_date',
            'max_subscribers' => 'nullable|integer|min:0',
            'signups_blocked' => 'nullable|boolean',
            'webhook_url' => 'nullable|url|max:2048',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => ['string', Rule::in(ContactList::acceptedWebhookEvents())],
            'settings' => 'nullable|array',
        ]);

        $attributes = collect($validated)
            ->except(['double_opt_in', 'settings'])
            ->toArray();

        if (array_key_exists('settings', $validated) || array_key_exists('double_opt_in', $validated)) {
            // Merge rather than replace — a partial update must not wipe the
            // list's sending, pages or advanced configuration.
            $settings = array_replace_recursive($list->settings ?? [], $validated['settings'] ?? []);

            if (array_key_exists('double_opt_in', $validated)) {
                $settings['subscription']['double_optin'] = (bool) $validated['double_opt_in'];
            }

            $attributes['settings'] = $settings;
        }

        $list->update($attributes);
        $list->loadCount('subscribers')->load(['group', 'defaultMailbox']);

        return new ContactListResource($list);
    }

    /**
     * Delete a contact list (soft delete).
     *
     * Refuses to touch a non-empty list unless the caller explicitly confirms,
     * so an agent cannot wipe an audience through a vague instruction.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $memberCount = $list->subscribers()->count();
        $confirmed = $request->boolean('confirm');

        if ($memberCount > 0 && !$confirmed) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => "List '{$list->name}' still has {$memberCount} member(s). Re-send with confirm=true to delete it.",
                'subscribers_count' => $memberCount,
            ], 409);
        }

        $name = $list->name;
        $list->delete();

        return response()->json([
            'message' => "Contact list '{$name}' deleted",
            'list_id' => $id,
            'subscribers_detached' => 0,
            'note' => 'Subscribers themselves are not deleted; only the list is removed.',
        ]);
    }

    /**
     * Operational snapshot of a list: membership breakdown, engagement
     * counters and configuration that affects sending.
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $list->load(['group', 'defaultMailbox', 'defaultSmsProvider']);

        $statusCounts = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->groupBy('pivot.status')
            ->select('pivot.status', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'status')
            ->all();

        $members = array_sum($statusCounts);

        $engagement = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->where('pivot.status', 'active')
            ->whereNull('subscribers.deleted_at')
            ->selectRaw('COUNT(*) as active')
            ->selectRaw('SUM(CASE WHEN subscribers.opens_count > 0 THEN 1 ELSE 0 END) as openers')
            ->selectRaw('SUM(CASE WHEN subscribers.clicks_count > 0 THEN 1 ELSE 0 END) as clickers')
            ->selectRaw('SUM(CASE WHEN pivot.confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as confirmed')
            ->first();

        $active = (int) ($engagement->active ?? 0);

        $recent = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $list->id)
            ->selectRaw('SUM(CASE WHEN subscribed_at >= ? THEN 1 ELSE 0 END) as added_30d', [now()->subDays(30)->toDateTimeString()])
            ->selectRaw('SUM(CASE WHEN unsubscribed_at >= ? THEN 1 ELSE 0 END) as lost_30d', [now()->subDays(30)->toDateTimeString()])
            ->first();

        return response()->json([
            'data' => [
                'id' => $list->id,
                'name' => $list->name,
                'type' => $list->type,
                'description' => $list->description,
                'group' => $list->group?->name,
                'members' => [
                    'total' => $members,
                    'active' => (int) ($statusCounts['active'] ?? 0),
                    'unsubscribed' => (int) ($statusCounts['unsubscribed'] ?? 0),
                    'bounced' => (int) ($statusCounts['bounced'] ?? 0),
                    'by_status' => $statusCounts,
                ],
                'engagement' => [
                    'openers' => (int) ($engagement->openers ?? 0),
                    'clickers' => (int) ($engagement->clickers ?? 0),
                    'confirmed' => (int) ($engagement->confirmed ?? 0),
                    'open_share_percent' => $active > 0 ? round(((int) $engagement->openers / $active) * 100, 2) : 0.0,
                    'click_share_percent' => $active > 0 ? round(((int) $engagement->clickers / $active) * 100, 2) : 0.0,
                ],
                'last_30_days' => [
                    'added' => (int) ($recent->added_30d ?? 0),
                    'lost' => (int) ($recent->lost_30d ?? 0),
                    'net' => (int) ($recent->added_30d ?? 0) - (int) ($recent->lost_30d ?? 0),
                ],
                'configuration' => [
                    'double_opt_in' => (bool) ($list->settings['subscription']['double_optin'] ?? false),
                    'resubscription_behavior' => $list->resubscription_behavior ?? 'reset_date',
                    'signups_blocked' => (bool) $list->signups_blocked,
                    'max_subscribers' => (int) $list->max_subscribers,
                    'accepts_signups' => $list->canAcceptSignups(),
                    'default_mailbox' => $list->defaultMailbox ? [
                        'id' => $list->defaultMailbox->id,
                        'name' => $list->defaultMailbox->name,
                        'from_email' => $list->defaultMailbox->from_email,
                    ] : null,
                    'default_sms_provider' => $list->defaultSmsProvider ? [
                        'id' => $list->defaultSmsProvider->id,
                        'name' => $list->defaultSmsProvider->name,
                    ] : null,
                    'webhook_url' => $list->webhook_url,
                    'webhook_events' => $list->webhook_events ?? [],
                ],
                'created_at' => $list->created_at?->toISOString(),
            ],
        ]);
    }
}
