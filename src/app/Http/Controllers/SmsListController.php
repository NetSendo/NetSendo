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
        $query = auth()->user()->contactLists()
            ->sms() // Scope to SMS lists
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
            'filters' => $request->only(['search', 'group_id', 'tag_id']),
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())->get(),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('SmsList/Create', [
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())->get(),
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

        return Inertia::render('SmsList/Edit', [
            'list' => [
                'id' => $smsList->id,
                'name' => $smsList->name,
                'description' => $smsList->description,
                'contact_list_group_id' => $smsList->contact_list_group_id,
                'is_public' => $smsList->is_public,
                'tags' => $smsList->tags->pluck('id'),
            ],
            'groups' => \App\Models\ContactListGroup::where('user_id', auth()->id())->get(),
            'tags' => \App\Models\Tag::where('user_id', auth()->id())->get(),
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

                $smsList->subscribers()->update(['contact_list_id' => $targetList->id]);
                
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
}
