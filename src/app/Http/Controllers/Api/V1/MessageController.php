<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\ContactList;
use App\Models\MessageFieldFilter;
use App\Models\Subscriber;
use App\Services\Messages\MessageEditorService;
use App\Services\Segmentation\SubscriberFieldFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
 * - POST   /api/v1/messages/{id}/test    - Send a test email
 * - GET|POST /api/v1/messages/{id}/preview - Render subject/HTML with placeholders resolved
 * - POST   /api/v1/messages/{id}/duplicate - Copy as a new draft
 * - POST   /api/v1/messages/{id}/toggle-active - Activate/deactivate a queue message
 * - GET    /api/v1/messages/{id}/recipients-count - Planned recipient count
 * - POST   /api/v1/messages/{id}/resend-failed - Re-queue failed recipients (confirm=true)
 * - POST   /api/v1/messages/{id}/send-to-missed - Queue missed autoresponder recipients (confirm=true)
 *
 * Every route is also available under /api/v1/campaigns.
 */
class MessageController extends Controller
{
    use ManagesContactLists;

    /**
     * Relations an agent needs to read back everything it can write.
     */
    private const DETAIL_RELATIONS = [
        'mailbox',
        'contactLists',
        'excludedLists',
        'template',
        'abTest.variants',
        'fieldFilters',
        'tags',
        'translations',
        'trackedLinks',
    ];

    /**
     * Request keys that are synced into related tables rather than stored as
     * message columns.
     */
    private const NON_COLUMN_KEYS = [
        'include_field_filters',
        'exclude_field_filters',
        'contact_list_ids',
        'excluded_list_ids',
        'crm_contact_ids',
        'excluded_crm_contact_ids',
        'tag_ids',
        'translations',
        'tracked_links',
        'ab_test_config',
        'is_active',
    ];

    /** Non-fatal problems reported back with the response. */
    private array $warnings = [];

    public function __construct(private MessageEditorService $editor)
    {
    }

    /**
     * List all campaigns with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Message::where('user_id', $user->id)
            ->with(['mailbox', 'contactLists', 'excludedLists', 'tags']);

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
            ->with(self::DETAIL_RELATIONS)
            ->find($id);

        if (!$message) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Campaign not found',
            ], 404);
        }

        return response()->json([
            'data' => $this->present($message),
        ]);
    }

    /**
     * Full editable representation of a message: the model with its relations
     * plus the derived fields the web editor works with (tag/CRM ids, the A/B
     * config in the shape update() accepts, and the trigger's automation rule).
     */
    protected function present(Message $message): array
    {
        $message->loadMissing(self::DETAIL_RELATIONS);

        $data = $message->toArray();
        $data['tag_ids'] = $message->tags->pluck('id')->values()->all();
        $data['crm_contact_ids'] = $message->crmContacts()->pluck('crm_contacts.id')->all();
        $data['excluded_crm_contact_ids'] = $message->excludedCrmContacts()->pluck('crm_contacts.id')->all();
        $data['ab_test_config'] = $this->editor->formatAbTestConfig($message);

        $rule = $this->editor->triggerRule($message);
        $data['automation_rule'] = $rule ? [
            'id' => $rule->id,
            'trigger_event' => $rule->trigger_event,
            'trigger_config' => $rule->trigger_config,
            'is_active' => (bool) $rule->is_active,
        ] : null;

        return $data;
    }

