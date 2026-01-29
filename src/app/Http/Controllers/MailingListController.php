<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use Illuminate\Http\Request;

use App\Models\ContactList;
use Inertia\Inertia;

class MailingListController extends Controller
{
    public function index(Request $request)
    {
        // Use accessibleLists() to include shared lists for team members
        // Filter to only show email lists in mailing list view
        $query = auth()->user()->accessibleLists()
            ->email()
            ->with(['group', 'tags'])
            ->withCount(['subscribers' => function ($query) {
                $query->where('contact_list_subscriber.status', 'active');
            }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('id', $search);
            });
        }

        if ($request->filled('group_id')) {
            $group = \App\Models\ContactListGroup::find($request->group_id);
            if ($group) {
                $groupIds = array_merge([$group->id], $group->getAllDescendantIds());
                $query->whereIn('contact_list_group_id', $groupIds);
            }
        }

        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        if ($request->filled('visibility')) {
            if ($request->visibility === 'public') {
                $query->where('is_public', true);
            } elseif ($request->visibility === 'private') {
                $query->where('is_public', false);
            }
        }

        // For filters, we generally want to show options from the admin account if we are a team member
        // or just the user's own stuff. For simplicity, let's keep it scoped to auth user for now,
        // but ideally this should fetch from the admin's scope if we are a team member.
        // Let's use getAdminUser() helper to support team members seeing admin's groups/tags.
        $scopeUser = auth()->user()->getAdminUser();

