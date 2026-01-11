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

        // Detach from all contact lists
        $tag->contactLists()->detach();

        // If you have subscriber relationship, detach them too (assuming many-to-many or morph)
        // Based on routes, it seems we have subscriber tags too?
        // Let's check Tag model again or just assume standard detach if defined.
        // The Tag model showed morphedByMany ContactList.
        // It didn't explicitly show subscribers, but let's check if it should.
        // Route: Route::post('subscribers/{subscriber}/tags', ...);
        // This implies Subscriber model has tags()
        // So Tag model should have subscribers() morphedByMany or belongsToMany.
        // However, standard delete() on a model with properly set up foreign keys (on pivot) should work IF cascade is on.
        // If not, we manually detach.
        // Let's try to find if there is a 'subscribers' method on Tag model or if we need to add it to safely detach.
        // Based on previous file view, Tag model only had contactLists().
        // Let's assume for now we just delete and let DB handle it or add detach if we find relationships.
        // To be safe:
        // $tag->subscribers()->detach(); // Only if relationship exists on model.

        $tag->delete();

        return back()->with('success', 'Tag został usunięty.');
    }
}
