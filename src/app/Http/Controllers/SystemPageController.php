<?php

namespace App\Http\Controllers;

use App\Models\SystemPage;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemPageController extends Controller
{
    /**
     * Display a listing of system pages.
     */
    public function index(Request $request)
    {
        $lists = auth()->user()->contactLists()->select('id', 'name')->get();
        $selectedListId = $request->list_id;

        // Get Global Defaults
        $globalPages = SystemPage::whereNull('contact_list_id')->get()->keyBy('slug');
        
        // If a list is selected, get its overrides
        $listPages = collect();
        if ($selectedListId) {
            $listPages = SystemPage::where('contact_list_id', $selectedListId)->get()->keyBy('slug');
        }

        // Merge: Use List specific if exists, otherwise Global
        $pages = $globalPages->map(function ($globalPage) use ($listPages, $selectedListId) {
            if ($listPages->has($globalPage->slug)) {
                $page = $listPages->get($globalPage->slug);
                $page->is_custom = true;
                return $page;
            }
            // Use global, but mark as default
            $page = $globalPage;
            $page->is_custom = false;
            $page->context_list_id = $selectedListId; 
            return $page;
        })->values();

        return Inertia::render('SystemPage/Index', [
            'pages' => $pages,
            'lists' => $lists,
            'currentListId' => $selectedListId,
        ]);
    }

    /**
     * Show the form for editing a system page.
     */
    public function edit(Request $request, SystemPage $systemPage)
    {
        $listId = $systemPage->contact_list_id ?? $request->list_id;
        
        $listName = __('system_pages.global_defaults');
        if ($listId) {
            $list = ContactList::find($listId);
            if ($list) {
                if ($list->user_id !== auth()->id()) {
                    abort(403);
                }
                $listName = $list->name;
            }
        }

        return Inertia::render('SystemPage/Edit', [
            'page' => $systemPage,
            'list_id' => $listId,
            'list_name' => $listName,
        ]);
    }

    /**
     * Update the specified system page.
     */
    public function update(Request $request, SystemPage $systemPage)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:100|alpha_dash',
            'access' => 'nullable|in:public,private',
            'list_id' => 'nullable|exists:contact_lists,id',
        ]);

        $listId = $request->list_id;

        // Security check
        if ($listId) {
            $list = ContactList::where('id', $listId)->where('user_id', auth()->id())->firstOrFail();
        }

        // "Copy on Write" logic
        if ($listId && $systemPage->contact_list_id === null) {
            // We are customizing a default page for a specific list
            SystemPage::create([
                'slug' => $systemPage->slug,
                'name' => $systemPage->name,
                'title' => $validated['title'],
                'content' => $validated['content'],
                'access' => $validated['access'] ?? 'public',
                'contact_list_id' => $listId,
            ]);
            
            return redirect()->route('settings.system-pages.index', ['list_id' => $listId])
                ->with('success', __('system_pages.custom_created'));
        }

        // Normal update
        $updateData = [
            'title' => $validated['title'],
            'content' => $validated['content'],
        ];
        
        if (isset($validated['access'])) {
            $updateData['access'] = $validated['access'];
        }
        
        // Only allow slug change for list-specific pages (not global)
        if ($systemPage->contact_list_id !== null && !empty($validated['slug'])) {
            $updateData['slug'] = $validated['slug'];
        }
        
        $systemPage->update($updateData);

        return redirect()->route('settings.system-pages.index', ['list_id' => $systemPage->contact_list_id])
            ->with('success', __('system_pages.updated'));
    }

    /**
     * Reset a list-specific page to use global default.
     */
    public function destroy(SystemPage $systemPage)
    {
        // Can only delete list-specific pages
        if ($systemPage->contact_list_id === null) {
            return back()->with('error', __('system_pages.cannot_delete_global'));
        }

        $listId = $systemPage->contact_list_id;
        $systemPage->delete();

        return redirect()->route('settings.system-pages.index', ['list_id' => $listId])
            ->with('success', __('system_pages.reset_to_default'));
    }
}
