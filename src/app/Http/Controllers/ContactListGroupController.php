<?php

namespace App\Http\Controllers;

use App\Models\ContactListGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ContactListGroupController extends Controller
{
    public function index()
    {
        // Get all groups with children eager loaded
        $groups = ContactListGroup::where('user_id', Auth::id())
            ->with(['children' => function ($query) {
                $query->withCount('contactLists')
                    ->with(['children' => function ($q) {
                        $q->withCount('contactLists');
                    }]);
            }])
            ->withCount('contactLists')
            ->whereNull('parent_id') // Only root groups
            ->orderBy('name')
            ->get();

        // Also get flat list for parent selection dropdown
        $allGroups = ContactListGroup::where('user_id', Auth::id())
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'parent_id' => $group->parent_id,
                    'full_path' => $group->full_path,
                    'depth' => $group->depth,
                ];
            });

        return Inertia::render('ContactListGroup/Index', [
            'groups' => $groups,
            'allGroups' => $allGroups,
        ]);
    }

    public function create()
    {
        return Inertia::render('ContactListGroup/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:contact_list_groups,id',
        ]);

        // Verify parent belongs to user
        if ($request->parent_id) {
            $parent = ContactListGroup::find($request->parent_id);
            if (!$parent || $parent->user_id !== Auth::id()) {
                abort(403);
            }
        }

        ContactListGroup::create([
            'name' => $request->name,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
        ]);

        return redirect()->route('groups.index')
            ->with('success', 'Grupa została utworzona pomyślnie.');
    }

    public function edit(ContactListGroup $group)
    {
        if ($group->user_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('ContactListGroup/Edit', [
            'group' => $group
        ]);
    }

    public function update(Request $request, ContactListGroup $group)
    {
        if ($group->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:contact_list_groups,id',
        ]);

        // Prevent setting self as parent
        if ($request->parent_id == $group->id) {
            return back()->withErrors(['parent_id' => 'Grupa nie może być swoim rodzicem.']);
        }

        // Prevent setting a descendant as parent (would create cycle)
        if ($request->parent_id) {
            $parent = ContactListGroup::find($request->parent_id);
            if (!$parent || $parent->user_id !== Auth::id()) {
                abort(403);
            }

            // Check if parent_id is a descendant of this group
            $descendantIds = $group->getAllDescendantIds();
            if (in_array($request->parent_id, $descendantIds)) {
                return back()->withErrors(['parent_id' => 'Nie można wybrać grupy podrzędnej jako rodzica.']);
            }
        }

        $group->update([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
        ]);

        return redirect()->route('groups.index')
            ->with('success', 'Grupa została zaktualizowana.');
    }

    public function destroy(ContactListGroup $group)
    {
        if ($group->user_id !== Auth::id()) {
            abort(403);
        }

        $group->delete();

        return redirect()->route('groups.index')
            ->with('success', 'Grupa została usunięta.');
    }
}
