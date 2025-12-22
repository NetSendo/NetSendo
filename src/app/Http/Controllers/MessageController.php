<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\AutomationRule;
use App\Models\EmailReadSession;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Template;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\InsertController;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = auth()->user()->messages()->with('contactLists');

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
            $query->whereHas('contactLists', function ($q) use ($request) {
                $q->where('contact_list_group_id', $request->input('group_id'));
            });
        }

        // Filter by Tag
        if ($request->filled('tag_id')) {
             $query->whereHas('contactLists', function ($q) use ($request) {
                $q->whereHas('tags', function ($t) use ($request) {
                    $t->where('tags.id', $request->input('tag_id'));
                });
            });
        }

        // Search by Subject
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('subject', 'like', '%' . $search . '%');
        }

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        
        // Allow sorting by valid fields
        if (in_array($sortField, ['subject', 'status', 'created_at', 'type', 'day'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
             $query->latest();
        }

        return Inertia::render('Message/Index', [
            'messages' => $query
                ->paginate(12)
                ->withQueryString()
                ->through(fn ($msg) => [
                    'id' => $msg->id,
                    'subject' => $msg->subject,
                    'status' => $msg->status,
                    'type' => $msg->type,
                    'day' => $msg->day,
                    'is_active' => $msg->is_active ?? true,
                    'lists_count' => $msg->contactLists->count(),
                    'list_name' => $msg->contactLists->count() > 0 
                        ? ($msg->contactLists->count() > 1 ? $msg->contactLists->count() . ' list' : $msg->contactLists->first()->name)
                        : '-',
                    'created_at' => DateHelper::formatForUser($msg->created_at),
                ]),
            'filters' => $request->only(['type', 'list_id', 'group_id', 'tag_id', 'search', 'sort', 'direction']),
            'lists' => auth()->user()->contactLists()->select('id', 'name')->orderBy('name')->get(),
            'groups' => auth()->user()->contactListGroups()->select('id', 'name')->orderBy('name')->get(),
            'tags' => auth()->user()->tags()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $defaultMailbox = Mailbox::getDefaultFor(auth()->id());
        $insertController = new InsertController();

        return Inertia::render('Message/Create', [
            'lists' => auth()->user()->contactLists()
                ->select('id', 'name', 'default_mailbox_id', 'contact_list_group_id')
                ->with(['defaultMailbox:id,name,provider', 'group:id,name', 'tags:id,name'])
                ->get(),
            'groups' => auth()->user()->contactListGroups()->select('id', 'name')->orderBy('name')->get(),
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
            'send_at' => 'nullable|date',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            // New fields
            'template_id' => 'nullable|exists:templates,id',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
            'ab_enabled' => 'nullable|boolean',
            'ab_variant_subject' => 'nullable|string|max:255',
            'ab_variant_content' => 'nullable|string',
            'ab_split_percentage' => 'nullable|integer|min:10|max:90',
            'trigger_type' => 'nullable|in:signup,anniversary,inactivity,custom',
            'trigger_config' => 'nullable|array',
        ]);

        // Verify ownership of lists
        if (!empty($validated['contact_list_ids'])) {
            $count = auth()->user()->contactLists()->whereIn('id', $validated['contact_list_ids'])->count();
            if ($count !== count($validated['contact_list_ids'])) {
                return back()->withErrors(['contact_list_ids' => 'Nieprawidłowa lista odbiorców.']);
            }
        }

        // Verify ownership of excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $count = auth()->user()->contactLists()->whereIn('id', $validated['excluded_list_ids'])->count();
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
        if ($validated['status'] === 'scheduled') {
            // If send_at is set, use it; otherwise schedule for now (immediate send)
            $scheduledAt = $validated['send_at'] ?? now();
        }

        $message = auth()->user()->messages()->create([
            'subject' => $validated['subject'],
            'type' => $validated['type'],
            'day' => $validated['type'] === 'autoresponder' ? ($validated['day'] ?? 0) : null,
            'content' => $validated['content'],
            'preheader' => $validated['preheader'] ?? null,
            'status' => $validated['status'],
            'send_at' => $validated['send_at'] ?? null,
            'scheduled_at' => $scheduledAt,
            'time_of_day' => $validated['time_of_day'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'channel' => 'email',
            'ab_enabled' => $validated['ab_enabled'] ?? false,
            'ab_variant_subject' => $validated['ab_variant_subject'] ?? null,
            'ab_variant_content' => $validated['ab_variant_content'] ?? null,
            'ab_split_percentage' => $validated['ab_split_percentage'] ?? 50,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'trigger_config' => $validated['trigger_config'] ?? null,
            'is_active' => true, // Queue messages start as active
        ]);

        if (!empty($validated['contact_list_ids'])) {
            $message->contactLists()->sync($validated['contact_list_ids']);
        }

        // Sync excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $message->excludedLists()->sync($validated['excluded_list_ids']);
        }

        // Sync trigger with automation rule
        $this->syncMessageTrigger($message, $validated);

        return redirect()->route('messages.index')
            ->with('success', 'Wiadomość została zapisana.');
    }

    public function edit(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        $message->load(['contactLists', 'excludedLists', 'template', 'mailbox']);
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
                'send_at' => $message->send_at?->format('Y-m-d H:i:s'),
                'time_of_day' => $message->time_of_day ? substr($message->time_of_day, 0, 5) : null,
                'timezone' => $message->timezone,
                'template_id' => $message->template_id,
                'mailbox_id' => $message->mailbox_id,
                'ab_enabled' => $message->ab_enabled,
                'ab_variant_subject' => $message->ab_variant_subject,
                'ab_variant_content' => $message->ab_variant_content,
                'ab_split_percentage' => $message->ab_split_percentage,
                'trigger_type' => $message->trigger_type,
                'trigger_config' => $message->trigger_config,
            ],
            'lists' => auth()->user()->contactLists()
                ->select('id', 'name', 'default_mailbox_id', 'contact_list_group_id')
                ->with(['defaultMailbox:id,name,provider', 'group:id,name', 'tags:id,name'])
                ->get(),
            'groups' => auth()->user()->contactListGroups()->select('id', 'name')->orderBy('name')->get(),
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
            'send_at' => 'nullable|date',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            // New fields
            'template_id' => 'nullable|exists:templates,id',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
            'ab_enabled' => 'nullable|boolean',
            'ab_variant_subject' => 'nullable|string|max:255',
            'ab_variant_content' => 'nullable|string',
            'ab_split_percentage' => 'nullable|integer|min:10|max:90',
            'trigger_type' => 'nullable|in:signup,anniversary,inactivity,custom',
            'trigger_config' => 'nullable|array',
        ]);

        // Verify ownership
        if (!empty($validated['contact_list_ids'])) {
            $count = auth()->user()->contactLists()->whereIn('id', $validated['contact_list_ids'])->count();
            if ($count !== count($validated['contact_list_ids'])) {
                return back()->withErrors(['contact_list_ids' => 'Nieprawidłowa lista odbiorców.']);
            }
        }

        // Verify ownership of excluded lists
        if (!empty($validated['excluded_list_ids'])) {
            $count = auth()->user()->contactLists()->whereIn('id', $validated['excluded_list_ids'])->count();
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
        if ($validated['status'] === 'scheduled') {
            // If send_at is set, use it; otherwise schedule for now (immediate send)
            $scheduledAt = $validated['send_at'] ?? now();
        }

        $message->update([
            'subject' => $validated['subject'],
            'type' => $validated['type'],
            'day' => $validated['type'] === 'autoresponder' ? ($validated['day'] ?? 0) : null,
            'content' => $validated['content'],
            'preheader' => $validated['preheader'] ?? null,
            'status' => $validated['status'],
            'send_at' => $validated['send_at'] ?? null,
            'scheduled_at' => $scheduledAt,
            'time_of_day' => $validated['time_of_day'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'ab_enabled' => $validated['ab_enabled'] ?? false,
            'ab_variant_subject' => $validated['ab_variant_subject'] ?? null,
            'ab_variant_content' => $validated['ab_variant_content'] ?? null,
            'ab_split_percentage' => $validated['ab_split_percentage'] ?? 50,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'trigger_config' => $validated['trigger_config'] ?? null,
        ]);

        if (array_key_exists('contact_list_ids', $validated)) {
            $message->contactLists()->sync($validated['contact_list_ids'] ?? []);
        }

        // Sync excluded lists
        if (array_key_exists('excluded_list_ids', $validated)) {
            $message->excludedLists()->sync($validated['excluded_list_ids'] ?? []);
        }

        // Sync trigger with automation rule
        $this->syncMessageTrigger($message, $validated);

        return redirect()->route('messages.index')
            ->with('success', 'Wiadomość została zaktualizowana.');
    }

    /**
     * Duplicate a message as a draft
     */
    public function duplicate(Message $message)
    {
        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        // Create a copy of the message
        $newMessage = $message->replicate();
        $newMessage->subject = '[KOPIA] ' . $message->subject;
        $newMessage->status = 'draft';
        $newMessage->send_at = null;
        $newMessage->created_at = now();
        $newMessage->updated_at = now();
        $newMessage->save();

        // Copy contact list associations
        $newMessage->contactLists()->sync($message->contactLists->pluck('id'));

        // Copy excluded list associations
        $newMessage->excludedLists()->sync($message->excludedLists->pluck('id'));

        return response()->json([
            'success' => true,
            'message' => $newMessage,
            'redirect_url' => route('messages.edit', $newMessage->id),
        ]);
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

    public function stats(Message $message)
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

        // TODO: Implement tracking when MessageOpen and MessageClick models are ready
        // For now, use placeholder values
        $opens = 0;
        $uniqueOpens = 0;
        $clicks = 0;
        $uniqueClicks = 0;

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

        return Inertia::render('Message/Stats', [
            'message' => [
                'id' => $message->id,
                'subject' => $message->subject,
                'sent_at' => DateHelper::formatForUser($message->send_at ?? $message->created_at),
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
            'recent_activity' => [
                'opens' => [], // TODO: Implement when MessageOpen model is ready
                'clicks' => [], // TODO: Implement when MessageClick model is ready
            ]
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
    public function test(Request $request, \App\Services\Mail\MailProviderService $providerService)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
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
            // Use the proper mail provider service to send HTML email
            $provider = $providerService->getProvider($mailbox);
            
            $provider->send(
                to: $validated['email'],
                toName: $validated['email'], // Use email as name for test
                subject: '[TEST] ' . $validated['subject'],
                htmlContent: $validated['content'] // Send as HTML, not plain text
            );

            return response()->json(['success' => true, 'message' => 'Test email sent']);
        } catch (\Exception $e) {
            \Log::error('Test email failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

        $message->is_active = !($message->is_active ?? true);
        $message->save();

        return response()->json([
            'success' => true,
            'is_active' => $message->is_active,
            'message' => $message->is_active ? 'Wiadomość została aktywowana.' : 'Wiadomość została dezaktywowana.'
        ]);
    }

    /**
     * Sync message trigger with AutomationRule
     * Creates/updates an automation rule when message has a trigger configured
     */
    protected function syncMessageTrigger(Message $message, array $data): void
    {
        $triggerType = $data['trigger_type'] ?? null;
        
        if (empty($triggerType)) {
            // Remove automation rule if trigger was removed
            AutomationRule::where('trigger_source', 'message')
                ->where('trigger_source_id', $message->id)
                ->delete();
            return;
        }

        // Map message trigger types to AutomationRule trigger events
        $triggerEventMap = [
            'signup' => 'subscriber_signup',
            'anniversary' => 'subscription_anniversary',
            'inactivity' => 'subscriber_inactive',
            'birthday' => 'subscriber_birthday',
            'page_visit' => 'page_visited',
            'custom' => 'tag_added', // Custom allows any trigger
        ];

        $triggerEvent = $triggerEventMap[$triggerType] ?? $triggerType;

        // Build trigger config
        $triggerConfig = $data['trigger_config'] ?? [];
        
        // Add list_id from message if not set
        if (empty($triggerConfig['list_id']) && $message->contactLists->isNotEmpty()) {
            $triggerConfig['list_id'] = $message->contactLists->first()->id;
        }

        AutomationRule::updateOrCreate(
            [
                'trigger_source' => 'message',
                'trigger_source_id' => $message->id,
            ],
            [
                'user_id' => $message->user_id,
                'name' => "Auto: {$message->subject}",
                'description' => "Automatyzacja utworzona z wiadomości #{$message->id}",
                'trigger_event' => $triggerEvent,
                'trigger_config' => $triggerConfig,
                'conditions' => [],
                'condition_logic' => 'all',
                'actions' => [
                    [
                        'type' => 'send_email',
                        'config' => ['message_id' => $message->id]
                    ]
                ],
                'is_active' => in_array($message->status, ['sent', 'scheduled']),
            ]
        );
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
}
