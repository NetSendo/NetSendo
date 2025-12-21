<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use Illuminate\Http\Request;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Events\SubscriberUnsubscribed;
use Inertia\Inertia;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscriber::query()
            ->with('contactList')
            ->whereHas('contactList', function ($q) {
                $q->where('user_id', auth()->id());
            });

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->list_id) {
            $query->where('contact_list_id', $request->list_id);
        }

        return Inertia::render('Subscriber/Index', [
            'subscribers' => $query->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn ($sub) => [
                    'id' => $sub->id,
                    'email' => $sub->email,
                    'first_name' => $sub->first_name,
                    'last_name' => $sub->last_name,
                    'status' => $sub->status,
                    'list_name' => $sub->contactList->name,
                    'created_at' => DateHelper::formatForUser($sub->created_at),
                ]),
            'lists' => auth()->user()->contactLists()->select('id', 'name')->get(),
            'filters' => $request->only(['search', 'list_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Subscriber/Create', [
            'lists' => auth()->user()->contactLists()->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'contact_list_id' => 'required|exists:contact_lists,id',
            'status' => 'required|in:active,unsubscribed,bounced',
        ]);

        // Ensure user owns the list
        $list = ContactList::where('id', $validated['contact_list_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $list->subscribers()->create($validated);

        return redirect()->route('subscribers.index')
            ->with('success', 'Subskrybent został dodany.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subscriber $subscriber)
    {
        // Since we don't have a dedicated Show view yet, we redirect to Edit
        return redirect()->route('subscribers.edit', $subscriber);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subscriber $subscriber)
    {
        // Ensure user owns the list that the subscriber belongs to
        $list = ContactList::where('id', $subscriber->contact_list_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return Inertia::render('Subscriber/Edit', [
            'subscriber' => $subscriber,
            'lists' => auth()->user()->contactLists()->select('id', 'name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subscriber $subscriber)
    {
        // Ensure user owns the subscriber's list
        $list = ContactList::where('id', $subscriber->contact_list_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'contact_list_id' => 'required|exists:contact_lists,id',
            'status' => 'required|in:active,unsubscribed,bounced',
        ]);

        // If changing list, verify ownership of new list
        if ($validated['contact_list_id'] != $subscriber->contact_list_id) {
             $newList = ContactList::where('id', $validated['contact_list_id'])
                ->where('user_id', auth()->id())
                ->firstOrFail();
        }

        $subscriber->update($validated);

        return redirect()->route('subscribers.index')
            ->with('success', 'Subskrybent został zaktualizowany.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscriber $subscriber)
    {
        // Ensure user owns the subscriber's list
        $list = ContactList::where('id', $subscriber->contact_list_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $subscriber->delete();

        return redirect()->route('subscribers.index')
            ->with('success', 'Subskrybent został usunięty.');
    }

    public function importForm()
    {
        return Inertia::render('Subscriber/Import', [
            'lists' => auth()->user()->contactLists()->select('id', 'name')->get(),
        ]);
    }

    public function import(\App\Http\Requests\SubscriberImportRequest $request)
    {
        $file = $request->file('file');
        $listId = $request->contact_list_id;
        $separator = $request->separator === 'tab' ? "\t" : $request->separator;
        
        // Ensure user owns the list
        $list = ContactList::where('id', $listId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        
        // Headers mapping
        $headers = [];
        $row = 0;
        $imported = 0;
        
        if (($data = fgetcsv($handle, 1000, $separator)) !== FALSE) {
            // Normalize headers
            $headers = array_map(function($h) {
                return strtolower(trim($h)); // email, "first name" -> firstname
            }, $data);
        }
        
        // Map header names to DB columns
        $map = [
            'email' => ['email', 'e-mail', 'mail'],
            'first_name' => ['first_name', 'firstname', 'imie', 'imię', 'name'],
            'last_name' => ['last_name', 'lastname', 'nazwisko', 'surname'],
        ];

        $colIndices = [];
        foreach ($map as $dbCol => $possibleNames) {
            $colIndices[$dbCol] = -1;
            foreach ($headers as $index => $header) {
                if (in_array($header, $possibleNames)) {
                    $colIndices[$dbCol] = $index;
                    break;
                }
            }
        }
        
        // If email column not found by header, assume first column is email if it looks like one
        // For simplicity, strict requirement on header for now or index 0
        if ($colIndices['email'] === -1) {
             // Fallback: assume column 0 is email
             $colIndices['email'] = 0;
        }

        while (($data = fgetcsv($handle, 1000, $separator)) !== FALSE) {
            $row++;
            
            // Get values based on indices
            $email = isset($data[$colIndices['email']]) ? trim($data[$colIndices['email']]) : null;
            
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue; // Skip invalid email
            }
            
            $firstName = $colIndices['first_name'] !== -1 && isset($data[$colIndices['first_name']]) ? trim($data[$colIndices['first_name']]) : null;
            $lastName = $colIndices['last_name'] !== -1 && isset($data[$colIndices['last_name']]) ? trim($data[$colIndices['last_name']]) : null;
            
            // Upsert or Create (ignore duplicates for this list)
            // Using updateOrCreate might be slow for huge files, but safe for now
            $list->subscribers()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'status' => 'active'
                ]
            );
            
            $imported++;
        }
        
        fclose($handle);

        return redirect()->route('subscribers.index')
            ->with('success', "Zaimportowano {$imported} subskrybentów.");
    }

    public function unsubscribe(Request $request, Subscriber $subscriber)
    {
        // 'signed' middleware handles security
        $list = $subscriber->contactList;
        
        $subscriber->update(['status' => 'unsubscribed']);

        // Dispatch event for automations
        event(new SubscriberUnsubscribed($subscriber, $list, 'link'));

        return Inertia::render('Subscriber/Unsubscribed', [
            'email' => $subscriber->email
        ]);
    }

    /**
     * Sync tags for a subscriber
     */
    public function syncTags(Request $request, Subscriber $subscriber)
    {
        // Ensure user owns the subscriber's list
        $list = ContactList::where('id', $subscriber->contact_list_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'tags' => 'present|array',
            'tags.*' => 'integer|exists:tags,id',
        ]);

        // Use the event-dispatching sync method
        $subscriber->syncTagsWithEvents($validated['tags']);

        return back()->with('success', 'Tagi subskrybenta zostały zaktualizowane.');
    }

    /**
     * Attach a tag to subscriber
     */
    public function attachTag(Request $request, Subscriber $subscriber, Tag $tag)
    {
        // Ensure user owns the subscriber's list
        $list = ContactList::where('id', $subscriber->contact_list_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Ensure user owns the tag
        if ($tag->user_id !== auth()->id()) {
            abort(403);
        }

        $subscriber->addTag($tag);

        return back()->with('success', 'Tag został dodany.');
    }

    /**
     * Detach a tag from subscriber
     */
    public function detachTag(Request $request, Subscriber $subscriber, Tag $tag)
    {
        // Ensure user owns the subscriber's list
        $list = ContactList::where('id', $subscriber->contact_list_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Ensure user owns the tag
        if ($tag->user_id !== auth()->id()) {
            abort(403);
        }

        $subscriber->removeTag($tag);

        return back()->with('success', 'Tag został usunięty.');
    }
}