    /**
     * template_id may reference the account's own templates or the public
     * system templates (user_id NULL, is_public), never another account's.
     * It is a reference only: sending always uses the message's own content.
     */
    protected function templateRule(int $userId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('templates', 'id')->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere(fn ($q) => $q->whereNull('user_id')->where('is_public', true));
            }),
        ];
    }

    /**
     * Validation rules for the editor fields beyond the basics: trigger, tags,
     * translations, tracked links, CRM contacts and A/B config. Every referenced
     * record must belong to the key owner.
     *
     * @return array<string, mixed>
     */
    protected function editorRules(int $userId): array
    {
        $ownedList = ['integer', Rule::exists('contact_lists', 'id')->where('user_id', $userId)];
        $ownedCrmContact = [
            'integer',
            Rule::exists('crm_contacts', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
        ];

        return [
            'send_in_subscriber_timezone' => 'nullable|boolean',
            // Trigger (synced to an automation rule)
            'trigger_type' => ['nullable', 'string', Rule::in(MessageEditorService::TRIGGER_TYPES)],
            'trigger_config' => 'nullable|array',
            'trigger_config.list_id' => array_merge(['nullable'], $ownedList),
            'trigger_config.message_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('user_id', $userId),
            ],
            'trigger_config.tag_id' => [
                'nullable',
                'integer',
                Rule::exists('tags', 'id')->where('user_id', $userId),
            ],
            'trigger_config.inactive_days' => 'nullable|integer|min:1|max:3650',
            'trigger_config.recent_days' => 'nullable|integer|min:1|max:3650',
            'trigger_config.url_pattern' => 'nullable|string|max:2048',
            // Campaign tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('user_id', $userId),
            ],
            // CRM contacts
            'crm_contact_ids' => 'nullable|array',
            'crm_contact_ids.*' => $ownedCrmContact,
            'excluded_crm_contact_ids' => 'nullable|array',
            'excluded_crm_contact_ids.*' => $ownedCrmContact,
            // Message translations (multi-language)
            'translations' => 'nullable|array|max:50',
            'translations.*.language' => 'required|string|max:5|distinct',
            'translations.*.subject' => 'required|string|max:255',
            'translations.*.preheader' => 'nullable|string|max:500',
            'translations.*.content' => 'nullable|string',
            // Tracked links
            'tracked_links' => 'nullable|array|max:500',
            'tracked_links.*.url' => 'required|string|max:2048',
            'tracked_links.*.tracking_enabled' => 'nullable|boolean',
            'tracked_links.*.share_data_enabled' => 'nullable|boolean',
            'tracked_links.*.shared_fields' => 'nullable|array',
            'tracked_links.*.shared_fields.*' => 'string|max:100',
            'tracked_links.*.subscribe_to_list_ids' => 'nullable|array',
            'tracked_links.*.subscribe_to_list_ids.*' => $ownedList,
            'tracked_links.*.unsubscribe_from_list_ids' => 'nullable|array',
            'tracked_links.*.unsubscribe_from_list_ids.*' => $ownedList,
            // A/B test configuration
            'ab_test_config' => 'nullable|array',
            'ab_test_config.enabled' => 'nullable|boolean',
            'ab_test_config.test_type' => 'nullable|string|in:subject,content,sender,send_time,full',
            'ab_test_config.winning_metric' => 'nullable|string|in:open_rate,click_rate,conversion_rate',
            'ab_test_config.sample_percentage' => 'nullable|integer|min:5|max:50',
            'ab_test_config.test_duration_hours' => 'nullable|integer|min:1|max:72',
            'ab_test_config.auto_select_winner' => 'nullable|boolean',
            'ab_test_config.confidence_threshold' => 'nullable|integer|min:60|max:99',
            'ab_test_config.variants' => 'nullable|array|max:5',
            'ab_test_config.variants.*.variant_letter' => 'required_with:ab_test_config.variants|string|max:1',
            'ab_test_config.variants.*.subject' => 'nullable|string|max:255',
            'ab_test_config.variants.*.preheader' => 'nullable|string|max:500',
            'ab_test_config.variants.*.is_control' => 'nullable|boolean',
        ];
    }

    /**
     * Persist whichever editor relations the caller sent. An absent key leaves
     * the stored data untouched; an empty array clears it.
     */
    protected function syncEditorRelations(Message $message, array $validated): void
    {
        if (array_key_exists('crm_contact_ids', $validated)) {
            $message->crmContacts()->sync($validated['crm_contact_ids'] ?? []);
        }

        if (array_key_exists('excluded_crm_contact_ids', $validated)) {
            $message->excludedCrmContacts()->sync($validated['excluded_crm_contact_ids'] ?? []);
        }

        if (array_key_exists('tag_ids', $validated)) {
            $message->tags()->sync($validated['tag_ids'] ?? []);
        }

        if (array_key_exists('translations', $validated)) {
            $message->translations()->delete();
            foreach ($validated['translations'] ?? [] as $translation) {
                $message->translations()->create([
                    'language' => $translation['language'],
                    'subject' => $translation['subject'],
                    'preheader' => $translation['preheader'] ?? null,
                    'content' => $translation['content'] ?? null,
                ]);
            }
        }

        if (array_key_exists('tracked_links', $validated)) {
            $this->editor->syncTrackedLinks($message, $validated['tracked_links'] ?? []);
        }

        if (array_key_exists('ab_test_config', $validated)) {
            $this->editor->syncAbTest($message, $validated['ab_test_config'] ?? []);
        }

        $message->unsetRelation('abTest');
    }

    /**
     * Keep the trigger's automation rule in step with the message (name,
     * list, active state). Without $force nothing happens for a message that
     * has no trigger; with it a cleared trigger removes the rule.
     *
     * Failures are logged and reported as a warning instead of failing the
     * request, exactly like the web editor.
     */
    protected function syncTrigger(Message $message, bool $force = false): void
    {
        if (!$force && empty($message->trigger_type)) {
            return;
        }

        try {
            $message->load('contactLists');
            $this->editor->syncTrigger($message, [
                'trigger_type' => $message->trigger_type,
                'trigger_config' => $message->trigger_config,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync message trigger: ' . $e->getMessage(), [
                'message_id' => $message->id,
                'trigger_type' => $message->trigger_type,
            ]);
            $this->warnings[] = 'The trigger was saved but its automation rule could not be synced: ' . $e->getMessage();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function withWarnings(array $payload): array
    {
        if (!empty($this->warnings)) {
            $payload['warnings'] = $this->warnings;
        }

        return $payload;
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Campaign not found',
        ], 404);
    }

    protected function findMessage(Request $request, int $id): ?Message
    {
        return Message::where('user_id', $request->user()->id)->find($id);
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
            'plain_text' => 'nullable|string',
            'tracking_enabled' => 'nullable|boolean',
            'preheader' => 'nullable|string|max:255',
            'mailbox_id' => [
                'nullable',
                'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'template_id' => $this->templateRule($user->id),
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
        ] + $this->fieldFilterRules($user->id) + $this->editorRules($user->id));

        // Create message
        $message = Message::create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'channel' => $validated['channel'],
            'type' => $validated['type'],
            'content' => $validated['content'] ?? '',
            'plain_text' => $validated['plain_text'] ?? null,
            'tracking_enabled' => $validated['tracking_enabled'] ?? null,
            'preheader' => $validated['preheader'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'day' => $validated['day'] ?? 0,
            'time_of_day' => $validated['time_of_day'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'send_in_subscriber_timezone' => $validated['send_in_subscriber_timezone'] ?? false,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'trigger_config' => $validated['trigger_config'] ?? null,
            'status' => 'draft',
            'is_active' => false,
            'include_field_filter_match' => $validated['include_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
            'exclude_field_filter_match' => $validated['exclude_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
        ]);

        // Attach contact lists
        if (!empty($validated['contact_list_ids'])) {
            $message->contactLists()->attach($validated['contact_list_ids']);
        }

        // Attach excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $message->excludedLists()->attach($validated['excluded_list_ids']);
        }

        $this->syncFieldFilters($message, $validated);
        $this->syncEditorRelations($message, $validated);

        // A trigger becomes an automation rule (inactive while the message is a draft)
        $this->syncTrigger($message);

        return response()->json($this->withWarnings([
            'data' => $this->present($message->fresh()),
            'message' => 'Campaign created successfully',
        ]), 201);
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

        // Prevent editing campaigns that are already going out. 'sending' is a
        // legacy status: broadcasts used to be queued with it instead of
        // 'scheduled', which stopped cron from ever dispatching them.
        $isDispatching = $message->type === 'broadcast'
            && $message->status === 'scheduled'
            && (int) $message->sent_count > 0;

        if ($isDispatching || in_array($message->status, ['sending', 'sent'])) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'Cannot edit a campaign that is sending or already sent',
            ], 409);
        }

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'plain_text' => 'nullable|string',
            'tracking_enabled' => 'nullable|boolean',
            'preheader' => 'nullable|string|max:255',
            'mailbox_id' => [
                'nullable',
                'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $user->id),
            ],
            'template_id' => $this->templateRule($user->id),
            'day' => 'nullable|integer|min:0',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|timezone',
            'is_active' => 'sometimes|boolean',
            'contact_list_ids' => 'sometimes|array',
            'contact_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
            'excluded_list_ids' => 'sometimes|array',
            'excluded_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('user_id', $user->id),
            ],
        ] + $this->fieldFilterRules($user->id) + $this->editorRules($user->id));

        // Related rows (lists, filters, tags, ...) live in their own tables
        $message->update(collect($validated)
            ->except(self::NON_COLUMN_KEYS)
            ->all());

        if (array_key_exists('contact_list_ids', $validated)) {
            $message->contactLists()->sync($validated['contact_list_ids'] ?? []);
        }

        if (array_key_exists('excluded_list_ids', $validated)) {
            $message->excludedLists()->sync($validated['excluded_list_ids'] ?? []);
        }

        $this->syncFieldFilters($message, $validated);
        $this->syncEditorRelations($message, $validated);

        // Same activation semantics as the editor's toggle: activating a draft
        // queue message promotes it to `scheduled` so the pipeline sees it.
        if (array_key_exists('is_active', $validated)) {
            if ($message->isQueueType()) {
                $this->editor->setActive($message, (bool) $validated['is_active']);
            } else {
                $message->update(['is_active' => (bool) $validated['is_active']]);
            }
        }

        // The audience changed: refresh the planned count like setLists() does
        $audienceKeys = ['contact_list_ids', 'excluded_list_ids', 'crm_contact_ids', 'excluded_crm_contact_ids',
            'include_field_filters', 'exclude_field_filters', 'include_field_filter_match', 'exclude_field_filter_match'];
        if (array_intersect($audienceKeys, array_keys($validated))) {
            $message->load(['contactLists', 'excludedLists', 'crmContacts', 'excludedCrmContacts', 'fieldFilters']);
            $message->update([
                'planned_recipients_count' => $message->getUniqueRecipients()->count(),
                'recipients_calculated_at' => now(),
            ]);
        }

        // Keep the automation rule in step (name, list, active state); an
        // explicit trigger_type of null removes it.
        $this->syncTrigger(
            $message,
            array_key_exists('trigger_type', $validated) || array_key_exists('trigger_config', $validated)
        );

        return response()->json($this->withWarnings([
            'data' => $this->present($message->fresh()),
            'message' => 'Campaign updated successfully',
        ]));
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
     * Validation rules for the custom-field audience filters, shared by every
     * endpoint that can set them.
     *
     * @return array<string, mixed>
     */
    protected function fieldFilterRules(int $userId): array
    {
        $fieldRule = [
            'integer',
            Rule::exists('custom_fields', 'id')->where('user_id', $userId),
        ];
        $operatorRule = 'required|string|in:' . implode(',', MessageFieldFilter::OPERATORS);

        return [
            'include_field_filters' => 'nullable|array|max:20',
            'include_field_filters.*.custom_field_id' => array_merge(['required'], $fieldRule),
            'include_field_filters.*.operator' => $operatorRule,
            'include_field_filters.*.values' => 'nullable|array|max:200',
            'include_field_filters.*.values.*' => 'nullable|string|max:255',
            'exclude_field_filters' => 'nullable|array|max:20',
            'exclude_field_filters.*.custom_field_id' => array_merge(['required'], $fieldRule),
            'exclude_field_filters.*.operator' => $operatorRule,
            'exclude_field_filters.*.values' => 'nullable|array|max:200',
            'exclude_field_filters.*.values.*' => 'nullable|string|max:255',
            'include_field_filter_match' => 'nullable|in:all,any',
            'exclude_field_filter_match' => 'nullable|in:all,any',
        ];
    }

    /**
     * Persist whichever side of the filters the caller sent. An absent key
     * leaves the stored filters untouched; an empty array clears that side.
     */
    protected function syncFieldFilters(Message $message, array $validated): void
    {
        $service = app(SubscriberFieldFilterService::class);

        if (array_key_exists('include_field_filters', $validated)) {
            $service->syncFilters($message, MessageFieldFilter::MODE_INCLUDE, $validated['include_field_filters'] ?? []);
        }

        if (array_key_exists('exclude_field_filters', $validated)) {
            $service->syncFilters($message, MessageFieldFilter::MODE_EXCLUDE, $validated['exclude_field_filters'] ?? []);
        }
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
        ] + $this->fieldFilterRules($user->id));

        $message->contactLists()->sync($validated['contact_list_ids']);
        $this->syncFieldFilters($message, $validated);
        $message->load(['contactLists', 'fieldFilters']);

        // The trigger's rule falls back to the first list when it names none
        $this->syncTrigger($message);

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
        ] + $this->fieldFilterRules($user->id));

        $message->excludedLists()->sync($validated['excluded_list_ids']);
        $this->syncFieldFilters($message, $validated);
        $message->load(['excludedLists', 'fieldFilters']);

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
        $this->syncTrigger($message);

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
            // Activate the autoresponder.
            //
            // `scheduled` is the only status the send pipeline recognises: both
            // the signup listener (CreateAutoresponderQueueEntries) and the cron
            // processor filter on it. Writing 'active' here left the message
            // permanently invisible to them - no queue entry was created when
            // somebody subscribed, cron never backfilled or dispatched one, and
            // every recipient silently rolled into "missed".
            $message->update([
                'is_active' => true,
                'status' => 'scheduled',
                'scheduled_at' => $message->scheduled_at ?? now(),
            ]);

            // Schedule the recipients already on the list whose send time is
            // still ahead, so activation shows up in the stats immediately
            // instead of only after the next cron run.
            $syncResult = $message->syncPlannedRecipients();
            $this->syncTrigger($message);

            return response()->json([
                'data' => $message->fresh(['mailbox', 'contactLists', 'excludedLists']),
                'message' => 'Autoresponder activated',
                'recipients_added' => $syncResult['added'],
            ]);
        }

        // For broadcasts, queue for immediate sending. Same rule as above: cron
        // dispatches `scheduled` messages only, so 'sending' stranded the
        // campaign with its recipients queued but nothing ever sending them.
        $message->update([
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        // Sync planned recipients
        $syncResult = $message->syncPlannedRecipients();
        $this->syncTrigger($message);

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

    /**
     * Resolve subject/preheader/content, optionally from a translation.
     *
     * @return array{subject: string, preheader: ?string, content: string}|null null when the language has no translation
     */
    protected function contentFor(Message $message, ?string $language): ?array
    {
        if ($language) {
            $translation = $message->getTranslationForLanguage($language);
            if (!$translation) {
                return null;
            }

            return [
                'subject' => (string) $translation->subject,
                'preheader' => $translation->preheader,
                'content' => (string) ($translation->content ?: $message->content),
            ];
        }

        return [
            'subject' => (string) $message->subject,
            'preheader' => $message->preheader,
            'content' => (string) $message->content,
        ];
    }

    /**
     * Send a test email of a campaign to one or more addresses.
     *
     * Placeholders are resolved for: the given subscriber_id, else the
     * subscriber with the test address, else the first subscriber on the
     * campaign's lists, else sample data. The subject gets a "[TEST] " prefix.
     * Nothing is written to the campaign's queue or stats.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $message = $this->findMessage($request, $id);
        if (!$message) {
            return $this->notFound();
        }

        $userId = $request->user()->id;

        $validated = $request->validate([
            'email' => 'required_without:emails|nullable|email',
            'emails' => 'required_without:email|nullable|array|min:1|max:5',
            'emails.*' => 'email',
            'subscriber_id' => 'nullable|integer',
            'mailbox_id' => [
                'nullable',
                'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $userId),
            ],
            'language' => 'nullable|string|max:5',
        ]);

        if ($message->channel !== 'email') {
            return $this->badRequest('Test sends are available for email campaigns only');
        }

        $parts = $this->contentFor($message, $validated['language'] ?? null);
        if (!$parts) {
            return $this->badRequest("The campaign has no translation for language '{$validated['language']}'");
        }

        if (trim($parts['content']) === '') {
            return $this->badRequest('Campaign must have content');
        }

        $mailbox = !empty($validated['mailbox_id'])
            ? Mailbox::where('user_id', $userId)->find($validated['mailbox_id'])
            : $message->getEffectiveMailbox();

        if (!$mailbox) {
            return $this->badRequest('No mailbox configured: pass mailbox_id or set a default mailbox');
        }

        $explicitSubscriber = null;
        if (!empty($validated['subscriber_id'])) {
            $explicitSubscriber = Subscriber::where('user_id', $userId)->find($validated['subscriber_id']);
            if (!$explicitSubscriber) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Subscriber not found',
                ], 404);
            }
        }

        $emails = array_values(array_unique(array_filter(array_merge(
            !empty($validated['email']) ? [$validated['email']] : [],
            $validated['emails'] ?? []
        ))));

        if (count($emails) > 5) {
            return $this->badRequest('At most 5 test addresses per request');
        }

        $listIds = $message->contactLists()->pluck('contact_lists.id')->all();
        $sent = [];
        $failed = [];

        foreach ($emails as $email) {
            $subscriber = $explicitSubscriber
                ?? $this->editor->findTestSubscriber($userId, $email, null, $listIds);

            try {
                $rendered = $this->editor->render(
                    $parts['content'],
                    $parts['subject'],
                    $parts['preheader'],
                    $subscriber,
                    $subscriber ? null : $this->editor->sampleData($email)
                );

                $this->editor->deliverTest($mailbox, $email, $rendered['subject'], $rendered['content']);

                $sent[] = [
                    'email' => $email,
                    'subject' => '[TEST] ' . $rendered['subject'],
                    'personalised_with' => $subscriber
                        ? ['type' => 'subscriber', 'subscriber_id' => $subscriber->id, 'email' => $subscriber->email]
                        : ['type' => 'sample'],
                ];
            } catch (\Throwable $e) {
                Log::error('API test email failed: ' . $e->getMessage(), ['message_id' => $message->id]);
                $failed[] = ['email' => $email, 'error' => $e->getMessage()];
            }
        }

        $data = [
            'campaign_id' => $message->id,
            'mailbox' => [
                'id' => $mailbox->id,
                'name' => $mailbox->name,
                'from_email' => $mailbox->from_email,
            ],
            'language' => $validated['language'] ?? null,
            'sent' => $sent,
            'failed' => $failed,
        ];

        if (empty($sent)) {
            return response()->json([
                'error' => 'Bad Gateway',
                'message' => 'The test email could not be sent: ' . ($failed[0]['error'] ?? 'unknown error'),
                'data' => $data,
            ], 502);
        }

        return response()->json([
            'data' => $data,
            'message' => count($sent) . ' test email(s) sent',
        ]);
    }

    /**
     * Render a campaign the way a recipient sees it: subject and HTML with
     * placeholders resolved and the preheader injected.
     *
     * Personalisation: subscriber_id or subscriber_email (must belong to the
     * account), else the first subscriber on the campaign's lists, else
     * sample data. Placeholders left unresolved are listed.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $message = $this->findMessage($request, $id);
        if (!$message) {
            return $this->notFound();
        }

        $userId = $request->user()->id;

        $validated = $request->validate([
            'subscriber_id' => 'nullable|integer',
            'subscriber_email' => 'nullable|email',
            'language' => 'nullable|string|max:5',
        ]);

        $parts = $this->contentFor($message, $validated['language'] ?? null);
        if (!$parts) {
            return $this->badRequest("The campaign has no translation for language '{$validated['language']}'");
        }

        $subscriber = null;
        if (!empty($validated['subscriber_id']) || !empty($validated['subscriber_email'])) {
            $subscriber = Subscriber::where('user_id', $userId)
                ->when(!empty($validated['subscriber_id']), fn ($q) => $q->where('id', $validated['subscriber_id']))
                ->when(!empty($validated['subscriber_email']), fn ($q) => $q->where('email', $validated['subscriber_email']))
                ->first();

            if (!$subscriber) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Subscriber not found',
                ], 404);
            }
        } else {
            $listIds = $message->contactLists()->pluck('contact_lists.id')->all();
            $subscriber = $this->editor->findTestSubscriber($userId, null, null, $listIds);
        }

        $rendered = $this->editor->render(
            $parts['content'],
            $parts['subject'],
            $parts['preheader'],
            $subscriber,
            $subscriber ? null : $this->editor->sampleData('jan.kowalski@example.com')
        );

        preg_match_all('/\[\[[^\]]+\]\]/', $rendered['subject'] . ' ' . $rendered['content'], $matches);

        return response()->json([
            'data' => [
                'campaign_id' => $message->id,
                'channel' => $message->channel,
                'language' => $validated['language'] ?? null,
                'subject' => $rendered['subject'],
                'preheader' => $parts['preheader'],
                'html' => $rendered['content'],
                'personalised_with' => $subscriber
                    ? ['type' => 'subscriber', 'subscriber_id' => $subscriber->id, 'email' => $subscriber->email]
                    : ['type' => 'sample'],
                'unresolved_placeholders' => array_values(array_unique($matches[0])),
            ],
        ]);
    }

    /**
     * Copy a campaign as a new draft (lists, exclusions, field filters,
     * tracked links, translations and trigger settings come along).
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $message = $this->findMessage($request, $id);
        if (!$message) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
        ]);

        $copy = $this->editor->duplicate($message, $validated['subject'] ?? null);

        return response()->json([
            'data' => $this->present($copy->fresh()),
            'message' => 'Campaign duplicated as a draft',
        ], 201);
    }

    /**
     * Activate or deactivate a queue (autoresponder) message. Without
     * is_active the flag is toggled. Activating a draft promotes it to
     * `scheduled` (same as the editor) and schedules the recipients whose send
     * time is still ahead.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $message = $this->findMessage($request, $id);
        if (!$message) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        if (!$message->isQueueType()) {
            return $this->badRequest('Only autoresponder (queue) messages can be activated or deactivated; use send or schedule for broadcasts');
        }

        $active = array_key_exists('is_active', $validated) && $validated['is_active'] !== null
            ? (bool) $validated['is_active']
            : !($message->is_active ?? true);

        $this->editor->setActive($message, $active);

        $recipientsAdded = 0;
        if ($active && $message->status === 'scheduled') {
            $recipientsAdded = $message->syncPlannedRecipients()['added'] ?? 0;
        }

        $this->syncTrigger($message);

        return response()->json($this->withWarnings([
            'data' => [
                'id' => $message->id,
                'is_active' => (bool) $message->is_active,
                'status' => $message->status,
                'recipients_added' => $recipientsAdded,
            ],
            'message' => $active ? 'Autoresponder activated' : 'Autoresponder deactivated',
        ]));
    }

    /**
     * Planned recipient count of a campaign (lists + CRM contacts, after
     * exclusions and field filters). For autoresponders also the number of
     * subscribers it missed and the queue breakdown.
     */
    public function recipientsCount(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $message = Message::where('user_id', $request->user()->id)
            ->with(['contactLists', 'excludedLists'])
            ->find($id);

        if (!$message) {
            return $this->notFound();
        }

        $counts = $this->editor->recipientCounts($message);

        return response()->json([
            'data' => $counts + [
                'type' => $message->type,
                'status' => $message->status,
                'stored_planned_recipients_count' => $message->planned_recipients_count,
            ],
        ]);
    }

    /**
     * Re-queue every recipient whose send failed. Real emails go out, so the
     * call needs confirm=true; without it a 409 reports how many would be sent.
     */
    public function resendFailed(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $message = $this->findMessage($request, $id);
        if (!$message) {
            return $this->notFound();
        }

        $failedCount = $message->queueEntries()
            ->where('status', \App\Models\MessageQueueEntry::STATUS_FAILED)
            ->count();

        if ($failedCount === 0) {
            return $this->badRequest('The campaign has no failed recipients');
        }

        if (!$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Conflict',
                'message' => "This re-sends the campaign to {$failedCount} recipient(s) whose delivery failed. Repeat with confirm=true to proceed.",
                'failed_count' => $failedCount,
            ], 409);
        }

        $requeued = $this->editor->resendToFailed($message);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'requeued' => $requeued,
                'status' => $message->fresh()->status,
            ],
            'message' => "{$requeued} failed recipient(s) re-queued",
        ]);
    }

    /**
     * Queue an active autoresponder for the subscribers it missed (they joined
     * before its day offset could be honoured), due immediately. Needs
     * confirm=true; without it a 409 reports how many would be sent.
     */
    public function sendToMissed(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $message = $this->findMessage($request, $id);
        if (!$message) {
            return $this->notFound();
        }

        if (!$message->isQueueType()) {
            return $this->badRequest('Only autoresponder (queue) messages have missed recipients');
        }

        if (!($message->is_active ?? true)) {
            return $this->badRequest('The autoresponder must be active to send to missed recipients');
        }

        // No limit — process ALL missed subscribers
        $missed = $message->getQueueScheduleStats(null)['missed_subscribers'] ?? [];

        if (empty($missed)) {
            return $this->badRequest('The autoresponder has no missed recipients');
        }

        if (!$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'This sends the autoresponder now to ' . count($missed) . ' subscriber(s) who missed it. Repeat with confirm=true to proceed.',
                'missed_count' => count($missed),
            ], 409);
        }

        $result = $this->editor->queueMissedRecipients($message, $missed);

        return response()->json([
            'data' => ['id' => $message->id] + $result,
            'message' => "Scheduled for {$result['created']} missed subscriber(s)",
        ]);
    }
}
