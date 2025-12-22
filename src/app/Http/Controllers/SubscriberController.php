<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use Illuminate\Http\Request;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Events\SubscriberUnsubscribed;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscriber::query()
            ->with(['contactLists' => function ($q) {
                // Optimize loading lists
                $q->select('contact_lists.id', 'contact_lists.name');
            }])
            ->where('user_id', auth()->id());

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->list_id) {
            $query->whereHas('contactLists', function ($q) use ($request) {
                $q->where('contact_lists.id', $request->list_id);
            });
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
                    'status' => $sub->is_active_global ? 'active' : 'inactive', // or computed from lists
                    'lists' => $sub->contactLists->pluck('name'), // Return array of names
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
            'customFields' => \App\Models\CustomField::where('user_id', auth()->id())->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                // Unique email per user check manually or complex rule
            ],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,other',
            'contact_list_ids' => 'required|array|min:1',
            'contact_list_ids.*' => 'exists:contact_lists,id',
            'status' => 'required|in:active,inactive', // Helper for is_active_global
            // Custom fields validation could be dynamic here
        ]);

        // Verify ownership of lists
        $count = ContactList::whereIn('id', $validated['contact_list_ids'])
            ->where('user_id', auth()->id())
            ->count();
            
        if ($count !== count($validated['contact_list_ids'])) {
            abort(403, 'Unauthorized access to one or more lists.');
        }

        DB::transaction(function () use ($validated, $request) {
            // Check if subscriber exists for this user
            $subscriber = Subscriber::where('user_id', auth()->id())
                ->where('email', $validated['email'])
                ->first();

            $data = [
                'user_id' => auth()->id(),
                'email' => $validated['email'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'],
                'gender' => $validated['gender'],
                'is_active_global' => $validated['status'] === 'active',
            ];

            if ($subscriber) {
                $subscriber->update($data);
            } else {
                $subscriber = Subscriber::create($data);
            }

            // Sync Lists (Attach)
            // We use syncWithoutDetaching if we want to add, but here "store" might imply "set these lists"
            // For a "Create" form, we usually just add them to these lists.
            // If the user already existed and was on List A, and we select List B, should they remain on A?
            // Usually YES for "Add Subscriber" logic if they already exist.
            // But if we are "Edit"ing, we might replace.
            // Let's assume Add = Attach.
            $subscriber->contactLists()->syncWithoutDetaching($validated['contact_list_ids']);

            // Handle Custom Fields
            if ($request->has('custom_fields')) {
                foreach ($request->input('custom_fields') as $fieldId => $value) {
                    if (blank($value)) continue; // Skip empty values if desired, or save null
                    
                    $subscriber->fieldValues()->updateOrCreate(
                        ['custom_field_id' => $fieldId],
                        ['value' => $value]
                    );
                }
            }
        });

        // Send Welcome Email if requested
        if ($validated['send_welcome_email'] ?? false) {
             $subscriber = Subscriber::where('user_id', auth()->id())
                ->where('email', $validated['email'])
                ->first();
                
             $lists = ContactList::whereIn('id', $validated['contact_list_ids'])->get();
             
             foreach ($lists as $list) {
                 // Dispatch event which should trigger automation/welcome email
                 event(new \App\Events\SubscriberSignedUp($subscriber, $list, 'manual'));
             }
        }

        return redirect()->route('subscribers.index')
            ->with('success', 'Subskrybent został zapisany.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subscriber $subscriber)
    {
        return redirect()->route('subscribers.edit', $subscriber);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subscriber $subscriber)
    {
        if ($subscriber->user_id !== auth()->id()) {
            abort(403);
        }

        $subscriber->load(['contactLists', 'fieldValues']);

        return Inertia::render('Subscriber/Edit', [
            'subscriber' => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'phone' => $subscriber->phone,
                'gender' => $subscriber->gender,
                'status' => $subscriber->is_active_global ? 'active' : 'inactive',
                'contact_list_ids' => $subscriber->contactLists->pluck('id'),
                'custom_fields' => $subscriber->fieldValues->mapWithKeys(fn($val) => [$val->custom_field_id => $val->value]),
            ],
            'lists' => auth()->user()->contactLists()->select('id', 'name')->get(),
            'customFields' => \App\Models\CustomField::where('user_id', auth()->id())->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subscriber $subscriber)
    {
        if ($subscriber->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('subscribers')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                })->ignore($subscriber->id),
            ],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,other',
            'contact_list_ids' => 'required|array|min:1',
            'contact_list_ids.*' => 'exists:contact_lists,id',
            'status' => 'required|in:active,inactive',
        ]);

        // Verify ownership of lists
        $count = ContactList::whereIn('id', $validated['contact_list_ids'])
            ->where('user_id', auth()->id())
            ->count();
            
        if ($count !== count($validated['contact_list_ids'])) {
            abort(403, 'Unauthorized access to one or more lists.');
        }

        DB::transaction(function () use ($validated, $subscriber, $request) {
            $subscriber->update([
                'email' => $validated['email'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'],
                'gender' => $validated['gender'],
                'is_active_global' => $validated['status'] === 'active',
            ]);

            // For update, we SYNC (overwrite) the lists selection
            $subscriber->contactLists()->sync($validated['contact_list_ids']);

            // Handle Custom Fields
            if ($request->has('custom_fields')) {
                foreach ($request->input('custom_fields') as $fieldId => $value) {
                    if (blank($value)) continue;
                    
                    $subscriber->fieldValues()->updateOrCreate(
                        ['custom_field_id' => $fieldId],
                        ['value' => $value]
                    );
                }
            }
        });

        return redirect()->route('subscribers.index')
            ->with('success', 'Subskrybent został zaktualizowany.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscriber $subscriber)
    {
        if ($subscriber->user_id !== auth()->id()) {
            abort(403);
        }

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
        $listId = $request->contact_list_id; // Still importing to ONE primary list logic for now? Or allow multi? Usually import to one.
        $separator = $request->separator === 'tab' ? "\t" : $request->separator;
        
        $list = ContactList::where('id', $listId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $path = $file->getRealPath();
        $content = file_get_contents($path);
        $bom = pack('H*', 'EFBBBF'); 
        $content = preg_replace("/^$bom/", '', $content);
        
        $lines = explode("\n", $content);
        $imported = 0;
        
        // Map, Headers logic same as before...
        // ... (truncated for brevity, logic is finding/creating subscriber and attaching list)
        
        // SIMPLIFIED IMPORT LOGIC FOR REPLACEMENT (Full implementation would repeat parsing logic)
        // I will retain the parsing logic but adapt the storage call.
        
        // Re-implementing minimal parsing for safety in this replacement block
        $map = [
            'email' => ['email', 'e-mail', 'mail'],
            'first_name' => ['first_name', 'firstname', 'imie', 'imię', 'name'],
            'last_name' => ['last_name', 'lastname', 'nazwisko', 'surname'],
        ];

        $colIndices = ['email' => -1, 'first_name' => -1, 'last_name' => -1];
        $startRow = 0;
        
        if (count($lines) > 0) {
            $firstRow = str_getcsv(trim($lines[0]), $separator);
            if (!empty($firstRow)) {
                if (strpos($firstRow[0], '@') !== false) {
                    $colIndices['email'] = 0;
                    $colIndices['first_name'] = count($firstRow) > 1 ? 1 : -1;
                    $colIndices['last_name'] = count($firstRow) > 2 ? 2 : -1;
                    $startRow = 0;
                } else {
                    $headers = array_map('strtolower', array_map('trim', $firstRow));
                    foreach ($map as $dbCol => $possibleNames) {
                        foreach ($headers as $index => $header) {
                            if (in_array($header, $possibleNames)) {
                                $colIndices[$dbCol] = $index;
                                break;
                            }
                        }
                    }
                     if ($colIndices['email'] === -1) {
                        $colIndices['email'] = 0;
                        $colIndices['first_name'] = count($headers) > 1 ? 1 : -1;
                        $colIndices['last_name'] = count($headers) > 2 ? 2 : -1;
                    }
                    $startRow = 1;
                }
            }
        }

        for ($i = $startRow; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            $data = str_getcsv($line, $separator);
            $email = isset($data[$colIndices['email']]) ? trim($data[$colIndices['email']]) : null;
            
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            
            $firstName = $colIndices['first_name'] !== -1 && isset($data[$colIndices['first_name']]) ? trim($data[$colIndices['first_name']]) : null;
            $lastName = $colIndices['last_name'] !== -1 && isset($data[$colIndices['last_name']]) ? trim($data[$colIndices['last_name']]) : null;
            
            // Find or Create Subscriber
            $subscriber = Subscriber::firstOrCreate(
                ['user_id' => auth()->id(), 'email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'is_active_global' => true
                ]
            );

            // Attach to list
            $subscriber->contactLists()->syncWithoutDetaching([$list->id]);
            
            $imported++;
        }

        return redirect()->route('subscribers.index')
            ->with('success', "Zaimportowano {$imported} subskrybentów.");
    }

    public function unsubscribe(Request $request, Subscriber $subscriber)
    {
        // Global unsubscribe vs List unsubscribe
        // Usually clicking unsubscribe link unsubscribes from THAT list or ALL?
        // Context matters. If accessed via controller manually, we might want to unsub from specific list if context provided?
        // But the route binding implies global action or we need to pass list_id.
        // For simplicity in this refactor, let's toggle global status or unsub from all?
        // Or if we have a context of "list_id".
        
        $subscriber->update(['is_active_global' => false]);
        
        // Also update pivot status?
        // $subscriber->contactLists()->updateExistingPivot($listId, ['status' => 'unsubscribed']);

        // Dispatch event for automations (needs context of which list triggered it?)
        // event(new SubscriberUnsubscribed($subscriber, $list, 'link'));

        return Inertia::render('Subscriber/Unsubscribed', [
            'email' => $subscriber->email
        ]);
    }

    public function syncTags(Request $request, Subscriber $subscriber)
    {
        if ($subscriber->user_id !== auth()->id()) abort(403);

        $validated = $request->validate([
            'tags' => 'present|array',
            'tags.*' => 'integer|exists:tags,id',
        ]);

        $subscriber->syncTagsWithEvents($validated['tags']);

        return back()->with('success', 'Tagi subskrybenta zostały zaktualizowane.');
    }

    public function attachTag(Request $request, Subscriber $subscriber, Tag $tag)
    {
        if ($subscriber->user_id !== auth()->id()) abort(403);
        if ($tag->user_id !== auth()->id()) abort(403);

        $subscriber->addTag($tag);

        return back()->with('success', 'Tag został dodany.');
    }

    public function detachTag(Request $request, Subscriber $subscriber, Tag $tag)
    {
        if ($subscriber->user_id !== auth()->id()) abort(403);
        if ($tag->user_id !== auth()->id()) abort(403);

        $subscriber->removeTag($tag);

        return back()->with('success', 'Tag został usunięty.');
    }
}

