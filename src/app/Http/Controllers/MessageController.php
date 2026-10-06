<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\AutomationRule;
use App\Models\CrmContact;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\EmailReadSession;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageFieldFilter;
use App\Models\MessageQueueEntry;
use App\Models\MessageTrackedLink;
use App\Models\Template;
use App\Services\Messages\MessageEditorService;
use App\Services\Segmentation\SubscriberFieldFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Http\Controllers\InsertController;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = auth()->user()->messages()->with(['contactLists', 'attachments', 'abTest']);

        // Filter by Type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by Contact List
        if ($request->filled('list_id')) {
            $query->whereHas('contactLists', function ($q) use ($request) {
                $q->where('contact_lists.id', $request->input('list_id'));
            });
        }

        // Filter by Group
        if ($request->filled('group_id')) {
            $group = \App\Models\ContactListGroup::find($request->input('group_id'));
            if ($group) {
                $groupIds = array_merge([$group->id], $group->getAllDescendantIds());
                $query->whereHas('contactLists', function ($q) use ($groupIds) {
                    $q->whereIn('contact_list_group_id', $groupIds);
                });
            }
        }

        // Filter by Tag
        if ($request->filled('tag_id')) {
             $query->whereHas('contactLists', function ($q) use ($request) {
                $q->whereHas('tags', function ($t) use ($request) {
                    $t->where('tags.id', $request->input('tag_id'));
                });
            });
        }

        // Search by Subject or ID
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', '%' . $search . '%')
                  ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        // Filter by Campaign Plan
        if ($request->filled('campaign_plan_id')) {
            $query->where('campaign_plan_id', $request->input('campaign_plan_id'));
        }

        // Sorting
        // Default: use scheduled_at/send_at for sent/scheduled messages, created_at for drafts
        // For autoresponders (queues), always use created_at
        $sortField = $request->input('sort', 'effective_date');
        $sortDirection = $request->input('direction', 'desc');

        // Allow sorting by valid fields
        if (in_array($sortField, ['id', 'subject', 'status', 'created_at', 'type', 'day'])) {
            $query->orderBy($sortField, $sortDirection);

            // When sorting by type, add secondary sort by day for proper queue ordering
            // This ensures autoresponder messages are sorted: Day 1, Day 2, Day 3... instead of random order
            if ($sortField === 'type') {
                $query->orderBy('day', $sortDirection);
            }
        } elseif ($sortField === 'effective_date') {
            // Smart date sorting:
            // - For broadcasts (sent/scheduled): use scheduled_at or send_at
            // - For drafts (no scheduled_at): use created_at
            // - For autoresponders/queues: use created_at
            $query->orderByRaw("
                CASE
                    WHEN type = 'autoresponder' THEN created_at
                    WHEN scheduled_at IS NOT NULL THEN scheduled_at
                    WHEN send_at IS NOT NULL THEN send_at
                    ELSE created_at
                END {$sortDirection}
            ");
        } else {
             $query->latest();
        }

        $perPage = min($request->input('per_page', 30), 100);

        return Inertia::render('Message/Index', [
            'messages' => $query
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn ($msg) => [
                    'id' => $msg->id,
                    'subject' => $msg->subject,
                    'preheader' => $msg->preheader,
                    'status' => $msg->status,
                    'type' => $msg->type,
                    'day' => $msg->day,
                    'type' => $msg->type,
                    'day' => $msg->day,
                    'is_active' => $msg->is_active ?? true,
                    'pdf_attachments' => $msg->attachments
                        ->filter(fn($a) => $a->mime_type === 'application/pdf')
                        ->map(fn($a) => $a->original_name)
                        ->values()
                        ->toArray(),
                    'lists_count' => $msg->contactLists->count(),
                    'list_name' => $msg->contactLists->count() > 0
                        ? ($msg->contactLists->count() > 1 ? $msg->contactLists->count() . ' list' : $msg->contactLists->first()->name)
                        : '-',
                    // For sent messages, use frozen planned_recipients_count
                    // For draft/scheduled, use stored value (calculated when message is edited/scheduled)
                    // This avoids expensive getUniqueRecipients() call for every message in the list
                    'recipients_count' => $msg->planned_recipients_count ?? $msg->sent_count ?? null,
                    // Skipped count removed from list view for performance
                    // Available in message stats/edit view via getQueueScheduleStats()
                    'skipped_count' => null,
                    'created_at' => DateHelper::formatForUser($msg->created_at),
                    'scheduled_at' => $msg->scheduled_at
                        ? DateHelper::formatForUser($msg->scheduled_at)
                        : null,
                    // A/B Test status for indicator
                    'ab_test' => $msg->abTest ? [
                        'id' => $msg->abTest->id,
                        'status' => $msg->abTest->status,
                        'variants_count' => $msg->abTest->variants_count ?? $msg->abTest->variants()->count(),
                    ] : null,
                ]),
            'filters' => $request->only(['type', 'list_id', 'group_id', 'tag_id', 'campaign_plan_id', 'search', 'sort', 'direction', 'per_page']),
            'lists' => auth()->user()->accessibleLists()->select('id', 'name')->orderBy('name')->get(),
            'groups' => auth()->user()->contactListGroups()
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                ]),
            'tags' => auth()->user()->tags()->select('id', 'name')->orderBy('name')->get(),
            'campaignPlans' => \App\Models\CampaignPlan::forUser(auth()->id())
                ->whereNotNull('exported_at')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Get current statuses for messages (for AJAX polling)
     */
    public function statuses(Request $request)
    {
        $ids = $request->input('ids');
        if (!$ids) {
            return response()->json([]);
        }

        $messageIds = is_array($ids) ? $ids : explode(',', $ids);

        $messages = Message::whereIn('id', $messageIds)
            ->where('user_id', auth()->id())
            ->select('id', 'status', 'sent_count', 'planned_recipients_count')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'status' => $m->status,
                'sent_count' => $m->sent_count,
                'planned_recipients_count' => $m->planned_recipients_count,
            ]);

        return response()->json($messages);
    }

    /**
     * Get recipient counts for specific messages (for progressive loading)
     * Returns accurate counts including exclusions and filters
     */
    public function recipientCounts(Request $request)
    {
        $ids = $request->input('ids');
        if (!$ids) {
            return response()->json([]);
        }

        $messageIds = is_array($ids) ? $ids : explode(',', $ids);

        $messages = Message::whereIn('id', $messageIds)
            ->where('user_id', auth()->id())
            ->with(['contactLists', 'excludedLists'])
            ->get();

        $editor = app(MessageEditorService::class);

        return response()->json($messages->map(fn($msg) => $editor->recipientCounts($msg)));
    }

    public function create(Request $request)
    {
        $defaultMailbox = Mailbox::getDefaultFor(auth()->id());
        $insertController = new InsertController();

        // Get optional pre-selected list ID from query parameter
        $preselectedListId = $request->query('list_id') ? (int) $request->query('list_id') : null;

        return Inertia::render('Message/Create', [
            'preselectedListId' => $preselectedListId,
            'lists' => auth()->user()->accessibleLists()
                ->select('id', 'name', 'type', 'default_mailbox_id', 'contact_list_group_id')
                ->withCount(['subscribers' => function ($query) {
                    $query->where('contact_list_subscriber.status', 'active');
                }])
                ->with(['defaultMailbox:id,name,provider', 'group:id,name', 'tags:id,name'])
                ->get(),
            'groups' => auth()->user()->contactListGroups()
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                ]),
            'tags' => auth()->user()->tags()->select('id', 'name')->orderBy('name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'mailboxes' => auth()->user()->mailboxes()
                ->active()
                ->select('id', 'name', 'provider', 'from_email', 'is_default')
                ->get(),
            'templates' => Template::where('user_id', auth()->id())
                ->orWhere(fn($q) => $q->whereNull('user_id')->where('is_public', true))
                ->select('id', 'name', 'thumbnail', 'category', 'user_id', 'is_public', 'json_structure', 'content')
                ->latest()
                ->get(),
            'defaultMailbox' => $defaultMailbox ? [
                'id' => $defaultMailbox->id,
                'name' => $defaultMailbox->name,
                'provider' => $defaultMailbox->provider,
                'from_email' => $defaultMailbox->from_email,
            ] : null,
            // Insert snippets data
            'inserts' => Template::where('user_id', auth()->id())->inserts()->orderBy('name')->get(),
            'signatures' => Template::where('user_id', auth()->id())->signatures()->orderBy('name')->get(),
            'systemVariables' => $insertController->getSystemVariables(),
            'webinars' => \App\Models\Webinar::forUser(auth()->id())
                ->whereIn('status', ['scheduled', 'live', 'published'])
                ->select('id', 'name', 'slug')
                ->orderBy('name')
                ->get(),
            // Available languages for multi-language message translations
            'availableLanguages' => config('netsendo.languages'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'type' => 'required|in:broadcast,autoresponder',
            'day' => 'nullable|integer|min:0',
            'content' => 'nullable|string',
            'preheader' => 'nullable|string|max:500',
            'status' => 'required|in:draft,scheduled,sent',
            'contact_list_ids' => 'nullable|array',
            'contact_list_ids.*' => 'exists:contact_lists,id',
            'excluded_list_ids' => 'nullable|array',
            'excluded_list_ids.*' => 'exists:contact_lists,id',
            // Custom-field audience filters
            'include_field_filters' => 'nullable|array|max:20',
            'include_field_filters.*.custom_field_id' => 'required|integer|exists:custom_fields,id',
            'include_field_filters.*.operator' => 'required|string|in:' . implode(',', MessageFieldFilter::OPERATORS),
            'include_field_filters.*.values' => 'nullable|array|max:200',
            'include_field_filters.*.values.*' => 'nullable|string|max:255',
            'exclude_field_filters' => 'nullable|array|max:20',
            'exclude_field_filters.*.custom_field_id' => 'required|integer|exists:custom_fields,id',
            'exclude_field_filters.*.operator' => 'required|string|in:' . implode(',', MessageFieldFilter::OPERATORS),
            'exclude_field_filters.*.values' => 'nullable|array|max:200',
            'exclude_field_filters.*.values.*' => 'nullable|string|max:255',
            'include_field_filter_match' => 'nullable|in:all,any',
            'exclude_field_filter_match' => 'nullable|in:all,any',
            'send_at' => 'nullable|date',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'send_in_subscriber_timezone' => 'nullable|boolean',
            // New fields
            'template_id' => 'nullable|exists:templates,id',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
            'ab_enabled' => 'nullable|boolean',
            'ab_variant_subject' => 'nullable|string|max:255',
            'ab_variant_content' => 'nullable|string',
            'ab_split_percentage' => 'nullable|integer|min:10|max:90',
            'trigger_type' => 'nullable|in:signup,anniversary,birthday,inactivity,page_visit,custom,recent_subscribers,opened_message,not_opened_message',
            'trigger_config' => 'nullable|array',
            // PDF Attachments
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf|max:10240', // 10MB max per file
            // Tracked Links
            'tracked_links' => 'nullable|array',
            'tracked_links.*.url' => 'required_with:tracked_links|string|max:2048',
            'tracked_links.*.tracking_enabled' => 'nullable|boolean',
            'tracked_links.*.share_data_enabled' => 'nullable|boolean',
            'tracked_links.*.shared_fields' => 'nullable|array',
            'tracked_links.*.shared_fields.*' => 'string',
            'tracked_links.*.subscribe_to_list_ids' => 'nullable|array',
            'tracked_links.*.subscribe_to_list_ids.*' => 'integer|exists:contact_lists,id',
            'tracked_links.*.unsubscribe_from_list_ids' => 'nullable|array',
            'tracked_links.*.unsubscribe_from_list_ids.*' => 'integer|exists:contact_lists,id',
            // A/B Test Configuration
            'ab_test_config' => 'nullable|array',
            'ab_test_config.enabled' => 'nullable|boolean',
            'ab_test_config.test_type' => 'nullable|string|in:subject,content,sender,send_time,full',
            'ab_test_config.winning_metric' => 'nullable|string|in:open_rate,click_rate,conversion_rate',
            'ab_test_config.sample_percentage' => 'nullable|integer|min:5|max:50',
            'ab_test_config.test_duration_hours' => 'nullable|integer|min:1|max:72',
            'ab_test_config.auto_select_winner' => 'nullable|boolean',
            'ab_test_config.confidence_threshold' => 'nullable|integer|min:60|max:99',
            'ab_test_config.variants' => 'nullable|array',
            'ab_test_config.variants.*.variant_letter' => 'required_with:ab_test_config.variants|string|max:1',
            'ab_test_config.variants.*.subject' => 'nullable|string|max:255',
            'ab_test_config.variants.*.preheader' => 'nullable|string|max:500',
            'ab_test_config.variants.*.is_control' => 'nullable|boolean',
            // Campaign Tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            // CRM Contacts
            'crm_contact_ids' => 'nullable|array',
            'crm_contact_ids.*' => 'integer|exists:crm_contacts,id',
            'excluded_crm_contact_ids' => 'nullable|array',
            'excluded_crm_contact_ids.*' => 'integer|exists:crm_contacts,id',
            // Message Translations (multi-language)
            'translations' => 'nullable|array',
            'translations.*.language' => 'required|string|max:5',
            'translations.*.subject' => 'required|string|max:255',
            'translations.*.preheader' => 'nullable|string|max:500',
            'translations.*.content' => 'nullable|string',
        ]);

        // Verify access to lists (including shared lists for team members)
        if (!empty($validated['contact_list_ids'])) {
            $count = auth()->user()->accessibleLists()->whereIn('id', $validated['contact_list_ids'])->count();
            if ($count !== count($validated['contact_list_ids'])) {
                return back()->withErrors(['contact_list_ids' => 'Nieprawidłowa lista odbiorców.']);
            }
        }

        // Verify access to excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $count = auth()->user()->accessibleLists()->whereIn('id', $validated['excluded_list_ids'])->count();
            if ($count !== count($validated['excluded_list_ids'])) {
                return back()->withErrors(['excluded_list_ids' => 'Nieprawidłowa lista wykluczeń.']);
            }
        }

        // Verify mailbox ownership
        if (!empty($validated['mailbox_id'])) {
            $mailbox = auth()->user()->mailboxes()->find($validated['mailbox_id']);
            if (!$mailbox) {
                return back()->withErrors(['mailbox_id' => 'Nieprawidłowa skrzynka nadawcza.']);
            }
        }

        // Determine scheduled_at for CRON processing
        $scheduledAt = null;
        $sendAt = null;

        if ($validated['status'] === 'scheduled') {
            // Determine timezone for parsing the input date
            // Priority: Form timezone -> User timezone -> App timezone (UTC)
            $timezone = $validated['timezone'] ?? DateHelper::getUserTimezone();

            if (!empty($validated['send_at'])) {
                // Parse the input date in the user's/selected timezone, then convert to UTC for storage
                $sendAt = \Carbon\Carbon::parse($validated['send_at'], $timezone)->setTimezone('UTC');
                $scheduledAt = $sendAt;
            } else {
                // Immediate send
                $scheduledAt = now();
            }
        }

        $message = auth()->user()->messages()->create([
            'subject' => $validated['subject'],
            'type' => $validated['type'],
            'day' => $validated['type'] === 'autoresponder' ? ($validated['day'] ?? 0) : null,
            'content' => $validated['content'],
            'preheader' => $validated['preheader'] ?? null,

            // Autoresponders cannot be 'sent' (finished) immediately. If status is 'sent' (Active in UI), force 'scheduled'.
            'status' => ($validated['type'] === 'autoresponder' && $validated['status'] === 'sent') ? 'scheduled' : $validated['status'],
            'send_at' => $sendAt,
            'scheduled_at' => $scheduledAt,
            'time_of_day' => $validated['time_of_day'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'send_in_subscriber_timezone' => $validated['send_in_subscriber_timezone'] ?? false,
            'template_id' => $validated['template_id'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'channel' => 'email',
            'ab_enabled' => $validated['ab_enabled'] ?? false,
            'ab_variant_subject' => $validated['ab_variant_subject'] ?? null,
            'ab_variant_content' => $validated['ab_variant_content'] ?? null,
            'ab_split_percentage' => $validated['ab_split_percentage'] ?? 50,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'trigger_config' => $validated['trigger_config'] ?? null,
            'include_field_filter_match' => $validated['include_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
            'exclude_field_filter_match' => $validated['exclude_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
            // Queue messages: active only when not a draft
            'is_active' => $validated['status'] !== 'draft',
        ]);

        if (!empty($validated['contact_list_ids'])) {
            $message->contactLists()->sync($validated['contact_list_ids']);
        }

        // Sync excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $message->excludedLists()->sync($validated['excluded_list_ids']);
        }

        // Sync custom-field audience filters (before any recipient calculation)
        $this->syncFieldFilters($message, $validated);

        // Sync CRM contacts
        if (array_key_exists('crm_contact_ids', $validated)) {
            $message->crmContacts()->sync($validated['crm_contact_ids'] ?? []);
        }

        // Sync excluded CRM contacts
        if (array_key_exists('excluded_crm_contact_ids', $validated)) {
            $message->excludedCrmContacts()->sync($validated['excluded_crm_contact_ids'] ?? []);
        }

        // Handle PDF attachments upload
        if ($request->hasFile('attachments')) {
            $userId = auth()->id();
            $storagePath = "attachments/{$userId}/{$message->id}";

            foreach ($request->file('attachments') as $file) {
                $storedPath = $file->store($storagePath, 'local');

                MessageAttachment::create([
                    'message_id' => $message->id,
                    'user_id' => $userId,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        // For "send immediately" broadcast messages (status=scheduled, but no send_at date),
        // sync recipients immediately so stats are available right away.
        // For future scheduled messages (send_at is set), CRON will handle this when the time comes.
        $isImmediateSend = $validated['status'] === 'scheduled'
            && $validated['type'] === 'broadcast'
            && empty($validated['send_at']);

        if ($isImmediateSend) {
            $message->syncPlannedRecipients();
        }

        // Sync tracked links configuration
        $this->syncTrackedLinks($message, $request->input('tracked_links', []));

        // Sync A/B test configuration
        $this->syncAbTest($message, $request->input('ab_test_config', []));

        // Sync trigger with automation rule (non-blocking - log errors but don't fail)
        try {
            $this->syncMessageTrigger($message, $validated);
        } catch (\Exception $e) {
            \Log::warning('Failed to sync message trigger: ' . $e->getMessage(), [
                'message_id' => $message->id,
                'trigger_type' => $validated['trigger_type'] ?? null,
            ]);
        }

        // Sync campaign tags
        if (array_key_exists('tag_ids', $validated)) {
            $message->tags()->sync($validated['tag_ids'] ?? []);
        }

        // Sync message translations (multi-language)
        if (array_key_exists('translations', $validated)) {
            $message->translations()->delete();
            foreach ($validated['translations'] ?? [] as $translationData) {
                $message->translations()->create($translationData);
            }
        }

        // Determine success message based on status
        $successMessage = match($message->status) {
            'scheduled' => $message->send_at ? 'Wiadomość została zaplanowana.' : 'Wiadomość została wysłana do kolejki.',
            'draft' => 'Wiadomość została zapisana jako szkic.',
            default => 'Wiadomość została zapisana.',
        };

        return redirect()->route('messages.index')
            ->with('success', $successMessage)
            ->with('highlight_message_id', $message->id);
    }

    public function edit(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $message->load(['contactLists', 'excludedLists', 'crmContacts', 'excludedCrmContacts', 'template', 'mailbox', 'attachments', 'trackedLinks', 'tags', 'translations', 'fieldFilters.customField']);
        $defaultMailbox = Mailbox::getDefaultFor(auth()->id());
        $insertController = new InsertController();

        return Inertia::render('Message/Create', [
            'message' => [
                'id' => $message->id,
                'subject' => $message->subject,
                'type' => $message->type,
                'day' => $message->day,
                'content' => $message->content,
                'preheader' => $message->preheader,
                'status' => $message->status,
                'contact_list_ids' => $message->contactLists->pluck('id'),
                'excluded_list_ids' => $message->excludedLists->pluck('id'),
                // Convert stored UTC time back to user's timezone for display
                'send_at' => $message->send_at
                    ? $message->send_at->setTimezone($message->timezone ?? DateHelper::getUserTimezone())->format('Y-m-d H:i:s')
                    : null,
                'time_of_day' => $message->time_of_day ? substr($message->time_of_day, 0, 5) : null,
                'timezone' => $message->timezone,
                'send_in_subscriber_timezone' => $message->send_in_subscriber_timezone ?? false,
                'template_id' => $message->template_id,
                'mailbox_id' => $message->mailbox_id,
                'ab_enabled' => $message->ab_enabled,
                'ab_variant_subject' => $message->ab_variant_subject,
                'ab_variant_content' => $message->ab_variant_content,
                'ab_split_percentage' => $message->ab_split_percentage,
                'trigger_type' => $message->trigger_type,
                'trigger_config' => $message->trigger_config,
                // Custom-field audience filters
                'include_field_filters' => $this->formatFieldFilters($message, MessageFieldFilter::MODE_INCLUDE),
                'exclude_field_filters' => $this->formatFieldFilters($message, MessageFieldFilter::MODE_EXCLUDE),
                'include_field_filter_match' => $message->include_field_filter_match ?? MessageFieldFilter::MATCH_ALL,
                'exclude_field_filter_match' => $message->exclude_field_filter_match ?? MessageFieldFilter::MATCH_ALL,
                // Attachments
                'attachments' => $message->attachments->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'size' => $a->size,
                    'formatted_size' => $a->formatted_size,
                ]),
                // Tracked links configuration
                'tracked_links' => $message->trackedLinks->map(fn($tl) => [
                    'id' => $tl->id,
                    'url' => $tl->url,
                    'tracking_enabled' => $tl->tracking_enabled,
                    'share_data_enabled' => $tl->share_data_enabled,
                    'shared_fields' => $tl->shared_fields ?? [],
                    'subscribe_to_list_ids' => $tl->subscribe_to_list_ids ?? [],
                    'unsubscribe_from_list_ids' => $tl->unsubscribe_from_list_ids ?? [],
                ]),
                // A/B test configuration
                'ab_test_config' => $this->formatAbTestConfig($message),
                // Campaign Tags
                'tag_ids' => $message->tags->pluck('id'),
                // Message translations (multi-language)
                'translations' => $message->translations->map(fn($t) => [
                    'id' => $t->id,
                    'language' => $t->language,
                    'subject' => $t->subject,
                    'preheader' => $t->preheader,
                    'content' => $t->content,
                ]),
                // CRM Contacts
                'crm_contact_ids' => $message->crmContacts->pluck('id'),
                'excluded_crm_contact_ids' => $message->excludedCrmContacts->pluck('id'),
                // Pre-loaded CRM contacts for display
                'crm_contacts' => $message->crmContacts->map(fn($c) => [
                    'id' => $c->id,
                    'email' => $c->email,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'full_name' => trim("{$c->first_name} {$c->last_name}") ?: null,
                    'company' => $c->company,
                    'status' => $c->status,
                ]),
                'excluded_crm_contacts' => $message->excludedCrmContacts->map(fn($c) => [
                    'id' => $c->id,
                    'email' => $c->email,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'full_name' => trim("{$c->first_name} {$c->last_name}") ?: null,
                    'company' => $c->company,
                    'status' => $c->status,
                ]),
            ],
            'lists' => auth()->user()->accessibleLists()
                ->select('id', 'name', 'type', 'default_mailbox_id', 'contact_list_group_id')
                ->withCount(['subscribers' => function ($query) {
                    $query->where('contact_list_subscriber.status', 'active');
                }])
                ->with(['defaultMailbox:id,name,provider', 'group:id,name', 'tags:id,name'])
                ->get(),
            'groups' => auth()->user()->contactListGroups()
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                ]),
            'tags' => auth()->user()->tags()->select('id', 'name')->orderBy('name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'mailboxes' => auth()->user()->mailboxes()
                ->active()
                ->select('id', 'name', 'provider', 'from_email', 'is_default')
                ->get(),
            'templates' => Template::where('user_id', auth()->id())
                ->orWhere(fn($q) => $q->whereNull('user_id')->where('is_public', true))
                ->select('id', 'name', 'thumbnail', 'category', 'user_id', 'is_public', 'json_structure', 'content')
                ->latest()
                ->get(),
            'defaultMailbox' => $defaultMailbox ? [
                'id' => $defaultMailbox->id,
                'name' => $defaultMailbox->name,
                'provider' => $defaultMailbox->provider,
                'from_email' => $defaultMailbox->from_email,
            ] : null,
            // Insert snippets data
            'inserts' => Template::where('user_id', auth()->id())->inserts()->orderBy('name')->get(),
            'signatures' => Template::where('user_id', auth()->id())->signatures()->orderBy('name')->get(),
            'systemVariables' => $insertController->getSystemVariables(),
            'webinars' => \App\Models\Webinar::forUser(auth()->id())
                ->whereIn('status', ['scheduled', 'live', 'published'])
                ->select('id', 'name', 'slug')
                ->orderBy('name')
                ->get(),
            // Available languages for multi-language message translations
            'availableLanguages' => config('netsendo.languages'),
        ]);
    }

    public function update(Request $request, Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'type' => 'required|in:broadcast,autoresponder',
            'day' => 'nullable|integer|min:0',
            'content' => 'nullable|string',
            'preheader' => 'nullable|string|max:500',
            'status' => 'required|in:draft,scheduled,sent',
            'contact_list_ids' => 'nullable|array',
            'contact_list_ids.*' => 'exists:contact_lists,id',
            'excluded_list_ids' => 'nullable|array',
            'excluded_list_ids.*' => 'exists:contact_lists,id',
            // Custom-field audience filters
            'include_field_filters' => 'nullable|array|max:20',
            'include_field_filters.*.custom_field_id' => 'required|integer|exists:custom_fields,id',
            'include_field_filters.*.operator' => 'required|string|in:' . implode(',', MessageFieldFilter::OPERATORS),
            'include_field_filters.*.values' => 'nullable|array|max:200',
            'include_field_filters.*.values.*' => 'nullable|string|max:255',
            'exclude_field_filters' => 'nullable|array|max:20',
            'exclude_field_filters.*.custom_field_id' => 'required|integer|exists:custom_fields,id',
            'exclude_field_filters.*.operator' => 'required|string|in:' . implode(',', MessageFieldFilter::OPERATORS),
            'exclude_field_filters.*.values' => 'nullable|array|max:200',
            'exclude_field_filters.*.values.*' => 'nullable|string|max:255',
            'include_field_filter_match' => 'nullable|in:all,any',
            'exclude_field_filter_match' => 'nullable|in:all,any',
            'send_at' => 'nullable|date',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'send_in_subscriber_timezone' => 'nullable|boolean',
            // New fields
            'template_id' => 'nullable|exists:templates,id',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
            'ab_enabled' => 'nullable|boolean',
            'ab_variant_subject' => 'nullable|string|max:255',
            'ab_variant_content' => 'nullable|string',
            'ab_split_percentage' => 'nullable|integer|min:10|max:90',
            'trigger_type' => 'nullable|in:signup,anniversary,birthday,inactivity,page_visit,custom,recent_subscribers,opened_message,not_opened_message',
            'trigger_config' => 'nullable|array',
            // PDF Attachments
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf|max:10240', // 10MB max per file
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
            // Tracked Links
            'tracked_links' => 'nullable|array',
            'tracked_links.*.url' => 'required_with:tracked_links|string|max:2048',
            'tracked_links.*.tracking_enabled' => 'nullable|boolean',
            'tracked_links.*.share_data_enabled' => 'nullable|boolean',
            'tracked_links.*.shared_fields' => 'nullable|array',
            'tracked_links.*.shared_fields.*' => 'string',
            'tracked_links.*.subscribe_to_list_ids' => 'nullable|array',
            'tracked_links.*.subscribe_to_list_ids.*' => 'integer|exists:contact_lists,id',
            'tracked_links.*.unsubscribe_from_list_ids' => 'nullable|array',
            'tracked_links.*.unsubscribe_from_list_ids.*' => 'integer|exists:contact_lists,id',
            // A/B Test Configuration
            'ab_test_config' => 'nullable|array',
            'ab_test_config.enabled' => 'nullable|boolean',
            'ab_test_config.test_type' => 'nullable|string|in:subject,content,sender,send_time,full',
            'ab_test_config.winning_metric' => 'nullable|string|in:open_rate,click_rate,conversion_rate',
            'ab_test_config.sample_percentage' => 'nullable|integer|min:5|max:50',
            'ab_test_config.test_duration_hours' => 'nullable|integer|min:1|max:72',
            'ab_test_config.auto_select_winner' => 'nullable|boolean',
            'ab_test_config.confidence_threshold' => 'nullable|integer|min:60|max:99',
            'ab_test_config.variants' => 'nullable|array',
            'ab_test_config.variants.*.variant_letter' => 'required_with:ab_test_config.variants|string|max:1',
            'ab_test_config.variants.*.subject' => 'nullable|string|max:255',
            'ab_test_config.variants.*.preheader' => 'nullable|string|max:500',
            'ab_test_config.variants.*.is_control' => 'nullable|boolean',
            // Campaign Tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            // CRM Contacts
            'crm_contact_ids' => 'nullable|array',
            'crm_contact_ids.*' => 'integer|exists:crm_contacts,id',
            'excluded_crm_contact_ids' => 'nullable|array',
            'excluded_crm_contact_ids.*' => 'integer|exists:crm_contacts,id',
            // Message Translations (multi-language)
            'translations' => 'nullable|array',
            'translations.*.language' => 'required|string|max:5',
            'translations.*.subject' => 'required|string|max:255',
            'translations.*.preheader' => 'nullable|string|max:500',
            'translations.*.content' => 'nullable|string',
        ]);

        // Verify access to lists (including shared lists for team members)
        if (!empty($validated['contact_list_ids'])) {
            $count = auth()->user()->accessibleLists()->whereIn('id', $validated['contact_list_ids'])->count();
            if ($count !== count($validated['contact_list_ids'])) {
                return back()->withErrors(['contact_list_ids' => 'Nieprawidłowa lista odbiorców.']);
            }
        }

        // Verify access to excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $count = auth()->user()->accessibleLists()->whereIn('id', $validated['excluded_list_ids'])->count();
            if ($count !== count($validated['excluded_list_ids'])) {
                return back()->withErrors(['excluded_list_ids' => 'Nieprawidłowa lista wykluczeń.']);
            }
        }

        // Verify mailbox ownership
        if (!empty($validated['mailbox_id'])) {
            $mailbox = auth()->user()->mailboxes()->find($validated['mailbox_id']);
            if (!$mailbox) {
                return back()->withErrors(['mailbox_id' => 'Nieprawidłowa skrzynka nadawcza.']);
            }
        }

        // Determine scheduled_at for CRON processing
        $scheduledAt = null;
        $sendAt = null;

        if ($validated['status'] === 'scheduled') {
            // Determine timezone for parsing the input date
            // Priority: Form timezone -> User timezone -> App timezone (UTC)
            $timezone = $validated['timezone'] ?? DateHelper::getUserTimezone();

            if (!empty($validated['send_at'])) {
                // Parse the input date in the user's/selected timezone, then convert to UTC for storage
                $sendAt = \Carbon\Carbon::parse($validated['send_at'], $timezone)->setTimezone('UTC');
                $scheduledAt = $sendAt;
            } else {
                // Immediate send
                $scheduledAt = now();
            }
        }

        $message->update([
            'subject' => $validated['subject'],
            'type' => $validated['type'],
            'day' => $validated['type'] === 'autoresponder' ? ($validated['day'] ?? 0) : null,
            'content' => $validated['content'],
            'preheader' => $validated['preheader'] ?? null,
            // Autoresponders cannot be 'sent' (finished) immediately. If status is 'sent' (Active in UI), force 'scheduled'.
            'status' => ($validated['type'] === 'autoresponder' && $validated['status'] === 'sent') ? 'scheduled' : $validated['status'],
            'send_at' => $sendAt,
            'scheduled_at' => $scheduledAt,
            'time_of_day' => $validated['time_of_day'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'send_in_subscriber_timezone' => $validated['send_in_subscriber_timezone'] ?? false,
            'template_id' => $validated['template_id'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'ab_enabled' => $validated['ab_enabled'] ?? false,
            'ab_variant_subject' => $validated['ab_variant_subject'] ?? null,
            'ab_variant_content' => $validated['ab_variant_content'] ?? null,
            'ab_split_percentage' => $validated['ab_split_percentage'] ?? 50,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'trigger_config' => $validated['trigger_config'] ?? null,
            'include_field_filter_match' => $validated['include_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
            'exclude_field_filter_match' => $validated['exclude_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
            // Queue messages: active only when not a draft
            'is_active' => $validated['status'] !== 'draft',
        ]);

        if (array_key_exists('contact_list_ids', $validated)) {
            $message->contactLists()->sync($validated['contact_list_ids'] ?? []);
        }

        // Sync excluded lists
        if (array_key_exists('excluded_list_ids', $validated)) {
            $message->excludedLists()->sync($validated['excluded_list_ids'] ?? []);
        }

        // Sync custom-field audience filters (before any recipient calculation)
        $this->syncFieldFilters($message, $validated);

        // Sync CRM contacts
        if (array_key_exists('crm_contact_ids', $validated)) {
            $message->crmContacts()->sync($validated['crm_contact_ids'] ?? []);
        }

        // Sync excluded CRM contacts
        if (array_key_exists('excluded_crm_contact_ids', $validated)) {
            $message->excludedCrmContacts()->sync($validated['excluded_crm_contact_ids'] ?? []);
        }

        // Handle attachment removals
        if (!empty($validated['remove_attachment_ids'])) {
            $attachmentsToRemove = $message->attachments()
                ->whereIn('id', $validated['remove_attachment_ids'])
                ->get();

            foreach ($attachmentsToRemove as $attachment) {
                $attachment->delete(); // Model auto-deletes file from storage
            }
        }

        // Handle new PDF attachments upload
        if ($request->hasFile('attachments')) {
            $userId = auth()->id();
            $storagePath = "attachments/{$userId}/{$message->id}";

            foreach ($request->file('attachments') as $file) {
                $storedPath = $file->store($storagePath, 'local');

                MessageAttachment::create([
                    'message_id' => $message->id,
                    'user_id' => $userId,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        // For "send immediately" broadcast messages (status=scheduled, but no send_at date),
        // sync recipients immediately so stats are available right away.
        // For future scheduled messages (send_at is set), CRON will handle this when the time comes.
        // For "send immediately" broadcast messages (status=scheduled, but no send_at date),
        // sync recipients immediately so stats are available right away.
        // For future scheduled messages (send_at is set), CRON will handle this when the time comes.
        $isImmediateSend = $validated['status'] === 'scheduled'
            && $validated['type'] === 'broadcast'
            && empty($validated['send_at']);

        if ($isImmediateSend) {
            $message->syncPlannedRecipients();
        }

        // Sync tracked links configuration
        $this->syncTrackedLinks($message, $request->input('tracked_links', []));

        // Sync A/B test configuration
        $this->syncAbTest($message, $request->input('ab_test_config', []));

        // Sync trigger with automation rule (non-blocking - log errors but don't fail)
        try {
            $this->syncMessageTrigger($message, $validated);
        } catch (\Exception $e) {
            \Log::warning('Failed to sync message trigger: ' . $e->getMessage(), [
                'message_id' => $message->id,
                'trigger_type' => $validated['trigger_type'] ?? null,
            ]);
        }

        // Sync campaign tags
        if (array_key_exists('tag_ids', $validated)) {
            $message->tags()->sync($validated['tag_ids'] ?? []);
        }

        // Sync message translations (multi-language)
        if (array_key_exists('translations', $validated)) {
            $message->translations()->delete();
            foreach ($validated['translations'] ?? [] as $translationData) {
                $message->translations()->create($translationData);
            }
        }

        // Determine success message based on status
        $successMessage = match($message->status) {
            'scheduled' => $message->send_at ? 'Wiadomość została zaplanowana.' : 'Wiadomość została wysłana do kolejki.',
            'draft' => 'Wiadomość została zapisana jako szkic.',
            default => 'Wiadomość została zaktualizowana.',
        };

        return redirect()->route('messages.index')
            ->with('success', $successMessage)
            ->with('highlight_message_id', $message->id);
    }

    /**
     * Duplicate a message as a draft
     */
    public function duplicate(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $newMessage = app(MessageEditorService::class)->duplicate($message);

        return response()->json([
            'success' => true,
            'message' => $newMessage,
            'redirect_url' => route('messages.edit', $newMessage->id),
        ]);
    }

    /**
     * Resend a message to new subscribers only
     * Skips subscribers who already received the message (have 'sent' status in queue)
     */
    public function resend(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        // Only allow resend for sent or scheduled broadcast messages
        if ($message->type !== 'broadcast') {
            return response()->json([
                'success' => false,
                'message' => 'Tylko wiadomości jednorazowe (broadcast) mogą być ponownie wysłane.'
            ], 422);
        }

        // Get subscribers who already received this message
        $alreadySentSubscriberIds = $message->queueEntries()
            ->where('status', MessageQueueEntry::STATUS_SENT)
            ->pluck('subscriber_id')
            ->toArray();

        // Get current unique recipients
        $currentRecipients = $message->getUniqueRecipients();

        // Filter out subscribers who already received the message
        $newRecipients = $currentRecipients->filter(function($subscriber) use ($alreadySentSubscriberIds) {
            return !in_array($subscriber->id, $alreadySentSubscriberIds);
        });

        $newCount = $newRecipients->count();

        if ($newCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Brak nowych odbiorców do wysłania. Wszyscy subskrybenci już otrzymali tę wiadomość.'
            ], 422);
        }

        // Reset message status to scheduled
        $message->update([
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        // Create queue entries only for new recipients
        foreach ($newRecipients as $subscriber) {
            // Check if entry already exists (might be planned or failed)
            $existingEntry = $message->queueEntries()
                ->where('subscriber_id', $subscriber->id)
                ->first();

            if ($existingEntry) {
                // Reset failed/skipped entries to planned
                if (in_array($existingEntry->status, [MessageQueueEntry::STATUS_FAILED, MessageQueueEntry::STATUS_SKIPPED])) {
                    $existingEntry->update([
                        'status' => MessageQueueEntry::STATUS_PLANNED,
                        'planned_at' => now(),
                        'error_message' => null,
                    ]);
                }
            } else {
                // Create new entry. The message is already due, so CRON may
                // sync the same recipient in between (issue #30).
                try {
                    $message->queueEntries()->create([
                        'subscriber_id' => $subscriber->id,
                        'status' => MessageQueueEntry::STATUS_PLANNED,
                        'planned_at' => now(),
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // Planned concurrently by CRON — nothing left to do
                }
            }
        }

        // Update planned recipients count
        $message->update([
            'planned_recipients_count' => $message->queueEntries()
                ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                ->count() + $message->sent_count,
        ]);

        return response()->json([
            'success' => true,
            'new_recipients' => $newCount,
            'message' => "Wiadomość została zaplanowana do wysłania. Nowych odbiorców: {$newCount}."
        ]);
    }

    /**
     * Resend a message to subscribers who had errors (failed status)
     * Useful for SendGrid free account limits - retry the next day
     */
    public function resendToFailed(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $failedCount = app(MessageEditorService::class)->resendToFailed($message);

        if ($failedCount === 0) {
            return back()->with('error', __('messages.stats.queue.no_failed_recipients'));
        }

        return back()->with('success', __('messages.stats.queue.resend_scheduled', ['count' => $failedCount]));
    }

    public function destroy(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $message->delete();

        return redirect()->route('messages.index')
            ->with('success', 'Wiadomość została usunięta.');
    }

    public function stats(Request $request, Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $message->load(['contactLists', 'excludedLists']);

        // Get queue statistics from message_queue_entries
        $queueStats = $message->getQueueStats();

        // For queue/autoresponder: use queue entry statistics
        // For broadcast: if already sent, use sent_count; otherwise calculate recipients
        if ($message->isQueueType()) {
            // Kolejka (autoresponder) - używamy statystyk z queue entries
            $totalSent = $queueStats['sent'];
            $plannedRecipients = $message->planned_recipients_count ?? $message->getUniqueRecipients()->count();
        } else {
            // Broadcast - jeśli wysłano, używamy queueStats; w przeciwnym razie przeliczamy
            if ($queueStats['total'] > 0) {
                $totalSent = $queueStats['sent'];
                $plannedRecipients = $queueStats['total'];
            } else {
                $totalSent = $message->getUniqueRecipients()->count();
                $plannedRecipients = $totalSent;
            }
        }

        // Unikaj dzielenia przez zero w obliczeniach procentowych
        $denominator = $totalSent > 0 ? $totalSent : 1;

        // Pobieranie statystyk otwarć
        $opens = EmailOpen::where('message_id', $message->id)->count();
        $uniqueOpens = EmailOpen::where('message_id', $message->id)->distinct('subscriber_id')->count('subscriber_id');

        // Pobieranie statystyk kliknięć
        $clicks = EmailClick::where('message_id', $message->id)->count();
        $uniqueClicks = EmailClick::where('message_id', $message->id)->distinct('subscriber_id')->count('subscriber_id');

        // Read time statistics
        $readTimeStats = EmailReadSession::getReadTimeStats($message->id);

        // Read time histogram (distribution)
        $readTimeHistogram = $this->getReadTimeHistogram($message->id);

        // Top readers (subscribers with longest read times)
        $topReaders = EmailReadSession::completed()
            ->forMessage($message->id)
            ->with('subscriber:id,email,first_name,last_name')
            ->orderByDesc('read_time_seconds')
            ->limit(10)
            ->get()
            ->map(fn($session) => [
                'email' => $session->subscriber?->email ?? 'Unknown',
                'name' => trim(($session->subscriber?->first_name ?? '') . ' ' . ($session->subscriber?->last_name ?? '')),
                'read_time' => $session->read_time_formatted,
                'read_time_seconds' => $session->read_time_seconds,
                'read_at' => DateHelper::formatForUser($session->started_at),
            ]);

        // Process recent opens with sorting, search, and pagination
        $opensQuery = EmailOpen::where('message_id', $message->id)
            ->with('subscriber:id,email,first_name,last_name');

        // Search filter for opens
        if ($request->filled('search_opens')) {
            $search = $request->input('search_opens');
            $opensQuery->whereHas('subscriber', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Get total count BEFORE sorting/pagination (for display)
        $opensTotal = (clone $opensQuery)->distinct('subscriber_id')->count('subscriber_id');

        if ($request->input('sort_opens_by') && $request->input('sort_opens_dir')) {
            $sortBy = $request->input('sort_opens_by'); // 'email' or 'time'
            $dir = $request->input('sort_opens_dir') === 'asc' ? 'asc' : 'desc';

            if ($sortBy === 'email') {
                $opensQuery->join('subscribers', 'email_opens.subscriber_id', '=', 'subscribers.id')
                    ->orderBy('subscribers.email', $dir)
                    ->select('email_opens.*'); // Avoid column collision
            } else {
                $opensQuery->orderBy('opened_at', $dir);
            }
        } else {
            $opensQuery->latest('opened_at');
        }

        $recentOpens = $opensQuery->paginate(10, ['*'], 'opens_page')
            ->withQueryString()
            ->through(fn($open) => [
                'id' => $open->id,
                'subscriber_id' => $open->subscriber_id,
                'email' => $open->subscriber?->email ?? 'Nieznany',
                'name' => trim(($open->subscriber?->first_name ?? '') . ' ' . ($open->subscriber?->last_name ?? '')),
                'ip' => $open->ip_address,
                'occurred_at' => DateHelper::formatForUser($open->opened_at),
            ]);

        // Process recent clicks with sorting, search, and pagination
        $clicksQuery = EmailClick::where('message_id', $message->id)
            ->with('subscriber:id,email,first_name,last_name');

        // Search filter for clicks - search by email, name, or URL
        if ($request->filled('search_clicks')) {
            $search = $request->input('search_clicks');
            $clicksQuery->where(function ($q) use ($search) {
                $q->whereHas('subscriber', function ($sub) use ($search) {
                    $sub->where('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->orWhere('url', 'like', "%{$search}%");
            });
        }

        // Get total count BEFORE sorting/pagination (for display)
        $clicksTotal = (clone $clicksQuery)->distinct('subscriber_id')->count('subscriber_id');

        if ($request->input('sort_clicks_by') && $request->input('sort_clicks_dir')) {
            $sortBy = $request->input('sort_clicks_by'); // 'email', 'url', 'time'
            $dir = $request->input('sort_clicks_dir') === 'asc' ? 'asc' : 'desc';

            if ($sortBy === 'email') {
                $clicksQuery->join('subscribers', 'email_clicks.subscriber_id', '=', 'subscribers.id')
                    ->orderBy('subscribers.email', $dir)
                    ->select('email_clicks.*');
            } elseif ($sortBy === 'url') {
                $clicksQuery->orderBy('url', $dir);
            } else {
                $clicksQuery->orderBy('clicked_at', $dir);
            }
        } else {
            $clicksQuery->latest('clicked_at');
        }

        $recentClicks = $clicksQuery->paginate(10, ['*'], 'clicks_page')
            ->withQueryString()
            ->through(fn($click) => [
                'id' => $click->id,
                'subscriber_id' => $click->subscriber_id,
                'email' => $click->subscriber?->email ?? 'Nieznany',
                'name' => trim(($click->subscriber?->first_name ?? '') . ' ' . ($click->subscriber?->last_name ?? '')),
                'url' => $click->url,
                'ip' => $click->ip_address,
                'occurred_at' => DateHelper::formatForUser($click->clicked_at),
            ]);

        return Inertia::render('Message/Stats', [
            'message' => [
                'id' => $message->id,
                'subject' => $message->subject,
                'sent_at' => DateHelper::formatForUser($message->send_at ?? $message->created_at),
                'scheduled_at' => $message->scheduled_at
                    ? DateHelper::formatForUser($message->scheduled_at)
                    : null,
                'status' => $message->status,
                'type' => $message->type,
                'is_active' => $message->is_active ?? true,
                'recipients_calculated_at' => $message->recipients_calculated_at
                    ? DateHelper::formatForUser($message->recipients_calculated_at)
                    : null,
            ],
            'stats' => [
                'sent' => $totalSent,
                'planned_recipients' => $plannedRecipients,
                'opens' => $opens,
                'unique_opens' => $uniqueOpens,
                'open_rate' => round(($uniqueOpens / $denominator) * 100, 1),
                'clicks' => $clicks,
                'unique_clicks' => $uniqueClicks,
                'click_rate' => round(($uniqueClicks / $denominator) * 100, 1),
                'click_to_open_rate' => $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 1) : 0,
            ],
            'read_time_stats' => $readTimeStats,
            'read_time_histogram' => $readTimeHistogram,
            'top_readers' => $topReaders,
            'queue_stats' => $queueStats,
            // Show the best queue entry per subscriber email (prioritizing successful statuses)
            // Uses ROW_NUMBER to pick: sent > failed > queued > planned > skipped
            // Excludes entries where subscriber was removed from list (they are not real recipients)
            'recipients' => $message->queueEntries()
                ->select('message_queue_entries.*')
                ->join('subscribers', 'message_queue_entries.subscriber_id', '=', 'subscribers.id')
                ->with('subscriber:id,email,first_name,last_name,status')
                ->whereIn('message_queue_entries.id', function ($query) use ($message) {
                    $query->select('id')
                        ->fromRaw("(
                            SELECT mq.id, s.email,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY s.email
                                       ORDER BY
                                           CASE mq.status
                                               WHEN 'sent' THEN 1
                                               WHEN 'failed' THEN 2
                                               WHEN 'queued' THEN 3
                                               WHEN 'planned' THEN 4
                                               WHEN 'skipped' THEN 5
                                               ELSE 6
                                           END,
                                           mq.id DESC
                                   ) as rn
                            FROM message_queue_entries mq
                            JOIN subscribers s ON mq.subscriber_id = s.id
                            WHERE mq.message_id = {$message->id}
                              AND NOT (mq.status = 'skipped' AND mq.error_message LIKE '%removed from list%')
                        ) as ranked")
                        ->where('rn', 1);
                })
                // Also filter out any remaining skipped entries for removed subscribers
                ->where(function ($q) {
                    $q->where('message_queue_entries.status', '!=', 'skipped')
                      ->orWhereNull('message_queue_entries.error_message')
                      ->orWhere('message_queue_entries.error_message', 'NOT LIKE', '%removed from list%');
                })
                // Search filter for recipients
                ->when($request->filled('search_recipients'), function ($q) use ($request) {
                    $search = $request->input('search_recipients');
                    $q->where(function ($q2) use ($search) {
                        $q2->where('subscribers.email', 'like', "%{$search}%")
                          ->orWhere('subscribers.first_name', 'like', "%{$search}%")
                          ->orWhere('subscribers.last_name', 'like', "%{$search}%");
                    });
                })
                ->orderByRaw("CASE message_queue_entries.status
                    WHEN 'failed' THEN 1
                    WHEN 'queued' THEN 2
                    WHEN 'planned' THEN 3
                    WHEN 'sent' THEN 4
                    WHEN 'skipped' THEN 5
                    ELSE 6 END")
                ->orderByDesc('sent_at')
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'recipients_page')
                ->withQueryString()
                ->through(fn($entry) => [
                    'id' => $entry->id,
                    'email' => $entry->subscriber?->email ?? 'Nieznany',
                    'name' => trim(($entry->subscriber?->first_name ?? '') . ' ' . ($entry->subscriber?->last_name ?? '')),
                    'subscriber_status' => $entry->subscriber?->status ?? 'unknown',
                    'queue_status' => $entry->status,
                    'planned_at' => $entry->planned_at ? DateHelper::formatForUser($entry->planned_at) : null,
                    'sent_at' => $entry->sent_at ? DateHelper::formatForUser($entry->sent_at) : null,
                    'error' => $entry->error_message,
                ]),
            'recent_activity' => [
                'opens' => $recentOpens,
                'opens_total' => $opensTotal,
                'clicks' => $recentClicks,
                'clicks_total' => $clicksTotal,
            ]
        ]);
    }

    /**
     * Get all subscriber IDs from opens log (for Select All functionality)
     * Returns unique subscriber IDs matching the current filter
     */
    public function allOpensSubscriberIds(Request $request, Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $query = EmailOpen::where('message_id', $message->id);

        // Apply same search filter as in stats()
        if ($request->filled('search_opens')) {
            $search = $request->input('search_opens');
            $query->whereHas('subscriber', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Get unique subscriber IDs (not null)
        $subscriberIds = $query->whereNotNull('subscriber_id')
            ->distinct()
            ->pluck('subscriber_id')
            ->toArray();

        return response()->json([
            'subscriber_ids' => $subscriberIds,
            'total' => count($subscriberIds),
        ]);
    }

    /**
     * Get all subscriber IDs from clicks log (for Select All functionality)
     * Returns unique subscriber IDs matching the current filter
     */
    public function allClicksSubscriberIds(Request $request, Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $query = EmailClick::where('message_id', $message->id);

        // Apply same search filter as in stats() - search by email, name, or URL
        if ($request->filled('search_clicks')) {
            $search = $request->input('search_clicks');
            $query->where(function ($q) use ($search) {
                $q->whereHas('subscriber', function ($sub) use ($search) {
                    $sub->where('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->orWhere('url', 'like', "%{$search}%");
            });
        }

        // Get unique subscriber IDs (not null)
        $subscriberIds = $query->whereNotNull('subscriber_id')
            ->distinct()
            ->pluck('subscriber_id')
            ->toArray();

        return response()->json([
            'subscriber_ids' => $subscriberIds,
            'total' => count($subscriberIds),
        ]);
    }

    public function show(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        if ($message->status === 'draft') {
            return redirect()->route('messages.edit', $message);
        }

        return redirect()->route('messages.stats', $message);
    }

    /**
     * Send a test email
     */
    /**
     * Send a test email
     */
    public function test(Request $request, \App\Services\Mail\MailProviderService $providerService, \App\Services\PlaceholderService $placeholderService)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'preheader' => 'nullable|string|max:500',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
            'subscriber_id' => 'nullable|exists:subscribers,id',
            'contact_list_ids' => 'nullable|array',
        ]);

        $mailbox = null;

        // Get mailbox
        if ($validated['mailbox_id']) {
            $mailbox = Mailbox::where('user_id', auth()->id())->find($validated['mailbox_id']);
        } else {
            $mailbox = Mailbox::getDefaultFor(auth()->id());
        }

        if (!$mailbox) {
            return response()->json(['error' => 'No mailbox configured'], 422);
        }

        try {
            $editor = app(MessageEditorService::class);

            // Get subscriber for placeholder substitution
            // Priority: 1) subscriber by test email, 2) subscriber_id from request, 3) first from selected lists
            $subscriber = $editor->findTestSubscriber(
                auth()->id(),
                $validated['email'],
                $validated['subscriber_id'] ?? null,
                $validated['contact_list_ids'] ?? []
            );

            // A real subscriber goes through PlaceholderService, otherwise sample data
            $rendered = $editor->render(
                $validated['content'],
                $validated['subject'],
                $validated['preheader'] ?? null,
                $subscriber,
                $subscriber ? null : $editor->sampleData($validated['email'])
            );

            // Use the proper mail provider service to send HTML email
            $editor->deliverTest($mailbox, $validated['email'], $rendered['subject'], $rendered['content']);

            return response()->json(['success' => true, 'message' => 'Test email sent']);
        } catch (\Exception $e) {
            \Log::error('Test email failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate preview content with placeholders substituted
     */
    public function preview(Request $request, \App\Services\PlaceholderService $placeholderService)
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'preheader' => 'nullable|string|max:500',
            'subscriber_id' => 'nullable|exists:subscribers,id',
        ]);

        try {
            $subscriber = null;
            $subscriberId = $validated['subscriber_id'] ?? null;

            if ($subscriberId) {
                // Security: only the user's own subscriber may personalise the preview
                $subscriber = \App\Models\Subscriber::where('user_id', auth()->id())->find($subscriberId);
            }

            $rendered = app(MessageEditorService::class)->render(
                $validated['content'],
                $validated['subject'] ?? '',
                $validated['preheader'] ?? null,
                $subscriber
            );

            return response()->json([
                'success' => true,
                'content' => $rendered['content'],
                'subject' => $rendered['subject'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get subscribers list for preview dropdown
     */
    public function previewSubscribers(Request $request)
    {
        $validated = $request->validate([
            'contact_list_ids' => 'nullable|array',
            'search' => 'nullable|string|min:2',
        ]);

        // Get user's contact list IDs for security filtering
        $userListIds = auth()->user()->contactLists()->pluck('id')->toArray();

        // Filter by specific lists if provided, otherwise use all user's lists
        $filterListIds = !empty($validated['contact_list_ids'])
            ? array_intersect($validated['contact_list_ids'], $userListIds)
            : $userListIds;

        $query = \App\Models\Subscriber::whereHas('contactLists', function ($q) use ($filterListIds) {
                $q->whereIn('contact_lists.id', $filterListIds);
            })
            ->select('id', 'email', 'first_name', 'last_name')
            ->active(); // Only active subscribers

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $subscribers = $query->limit(10)->get();

        return response()->json([
            'subscribers' => $subscribers
        ]);
    }

    /**
     * Inject preheader into HTML content
     */
    private function injectPreheader(string $content, string $preheader): string
    {
        return app(MessageEditorService::class)->injectPreheader($content, $preheader);
    }

    /**
     * Toggle the active status of a queue message
     */
    public function toggleActive(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        // Only allow toggling for autoresponder/queue type messages
        if (!$message->isQueueType()) {
            return response()->json([
                'success' => false,
                'message' => 'Tylko wiadomości typu Kolejka mogą mieć status aktywności.'
            ], 422);
        }

        // Activation also promotes a draft to `scheduled` (see setActive)
        app(MessageEditorService::class)->setActive($message, !($message->is_active ?? true));

        return response()->json([
            'success' => true,
            'is_active' => $message->is_active,
            'status' => $message->status,
            'message' => $message->is_active ? 'Wiadomość została aktywowana.' : 'Wiadomość została dezaktywowana.'
        ]);
    }

    /**
     * Sync message trigger with AutomationRule
     * Creates/updates an automation rule when message has a trigger configured
     */
    protected function syncMessageTrigger(Message $message, array $data): void
    {
        app(MessageEditorService::class)->syncTrigger($message, $data);
    }

    /**
     * Sync tracked links configuration for the message.
     * Creates/updates/deletes MessageTrackedLink records based on form data.
     */
    /**
     * Persist the custom-field audience filters for both sides of the message.
     *
     * Absent keys leave the stored filters alone (partial updates from the API),
     * an empty array clears that side.
     */
    /**
     * Shape stored filters for the form, carrying the field label so the row can
     * render before the available-fields request comes back.
     */
    protected function formatFieldFilters(Message $message, string $mode): array
    {
        return $message->fieldFilters
            ->where('mode', $mode)
            ->sortBy('sort_order')
            ->map(fn (MessageFieldFilter $filter) => [
                'custom_field_id' => $filter->custom_field_id,
                'operator' => $filter->operator,
                'values' => $filter->values ?? [],
                'field_label' => $filter->customField?->label,
                'field_type' => $filter->customField?->type,
            ])
            ->values()
            ->all();
    }

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

    protected function syncTrackedLinks(Message $message, array $trackedLinks): void
    {
        app(MessageEditorService::class)->syncTrackedLinks($message, $trackedLinks);
    }

    /**
     * Sync A/B test configuration for the message.
     * Creates/updates/deletes AbTest and AbTestVariant records based on form data.
     */
    protected function syncAbTest(Message $message, array $config): void
    {
        app(MessageEditorService::class)->syncAbTest($message, $config);
    }

    /**
     * Format existing A/B test as ab_test_config for frontend.
     */
    protected function formatAbTestConfig(Message $message): array
    {
        return app(MessageEditorService::class)->formatAbTestConfig($message);
    }

    /**
     * Get read time histogram data for chart
     */
    protected function getReadTimeHistogram(int $messageId): array
    {
        $sessions = EmailReadSession::completed()
            ->forMessage($messageId)
            ->pluck('read_time_seconds');

        // Define buckets: 0-10s, 10-30s, 30-60s, 60-120s, 120s+
        $buckets = [
            '0-10s' => 0,
            '10-30s' => 0,
            '30-60s' => 0,
            '1-2min' => 0,
            '2min+' => 0,
        ];

        foreach ($sessions as $seconds) {
            if ($seconds <= 10) {
                $buckets['0-10s']++;
            } elseif ($seconds <= 30) {
                $buckets['10-30s']++;
            } elseif ($seconds <= 60) {
                $buckets['30-60s']++;
            } elseif ($seconds <= 120) {
                $buckets['1-2min']++;
            } else {
                $buckets['2min+']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
            'total' => $sessions->count(),
        ];
    }

    /**
     * Get detailed queue schedule statistics for an autoresponder message.
     * Shows breakdown by day of scheduled delivery and missed recipients.
     */
    public function queueScheduleStats(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$message->isQueueType()) {
            return response()->json([
                'success' => false,
                'message' => 'Ta funkcja jest dostępna tylko dla wiadomości typu Kolejka.',
            ], 422);
        }

        $stats = $message->getQueueScheduleStats();

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'subject' => $message->subject,
                'day' => $message->day,
                'is_active' => $message->is_active ?? true,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Send message to missed recipients (those who joined before the queue day offset).
     * Creates queue entries for them and schedules for immediate dispatch.
     */
    public function sendToMissedRecipients(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$message->isQueueType()) {
            return response()->json([
                'success' => false,
                'message' => 'Ta funkcja jest dostępna tylko dla wiadomości typu Kolejka.',
            ], 422);
        }

        if (!($message->is_active ?? true)) {
            return response()->json([
                'success' => false,
                'message' => 'Wiadomość musi być aktywna, aby wysyłać do pominiętych subskrybentów.',
            ], 422);
        }

        // No limit — process ALL missed subscribers, not just the first 100
        $stats = $message->getQueueScheduleStats(null);
        $missedSubscribers = $stats['missed_subscribers'] ?? [];

        if (empty($missedSubscribers)) {
            return response()->json([
                'success' => false,
                'message' => 'Brak pominiętych subskrybentów do wysłania.',
            ], 422);
        }

        $result = app(MessageEditorService::class)->queueMissedRecipients($message, $missedSubscribers);
        $created = $result['created'];
        $alreadyExists = $result['already_exists'];

        return response()->json([
            'success' => true,
            'created' => $created,
            'already_exists' => $alreadyExists,
            'message' => "Zaplanowano wysyłkę do {$created} pominiętych subskrybentów.",
        ]);
    }

    /**
     * Search sent messages for trigger configuration.
     * Used by opened_message and not_opened_message triggers.
     */
    public function searchSentMessages(Request $request)
    {
        $query = auth()->user()->messages()
            ->where('status', 'sent')
            ->select('id', 'subject', 'created_at', 'sent_count');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'subject' => $msg->subject,
                'sent_count' => $msg->sent_count,
                'created_at' => $msg->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json($messages);
    }

    /**
     * Search CRM contacts for message recipient selection.
     * Returns contacts with their subscriber info for email sending.
     */
    public function searchCrmContacts(Request $request)
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();
        $query = $request->get('q', '');
        $excludeIds = $request->get('exclude', []); // IDs to exclude (already selected)

        $contactsQuery = CrmContact::where('crm_contacts.user_id', $userId)
            ->whereNotNull('crm_contacts.subscriber_id')
            ->join('subscribers', 'crm_contacts.subscriber_id', '=', 'subscribers.id')
            ->leftJoin('crm_companies', 'crm_contacts.crm_company_id', '=', 'crm_companies.id');

        // Search by subscriber fields and company name
        if (strlen($query) >= 2) {
            $contactsQuery->where(function ($q) use ($query) {
                $q->where('subscribers.email', 'like', "%{$query}%")
                  ->orWhere('subscribers.first_name', 'like', "%{$query}%")
                  ->orWhere('subscribers.last_name', 'like', "%{$query}%")
                  ->orWhere('subscribers.phone', 'like', "%{$query}%")
                  ->orWhere('crm_companies.name', 'like', "%{$query}%");
            });
        }

        // Exclude already selected
        if (!empty($excludeIds) && is_array($excludeIds)) {
            $contactsQuery->whereNotIn('crm_contacts.id', $excludeIds);
        }

        $contacts = $contactsQuery
            ->select([
                'crm_contacts.id',
                'crm_contacts.status',
                'crm_contacts.subscriber_id',
                'subscribers.email',
                'subscribers.first_name',
                'subscribers.last_name',
                'subscribers.phone',
                'crm_companies.name as company',
            ])
            ->orderByRaw("CASE WHEN subscribers.email LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
            ->orderBy('subscribers.last_name')
            ->orderBy('subscribers.first_name')
            ->limit(30)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'email' => $c->email,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'full_name' => trim("{$c->first_name} {$c->last_name}") ?: null,
                'company' => $c->company,
                'status' => $c->status,
            ]);

        return response()->json(['contacts' => $contacts]);
    }
}

