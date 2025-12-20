<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ContactList;
use Inertia\Inertia;

class MailingListController extends Controller
{
    public function index(Request $request)
    {
        $query = auth()->user()->contactLists()
            ->with(['group', 'tags']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('group_id')) {
            $query->where('contact_list_group_id', $request->group_id);
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

        return Inertia::render('MailingList/Index', [
            'lists' => $query->latest()
                ->paginate(12)
                ->through(fn ($list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'group' => $list->group,
                    'tags' => $list->tags,
                    'tags' => $list->tags,
                    'created_at' => $list->created_at->format('Y-m-d'),
                    'subscribers_count' => $list->subscribers()->count(),
                    'is_public' => (bool)$list->is_public,
                ])->withQueryString(),
            'filters' => $request->only(['search', 'group_id', 'tag_id', 'visibility']),
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())->get(),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        
        return Inertia::render('MailingList/Create', [
            'defaultSettings' => $user->settings ?? [],
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())->get(),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
            'mailboxes' => \App\Models\Mailbox::where('user_id', auth()->id())->active()->get(['id', 'name', 'from_email']),
            'externalPages' => \App\Models\ExternalPage::where('user_id', auth()->id())->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
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
        if ($mailingList->user_id !== auth()->id()) {
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
                'created_at' => $mailingList->created_at->format('Y-m-d'),
                'is_public' => $mailingList->is_public,
                'subscribers_count' => $mailingList->subscribers->count(),
                // Add recent subscribers or stats later
            ]
        ]);
    }

    public function edit(ContactList $mailingList)
    {
        if ($mailingList->user_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();

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
                'cron_settings' => $mailingList->cronSettings ?? null,
            ],
            'defaultSettings' => $user->settings ?? [],
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())->get(),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
            'mailboxes' => \App\Models\Mailbox::where('user_id', auth()->id())->active()->get(['id', 'name', 'from_email']),
            'externalPages' => \App\Models\ExternalPage::where('user_id', auth()->id())->get(['id', 'name']),
            'globalCronSettings' => \App\Models\CronSetting::getGlobalSchedule(),
        ]);
    }

    public function update(Request $request, ContactList $mailingList)
    {
        if ($mailingList->user_id !== auth()->id()) {
            abort(403);
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
            
            // Pages (Redirects)
            // Storing as array of objects or direct keys. Let's use direct keys with nested structure for each page type
            // Pages (Redirects)
            'settings.pages' => 'nullable|array',
            'settings.pages.*.type' => 'nullable|string',
            'settings.pages.*.url' => 'nullable|string',
            'settings.pages.*.external_page_id' => 'nullable|integer',
            
            // Advanced
            'settings.advanced.facebook_integration' => 'nullable|string',
            'settings.advanced.queue_days' => 'nullable|array', // e.g., ['Mon', 'Fri']
            'settings.advanced.bounce_analysis' => 'boolean',
        ]);

        // Assign default_mailbox_id from settings if present
        if (isset($validated['settings']['sending']['mailbox_id'])) {
            $validated['default_mailbox_id'] = $validated['settings']['sending']['mailbox_id'];
        }

        $mailingList->update($validated);

        if (isset($validated['tags'])) {
            $mailingList->tags()->sync($validated['tags']);
        }

        return redirect()->route('mailing-lists.index')
            ->with('success', 'Lista adresowa została zaktualizowana.');
    }

    public function destroy(Request $request, ContactList $mailingList)
    {
        if ($mailingList->user_id !== auth()->id()) {
            abort(403);
        }

        $subscribersCount = $mailingList->subscribers()->count();

        if ($subscribersCount > 0) {
            if ($request->has('transfer_to_id')) {
                // Validate target list
                $targetList = auth()->user()->contactLists()->findOrFail($request->transfer_to_id);

                if ($targetList->id === $mailingList->id) {
                    return back()->withErrors(['transfer_to_id' => 'Nie można przenieść subskrybentów do tej samej listy.']);
                }

                // Transfer subscribers
                $mailingList->subscribers()->update(['contact_list_id' => $targetList->id]);
                
            } elseif ($request->boolean('force_delete')) {
                // Delete subscribers (cascade handles this usually, but explicitly here for clarity if no cascade)
                $mailingList->subscribers()->delete();
            } else {
                return back()->withErrors(['general' => 'Ta lista zawiera subskrybentów. Wybierz opcję przeniesienia lub usunięcia ich.']);
            }
        }

        $mailingList->delete();

        return redirect()->route('mailing-lists.index')
            ->with('success', 'Lista adresowa została usunięta.');
    }
}
