<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use App\Models\ContactList;
use Inertia\Inertia;

class SmsListController extends Controller
{
    public function index(Request $request)
    {
        $query = auth()->user()->accessibleLists()
            ->sms() // Scope to SMS lists
            ->with(['group', 'tags']);

        // Sorting
        $sortCol = $request->input('sort_col', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if (in_array($sortCol, ['name', 'created_at'])) {
            $query->orderBy($sortCol, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
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

        return Inertia::render('SmsList/Index', [
            'lists' => $query->latest()
                ->paginate(12)
                ->through(fn ($list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'group' => $list->group,
                    'tags' => $list->tags,
                    'created_at' => DateHelper::formatDateForUser($list->created_at),
                    'subscribers_count' => $list->subscribers()->count(),
                    'is_public' => (bool)$list->is_public,
                ])->withQueryString(),
            'filters' => $request->only(['search', 'group_id', 'tag_id', 'visibility', 'sort_col', 'sort_dir']),
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                ]),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('SmsList/Create', [
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                ]),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
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
        ]);

        // Force type to 'sms'
        $validated['type'] = 'sms';

        $list = auth()->user()->contactLists()->create($validated);

        if (isset($validated['tags'])) {
            $list->tags()->sync($validated['tags']);
        }

        return redirect()->route('sms-lists.index')
            ->with('success', 'Lista SMS została utworzona.');
    }

    public function edit(ContactList $smsList)
    {
        if ($smsList->user_id !== auth()->id() || $smsList->type !== 'sms') {
            abort(403);
        }

        // Get other SMS lists for co-registration (exclude current list)
        $otherLists = auth()->user()->contactLists()
            ->sms()
            ->where('id', '!=', $smsList->id)
            ->get(['id', 'name']);

        return Inertia::render('SmsList/Edit', [
            'list' => [
                'id' => $smsList->id,
                'name' => $smsList->name,
                'description' => $smsList->description,
                'contact_list_group_id' => $smsList->contact_list_group_id,
                'is_public' => $smsList->is_public,
                'tags' => $smsList->tags->pluck('id'),
                'settings' => $smsList->settings ?? [],
                'cron_settings' => $smsList->cronSettings ?? null,
                // Integration settings
                'api_key' => $smsList->api_key,
                'webhook_url' => $smsList->webhook_url,
                'webhook_events' => $smsList->webhook_events ?? [],
                // Advanced settings
                'parent_list_id' => $smsList->parent_list_id,
                'sync_settings' => $smsList->sync_settings ?? [],
                'max_subscribers' => $smsList->max_subscribers ?? 0,
                'signups_blocked' => $smsList->signups_blocked ?? false,
            ],
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())
                ->with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'depth' => $g->depth,
                ]),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
            'otherLists' => $otherLists,
            'globalCronSettings' => \App\Models\CronSetting::getGlobalSchedule(),
        ]);
    }

    public function update(Request $request, ContactList $smsList)
    {
        if ($smsList->user_id !== auth()->id() || $smsList->type !== 'sms') {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_list_group_id' => 'nullable|exists:contact_list_groups,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'is_public' => 'boolean',

            // Settings
            'settings' => 'nullable|array',
            'settings.cron' => 'nullable|array',
            'settings.cron.use_custom' => 'boolean',
            'settings.cron.volume_per_minute' => 'nullable|integer|min:1|max:10000',
            'settings.cron.schedule' => 'nullable|array',

            // Integration
            'webhook_url' => 'nullable|url|max:500',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:subscribe,unsubscribe,update',

            // Advanced
            'parent_list_id' => 'nullable|exists:contact_lists,id',
            'sync_settings' => 'nullable|array',
            'sync_settings.sync_on_subscribe' => 'boolean',
            'sync_settings.sync_on_unsubscribe' => 'boolean',
            'max_subscribers' => 'nullable|integer|min:0',
            'signups_blocked' => 'boolean',
        ]);

        $smsList->update($validated);

        if (isset($validated['tags'])) {
            $smsList->tags()->sync($validated['tags']);
        }

        return redirect()->route('sms-lists.index')
            ->with('success', 'Lista SMS została zaktualizowana.');
    }

    public function destroy(Request $request, ContactList $smsList)
    {
        if ($smsList->user_id !== auth()->id() || $smsList->type !== 'sms') {
            abort(403);
        }

        $subscribersCount = $smsList->subscribers()->count();

        if ($subscribersCount > 0) {
            if ($request->has('transfer_to_id')) {
                $targetList = auth()->user()->contactLists()->sms()->findOrFail($request->transfer_to_id);

                if ($targetList->id === $smsList->id) {
                    return back()->withErrors(['transfer_to_id' => 'Nie można przenieść subskrybentów do tej samej listy.']);
                }

                // Transfer subscribers to target list (many-to-many: detach from source, attach to target)
                $subscriberIds = $smsList->subscribers()->pluck('subscribers.id')->toArray();
                $smsList->subscribers()->detach($subscriberIds);
                $targetList->subscribers()->attach($subscriberIds, ['status' => 'active', 'subscribed_at' => now()]);

            } elseif ($request->boolean('force_delete')) {
                $smsList->subscribers()->delete();
            } else {
                return back()->withErrors(['general' => 'Ta lista zawiera subskrybentów.']);
            }
        }

        $smsList->delete();

        return redirect()->route('sms-lists.index')
            ->with('success', 'Lista SMS została usunięta.');
    }

    /**
     * Generate a new API key for the SMS list
     */
    public function generateApiKey(ContactList $smsList)
    {
        if ($smsList->user_id !== auth()->id() || $smsList->type !== 'sms') {
            abort(403, 'Brak uprawnień do edycji tej listy.');
        }

        $smsList->generateApiKey();

        return back()->with('success', 'Nowy klucz API został wygenerowany.');
    }

    /**
     * Test webhook configuration
     */
    public function testWebhook(ContactList $smsList)
    {
        if ($smsList->user_id !== auth()->id() || $smsList->type !== 'sms') {
            abort(403, 'Brak uprawnień do edycji tej listy.');
        }

        if (empty($smsList->webhook_url)) {
            return back()->with('error', 'Nie skonfigurowano URL webhooka.');
        }

        $testData = [
            'phone' => '+48123456789',
            'first_name' => 'Test',
            'last_name' => 'User',
        ];

        // Temporarily enable 'test' event
        $originalEvents = $smsList->webhook_events;
        $smsList->webhook_events = ['test'];

        $success = $smsList->triggerWebhook('test', $testData);

        // Restore original events
        $smsList->webhook_events = $originalEvents;

        if ($success) {
            return back()->with('success', 'Testowy webhook został wysłany pomyślnie!');
        } else {
            return back()->with('error', 'Nie udało się wysłać testowego webhooka. Sprawdź URL i logi.');
        }
    }
}