        // Sorting
        $sortCol = $request->input('sort_col', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if (in_array($sortCol, ['name', 'created_at', 'id', 'subscribers_count'])) {
            $query->orderBy($sortCol, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return Inertia::render('MailingList/Index', [
            'lists' => $query
                ->paginate(12)
                ->through(fn ($list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'group' => $list->group,
                    'tags' => $list->tags,
                    'created_at' => DateHelper::formatDateForUser($list->created_at),
                    'subscribers_count' => $list->subscribers()->wherePivot('status', 'active')->count(),
                    'is_public' => (bool)$list->is_public,
                    'permission' => auth()->user()->canEditList($list) ? 'edit' : 'view',
                ])->withQueryString(),
            'filters' => $request->only(['search', 'group_id', 'tag_id', 'visibility', 'sort_col', 'sort_dir']),
            'groups' => \App\Models\ContactListGroup::where('user_id', $scopeUser->id)
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                    'full_path' => $g->full_path,
                ]),
            'tags' => \App\Models\Tag::where('user_id', $scopeUser->id)->get(),
            'allLists' => auth()->user()->accessibleLists()
                ->email()
                ->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $scopeUser = $user->getAdminUser();

        return Inertia::render('MailingList/Create', [
            'defaultSettings' => $scopeUser->settings ?? [],
            'groups' => \App\Models\ContactListGroup::where('user_id', $scopeUser->id)
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                    'full_path' => $g->full_path,
                ]),
            'tags' => \App\Models\Tag::where('user_id', $scopeUser->id)->get(),
            'mailboxes' => \App\Models\Mailbox::where('user_id', $scopeUser->id)->active()->get(['id', 'name', 'from_email']),
            'smsProviders' => \App\Models\SmsProvider::forUser($scopeUser->id)->active()->get(['id', 'name', 'from_name', 'from_number']),
            'externalPages' => \App\Models\ExternalPage::where('user_id', $scopeUser->id)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        // Team members create lists owned by their Admin (or themselves? usually admin account owns data)
        // But for simpler MVP, let's say they own it, OR we assign it to admin_user_id if present.
        // Let's create it on the authenticated user for now to avoid complexity with accessibleLists logic.
        // Or better: everything belongs to the admin.

        $user = auth()->user();
        if (!$user->isAdmin()) {
            // If team member, we might want to prevent creation OR assign to admin.
            // Let's assume for now they CAN create lists and they own them.
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_list_group_id' => 'nullable|exists:contact_list_groups,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'is_public' => 'boolean',
            'settings' => 'nullable|array',

            // Subscription
            'settings.subscription.double_optin' => 'boolean',
            'settings.subscription.notification_email' => 'nullable|email',
            'settings.subscription.delete_unconfirmed' => 'boolean',
            'settings.subscription.delete_unconfirmed_after_days' => 'nullable|integer|min:1|max:365',
            'settings.subscription.security_options' => 'nullable|array',

            // Sending
            'settings.sending.mailbox_id' => 'nullable|integer',
            'settings.sending.sms_settings' => 'nullable|string',
            'settings.sending.from_name' => 'nullable|string|max:255',
            'settings.sending.reply_to' => 'nullable|email',
            'settings.sending.company_name' => 'nullable|string|max:255',
            'settings.sending.company_address' => 'nullable|string|max:255',
            'settings.sending.company_city' => 'nullable|string|max:255',
            'settings.sending.company_zip' => 'nullable|string|max:20',
            'settings.sending.company_country' => 'nullable|string|max:255',
            'settings.sending.headers' => 'nullable|array',
            'settings.sending.headers.list_unsubscribe' => 'nullable|string',
            'settings.sending.headers.list_unsubscribe_post' => 'nullable|string',

            // Pages (Redirects)
            'settings.pages' => 'nullable|array',
            'settings.pages.*.type' => 'nullable|string',
            'settings.pages.*.url' => 'nullable|string',
            'settings.pages.*.external_page_id' => 'nullable|integer',

            // Advanced
            'settings.advanced.facebook_integration' => 'nullable|string',
            'settings.advanced.queue_days' => 'nullable|array',
            'settings.advanced.bounce_analysis' => 'boolean',
        ]);

        $list = auth()->user()->contactLists()->create($validated);

        if (isset($validated['tags'])) {
            $list->tags()->sync($validated['tags']);
        }

        return redirect()->route('mailing-lists.index')
            ->with('success', 'Lista adresowa została utworzona.');
    }

    public function show(ContactList $mailingList)
    {
        if (!auth()->user()->canAccessList($mailingList)) {
            abort(403);
        }

        $mailingList->load(['group', 'tags', 'subscribers']);

        return Inertia::render('MailingList/Show', [
            'list' => [
                'id' => $mailingList->id,
                'name' => $mailingList->name,
                'description' => $mailingList->description,
                'group' => $mailingList->group,
                'tags' => $mailingList->tags,
                'created_at' => DateHelper::formatDateForUser($mailingList->created_at),
                'is_public' => $mailingList->is_public,
                'subscribers_count' => $mailingList->subscribers->where('pivot.status', 'active')->count(),
                'permission' => auth()->user()->canEditList($mailingList) ? 'edit' : 'view',
            ]
        ]);
    }

    public function edit(ContactList $mailingList)
    {
        if (!auth()->user()->canEditList($mailingList)) {
            abort(403, 'Brak uprawnień do edycji tej listy.');
        }

        $user = auth()->user();
        $scopeUser = $user->getAdminUser();

        // Get other lists for co-registration (exclude current list)
        $otherLists = $user->accessibleLists()
            ->where('id', '!=', $mailingList->id)
            ->get(['id', 'name']);

        return Inertia::render('MailingList/Edit', [
            'list' => [
                'id' => $mailingList->id,
                'name' => $mailingList->name,
                'description' => $mailingList->description,
                'contact_list_group_id' => $mailingList->contact_list_group_id,
                'is_public' => $mailingList->is_public,
                'tags' => $mailingList->tags->pluck('id'),
                'settings' => $mailingList->settings ?? [],
                'default_mailbox_id' => $mailingList->default_mailbox_id,
                'default_sms_provider_id' => $mailingList->default_sms_provider_id,
                'cron_settings' => $mailingList->cronSettings ?? null,
                // Integration settings
                'api_key' => $mailingList->api_key,
                'webhook_url' => $mailingList->webhook_url,
                'webhook_events' => $mailingList->webhook_events ?? [],
                // Advanced settings
                'parent_list_id' => $mailingList->parent_list_id,
                'sync_settings' => $mailingList->sync_settings ?? [],
                'max_subscribers' => $mailingList->max_subscribers ?? 0,
                'signups_blocked' => $mailingList->signups_blocked ?? false,
                'required_fields' => $mailingList->required_fields ?? [],
                // Resubscription behavior
                'resubscription_behavior' => $mailingList->resubscription_behavior ?? 'reset_date',
            ],
            'defaultSettings' => $scopeUser->settings ?? [],
            'groups' => \App\Models\ContactListGroup::where('user_id', $scopeUser->id)
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                    'full_path' => $g->full_path,
                ]),
            'tags' => \App\Models\Tag::where('user_id', $scopeUser->id)->get(),
            'mailboxes' => \App\Models\Mailbox::where('user_id', $scopeUser->id)->active()->get(['id', 'name', 'from_email']),
            'smsProviders' => \App\Models\SmsProvider::forUser($scopeUser->id)->active()->get(['id', 'name', 'from_name', 'from_number']),
            'externalPages' => \App\Models\ExternalPage::where('user_id', $scopeUser->id)->get(['id', 'name']),
            'globalCronSettings' => \App\Models\CronSetting::getGlobalSchedule(),
            'otherLists' => $otherLists,
        ]);
    }

    public function update(Request $request, ContactList $mailingList)
    {
        if (!auth()->user()->canEditList($mailingList)) {
             abort(403, 'Brak uprawnień do edycji tej listy.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_list_group_id' => 'nullable|exists:contact_list_groups,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'is_public' => 'boolean',
            'settings' => 'nullable|array',

            // Subscription
            'settings.subscription.double_optin' => 'boolean',
            'settings.subscription.notification_email' => 'nullable|email',
            'settings.subscription.delete_unconfirmed' => 'boolean',
            'settings.subscription.delete_unconfirmed_after_days' => 'nullable|integer|min:1|max:365',
            'settings.subscription.security_options' => 'nullable|array',

            // Sending
            'settings.sending.mailbox_id' => 'nullable|integer',
            'settings.sending.sms_settings' => 'nullable|string',
            'settings.sending.from_name' => 'nullable|string|max:255',
            'settings.sending.reply_to' => 'nullable|email',
            'settings.sending.company_name' => 'nullable|string|max:255',
            'settings.sending.company_address' => 'nullable|string|max:255',
            'settings.sending.company_city' => 'nullable|string|max:255',
            'settings.sending.company_zip' => 'nullable|string|max:20',
            'settings.sending.company_country' => 'nullable|string|max:255',
            'settings.sending.headers' => 'nullable|array',
            'settings.sending.headers.list_unsubscribe' => 'nullable|string',
            'settings.sending.headers.list_unsubscribe_post' => 'nullable|string',

            // Pages (Redirects)
            'settings.pages' => 'nullable|array',
            'settings.pages.*.type' => 'nullable|string',
            'settings.pages.*.url' => 'nullable|string',
            'settings.pages.*.external_page_id' => 'nullable|integer',

            // Advanced
            'settings.advanced.facebook_integration' => 'nullable|string',
            'settings.advanced.queue_days' => 'nullable|array', // e.g., ['Mon', 'Fri']
            'settings.advanced.bounce_analysis' => 'boolean',

            // Integration
            'webhook_url' => 'nullable|url|max:500',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:subscribe,unsubscribe,update,bounce',

            // Co-registration / Advanced limits
            'parent_list_id' => 'nullable|exists:contact_lists,id',
            'sync_settings' => 'nullable|array',
            'sync_settings.sync_on_subscribe' => 'boolean',
            'sync_settings.sync_on_unsubscribe' => 'boolean',
            'max_subscribers' => 'nullable|integer|min:0',
            'signups_blocked' => 'boolean',
            'required_fields' => 'nullable|array',

            // Resubscription behavior
            'resubscription_behavior' => 'nullable|in:reset_date,keep_original_date',

            // Direct provider assignments
            'default_sms_provider_id' => 'nullable|exists:sms_providers,id',
        ]);

        // Assign default_mailbox_id from settings if present
        if (isset($validated['settings']['sending']['mailbox_id'])) {
            $validated['default_mailbox_id'] = $validated['settings']['sending']['mailbox_id'];
        }

        // Assign default_sms_provider_id from settings if present
        if (isset($validated['settings']['sending']['sms_provider_id'])) {
            $validated['default_sms_provider_id'] = $validated['settings']['sending']['sms_provider_id'];
        }

        $mailingList->update($validated);

        if (isset($validated['tags'])) {
            $mailingList->tags()->sync($validated['tags']);
        }

        return back()->with('success', 'Lista adresowa została zaktualizowana.');
    }

    public function destroy(Request $request, ContactList $mailingList)
    {
        // Only owner can delete list
        if ($mailingList->user_id !== auth()->id()) {
            abort(403, 'Tylko właściciel listy może ją usunąć.');
        }

        $subscribersCount = $mailingList->subscribers()->wherePivot('status', 'active')->count();

        if ($subscribersCount > 0) {
            if ($request->has('transfer_to_id')) {
                // Validate target list - must also be editable/owned by user
                // Using accessibleLists to allow transfer to any list they have access to might be risky if they don't own it.
                // Safest to restrict to lists they own.
                $targetList = auth()->user()->contactLists()->findOrFail($request->transfer_to_id);

                if ($targetList->id === $mailingList->id) {
                    return back()->withErrors(['transfer_to_id' => 'Nie można przenieść subskrybentów do tej samej listy.']);
                }

                // Transfer subscribers to target list (many-to-many: detach from source, attach to target)
                $subscriberIds = $mailingList->subscribers()->pluck('subscribers.id')->toArray();
                $mailingList->subscribers()->detach($subscriberIds);
                $targetList->subscribers()->attach($subscriberIds, ['status' => 'active', 'subscribed_at' => now()]);

            } elseif ($request->boolean('force_delete')) {
                // Delete subscribers
                $mailingList->subscribers()->delete();
            } else {
                return back()->withErrors(['general' => 'Ta lista zawiera subskrybentów. Wybierz opcję przeniesienia lub usunięcia ich.']);
            }
        }

        $mailingList->delete();

        return redirect()->route('mailing-lists.index')
            ->with('success', 'Lista adresowa została usunięta.');
    }

    /**
     * Generate a new API key for the list
     */
    public function generateApiKey(ContactList $mailingList)
    {
        if (!auth()->user()->canEditList($mailingList)) {
            abort(403, 'Brak uprawnień do edycji tej listy.');
        }

        $newKey = $mailingList->generateApiKey();

        return back()->with('success', 'Nowy klucz API został wygenerowany.');
    }

    /**
     * Test webhook configuration
     */
    public function testWebhook(ContactList $mailingList)
    {
        if (!auth()->user()->canEditList($mailingList)) {
            abort(403, 'Brak uprawnień do edycji tej listy.');
        }

        if (empty($mailingList->webhook_url)) {
            return back()->with('error', 'Nie skonfigurowano URL webhooka.');
        }

        $testData = [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ];

        // Temporarily enable 'test' event
        $originalEvents = $mailingList->webhook_events;
        $mailingList->webhook_events = ['test'];

        $success = $mailingList->triggerWebhook('test', $testData);

        // Restore original events
        $mailingList->webhook_events = $originalEvents;

        if ($success) {
            return back()->with('success', 'Testowy webhook został wysłany pomyślnie!');
        } else {
            return back()->with('error', 'Nie udało się wysłać testowego webhooka. Sprawdź URL i logi.');
        }
    }

}
