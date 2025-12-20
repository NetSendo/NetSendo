<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::where('user_id', Auth::id())
            ->withCount('contactLists')
            ->latest()
            ->get();

        return Inertia::render('Tag/Index', [
            'tags' => $tags
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        Tag::create([
            'name' => $request->name,
            'color' => $request->color ?? '#3b82f6',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Tag został utworzony.');
    }

    public function update(Request $request, Tag $tag)
    {
        if ($tag->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $tag->update($request->only('name', 'color'));

        return back()->with('success', 'Tag został zaktualizowany.');
    }

    public function destroy(Tag $tag)
    {
        if ($tag->user_id !== Auth::id()) {
            abort(403);
        }

        $tag->delete();

        return back()->with('success', 'Tag został usunięty.');
    }
}
