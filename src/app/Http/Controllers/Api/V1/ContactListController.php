<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Resources\Api\V1\ContactListResource;
use App\Http\Resources\Api\V1\SubscriberResource;
use App\Models\ContactList;
use App\Services\Lists\ListSettingsSchema;
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
    public function show(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        return $this->listResponse($request, $list);
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
     *
     * Accepts every setting of the list editor in the browser, validated the
     * same way (see ListSettingsSchema for the `settings` structure).
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:email,sms',
            ...$this->configurationRules($request),
        ]);

        $attributes = $this->configurationAttributes($validated, []);

        $list = ContactList::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'email',
            'description' => null,
            'is_public' => false,
            'resubscription_behavior' => 'reset_date',
            'max_subscribers' => 0,
            'settings' => [],
            ...$attributes,
        ]);

        if (array_key_exists('tags', $validated)) {
            $list->tags()->sync($validated['tags'] ?? []);
        }

        return $this->listResponse($request, $list, 201);
    }

    /**
     * Update a contact list. Only the fields sent are changed; `settings` and
     * `sync_settings` are deep-merged into what is stored, so a partial update
     * never wipes the rest of the list's configuration.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            ...$this->configurationRules($request, $list),
        ]);

        $attributes = $this->configurationAttributes($validated, $list->settings ?? [], $list);

        if (array_key_exists('name', $validated)) {
            $attributes['name'] = $validated['name'];
        }

        $list->update($attributes);

        if (array_key_exists('tags', $validated)) {
            $list->tags()->sync($validated['tags'] ?? []);
        }

        return $this->listResponse($request, $list->fresh());
    }

    /**
     * Rules shared by create and update: the list's own columns plus the
     * documented `settings` document.
     */
    private function configurationRules(Request $request, ?ContactList $list = null): array
    {
        $userId = $request->user()->id;

        return [
            'description' => 'nullable|string|max:1000',
            'contact_list_group_id' => [
                'nullable', 'integer',
                Rule::exists('contact_list_groups', 'id')->where('user_id', $userId),
            ],
            'default_mailbox_id' => [
                'nullable', 'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $userId),
            ],
            'default_sms_provider_id' => [
                'nullable', 'integer',
                Rule::exists('sms_providers', 'id')->where('user_id', $userId),
            ],
            'tags' => 'nullable|array',
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('user_id', $userId),
            ],
            'is_public' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:64',
            'double_opt_in' => 'nullable|boolean',
            'resubscription_behavior' => 'nullable|in:reset_date,keep_original_date',
            'reset_autoresponders_on_resubscription' => 'nullable|boolean',
            'max_subscribers' => 'nullable|integer|min:0',
            'signups_blocked' => 'nullable|boolean',
            'required_fields' => 'nullable|array',
            'webhook_url' => 'nullable|url|max:2048',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => ['string', Rule::in(ContactList::acceptedWebhookEvents())],

            // Co-registration: members are synced from this parent list
            'parent_list_id' => [
                'nullable', 'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
                ...($list ? [Rule::notIn([$list->id])] : []),
            ],
            'sync_settings' => 'nullable|array',
            'sync_settings.sync_on_subscribe' => 'boolean',
            'sync_settings.sync_on_unsubscribe' => 'boolean',

            ...ListSettingsSchema::rules($userId),
        ];
    }

    /**
     * Column values for a create or update from validated input. The list's
     * sender lives in two places (the default_mailbox_id column used for
     * sending and settings.sending.mailbox_id shown by the editor), so they
     * are kept in step whichever one the caller sets; the same goes for the
     * SMS provider.
     */
    private function configurationAttributes(array $validated, array $storedSettings, ?ContactList $list = null): array
    {
        $columns = [
            'description', 'contact_list_group_id', 'default_mailbox_id', 'default_sms_provider_id',
            'is_public', 'timezone', 'resubscription_behavior', 'reset_autoresponders_on_resubscription',
            'max_subscribers', 'signups_blocked', 'required_fields', 'webhook_url', 'webhook_events',
            'parent_list_id',
        ];

        $attributes = array_intersect_key($validated, array_flip($columns));

        // Columns that are NOT NULL in the schema: an explicit null means "default"
        foreach (['is_public' => false, 'signups_blocked' => false, 'max_subscribers' => 0, 'resubscription_behavior' => 'reset_date'] as $column => $default) {
            if (array_key_exists($column, $attributes) && $attributes[$column] === null) {
                $attributes[$column] = $default;
            }
        }

        if (array_key_exists('reset_autoresponders_on_resubscription', $attributes) && $attributes['reset_autoresponders_on_resubscription'] === null) {
            unset($attributes['reset_autoresponders_on_resubscription']);
        }

        if (array_key_exists('sync_settings', $validated)) {
            $attributes['sync_settings'] = $validated['sync_settings'] === null
                ? null
                : ListSettingsSchema::merge($list?->sync_settings ?? [], $validated['sync_settings']);
        }

        $touchesSettings = array_key_exists('settings', $validated)
            || array_key_exists('double_opt_in', $validated)
            || array_key_exists('default_mailbox_id', $validated)
            || array_key_exists('default_sms_provider_id', $validated);

        if (!$touchesSettings) {
            return $attributes;
        }

        $patch = $validated['settings'] ?? [];
        $settings = ListSettingsSchema::merge($storedSettings, $patch);

        if (array_key_exists('double_opt_in', $validated) && $validated['double_opt_in'] !== null) {
            $settings['subscription']['double_optin'] = (bool) $validated['double_opt_in'];
        }

        foreach (['mailbox_id' => 'default_mailbox_id', 'sms_provider_id' => 'default_sms_provider_id'] as $settingKey => $column) {
            if (is_array($patch['sending'] ?? null) && array_key_exists($settingKey, $patch['sending'])) {
                $attributes[$column] = $patch['sending'][$settingKey];
            } elseif (array_key_exists($column, $validated)) {
                $settings['sending'][$settingKey] = $validated[$column];
            }
        }

        $attributes['settings'] = $settings;

        return $attributes;
    }

    /**
     * Full configuration of one list: the summary fields plus everything the
     * list editor shows (settings document, tags, co-registration, limits,
     * webhook). CRON sending windows live at /lists/{id}/cron-settings.
     */
    private function listResponse(Request $request, ContactList $list, int $status = 200): JsonResponse
    {
        $list->loadCount(['subscribers' => function ($query) {
            $query->where('contact_list_subscriber.status', 'active');
        }])->load(['group', 'defaultMailbox', 'tags']);

        $data = (new ContactListResource($list))->resolve($request);

        $data += [
            'double_opt_in' => (bool) ($list->settings['subscription']['double_optin'] ?? false),
            'contact_list_group_id' => $list->contact_list_group_id,
            'default_mailbox_id' => $list->default_mailbox_id,
            'default_sms_provider_id' => $list->default_sms_provider_id,
            'tags' => $list->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values(),
            'settings' => (object) ($list->settings ?? []),
            'resubscription_behavior' => $list->resubscription_behavior ?? 'reset_date',
            'reset_autoresponders_on_resubscription' => (bool) ($list->reset_autoresponders_on_resubscription ?? true),
            'max_subscribers' => (int) ($list->max_subscribers ?? 0),
            'signups_blocked' => (bool) $list->signups_blocked,
            'required_fields' => $list->required_fields ?? [],
            'parent_list_id' => $list->parent_list_id,
            'sync_settings' => (object) ($list->sync_settings ?? []),
            'webhook_url' => $list->webhook_url,
            'webhook_events' => $list->webhook_events ?? [],
            'has_list_api_key' => !empty($list->api_key),
        ];

        return response()->json(['data' => $data], $status);
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
