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
        $groups = ContactListGroup::where('user_id', Auth::id())
            ->withCount('contactLists')
            ->latest()
            ->get();

        return Inertia::render('ContactListGroup/Index', [
            'groups' => $groups
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
        ]);

        ContactListGroup::create([
            'name' => $request->name,
            'user_id' => Auth::id(),
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
        ]);

        $group->update([
            'name' => $request->name,
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
