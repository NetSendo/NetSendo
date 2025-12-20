<?php

namespace App\Http\Controllers;

use App\Models\SystemMessage;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $lists = auth()->user()->contactLists()->select('id', 'name')->get();
        $selectedListId = $request->list_id;

        // Get Global Defaults
        $globalMessages = SystemMessage::whereNull('contact_list_id')->get()->keyBy('slug');
        
        // If a list is selected, get its overrides
        $listMessages = collect();
        if ($selectedListId) {
            $listMessages = SystemMessage::where('contact_list_id', $selectedListId)->get()->keyBy('slug');
        }

        // Merge: Use List specific if exists, otherwise Global
        // We want to display ALL messages that define the system features.
        // So we iterate over Global (the definition of what messages exist) and check for overrides.
        
        $messages = $globalMessages->map(function ($globalMsg) use ($listMessages, $selectedListId) {
            if ($listMessages->has($globalMsg->slug)) {
                $msg = $listMessages->get($globalMsg->slug);
                $msg->is_custom = true;
                return $msg;
            }
            // Use global, but mark as default
            $msg = $globalMsg;
            $msg->is_custom = false;
            // Inject the context list_id for the frontend link
            $msg->context_list_id = $selectedListId; 
            return $msg;
        })->values();

        return Inertia::render('SystemMessage/Index', [
            'messages' => $messages,
            'lists' => $lists,
            'currentListId' => $selectedListId,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, SystemMessage $systemMessage)
    {
        // If we are editing, we need to know the context list.
        // If the systemMessage has a contact_list_id, we are editing that specific one.
        // If it DOES NOT, but we have a request->list_id, we are about to "Copy on Write".
        
        $listId = $systemMessage->contact_list_id ?? $request->list_id;
        
        $listName = 'Global Defaults';
        if ($listId) {
            $list = ContactList::find($listId);
            if ($list) {
                // Ensure ownership
                if ($list->user_id !== auth()->id()) {
                    abort(403);
                }
                $listName = $list->name;
            }
        }

        return Inertia::render('SystemMessage/Edit', [
            'message' => $systemMessage,
            'list_id' => $listId,
            'list_name' => $listName,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SystemMessage $systemMessage)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'list_id' => 'nullable|exists:contact_lists,id',
        ]);

        $listId = $request->list_id;

        // Security check
        if ($listId) {
            $list = ContactList::where('id', $listId)->where('user_id', auth()->id())->firstOrFail();
        }

        // "Copy on Write" logic
        if ($listId && $systemMessage->contact_list_id === null) {
            // We are customizing a default message for a specific list
            SystemMessage::create([
                'slug' => $systemMessage->slug,
                'name' => $systemMessage->name, // Keep internal name
                'title' => $validated['title'],
                'content' => $validated['content'],
                'contact_list_id' => $listId,
            ]);
            
             return redirect()->route('settings.system-messages.index', ['list_id' => $listId])
                ->with('success', 'Custom message created for this list.');
        }

        // Normal update
        $systemMessage->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return redirect()->route('settings.system-messages.index', ['list_id' => $systemMessage->contact_list_id])
            ->with('success', 'System message updated successfully.');
    }
}
