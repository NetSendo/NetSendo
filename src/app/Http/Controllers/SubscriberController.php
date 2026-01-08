<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use Illuminate\Http\Request;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use App\Models\Tag;
use App\Events\SubscriberUnsubscribed;
use App\Events\SubscriberSignedUp;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        // Get accessible list IDs for the current user (includes shared lists for team members)
        $accessibleListIds = auth()->user()->accessibleLists()->pluck('id');

        $query = Subscriber::query()
            ->with(['contactLists' => function ($q) use ($accessibleListIds) {
                // Only load lists where subscriber is actively subscribed AND user has access
                $q->select('contact_lists.id', 'contact_lists.name')
                  ->wherePivot('status', 'active')
                  ->whereIn('contact_lists.id', $accessibleListIds);
            }, 'fieldValues.customField'])
            // Show subscribers that belong to at least one accessible list
            ->whereHas('contactLists', function ($q) use ($accessibleListIds) {
                $q->whereIn('contact_lists.id', $accessibleListIds)
                  ->where('contact_list_subscriber.status', 'active');
            });

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->list_id) {
            $query->whereHas('contactLists', function ($q) use ($request) {
                $q->where('contact_lists.id', $request->list_id)
                  ->where('contact_list_subscriber.status', 'active');
            });
        }

        if ($request->list_type) {
            $query->whereHas('contactLists', function ($q) use ($request) {
                $q->where('contact_lists.type', $request->list_type);
            });
        }

        // Sorting
        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';

        // Validate sort column
        $allowedSorts = ['created_at', 'email', 'first_name', 'last_name', 'phone'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        // Pagination
        $perPage = $request->per_page ?? 15;
        $allowedPerPage = [10, 15, 25, 50, 100, 200];
        if (!in_array((int)$perPage, $allowedPerPage)) {
            $perPage = 15;
        }

        // Get custom fields for column visibility options
        $customFields = \App\Models\CustomField::where('user_id', auth()->id())
            ->orderBy('sort_order')
            ->get(['id', 'name', 'label', 'type']);

        $listId = $request->list_id; // Define listId for statistics calculation

        // Calculate statistics (after listId is defined above)
        $statistics = [];
        if ($listId) {
            // Statistics for specific list (only if user has access)
            if ($accessibleListIds->contains($listId)) {
                $totalInList = DB::table('contact_list_subscriber')
                    ->where('contact_list_id', $listId)
                    ->where('contact_list_subscriber.status', 'active')
                    ->count();

                $list = ContactList::find($listId);
                $statistics = [
                    'total_in_list' => $totalInList,
                    'list_name' => $list ? $list->name : null,
                ];
            } else {
                $statistics = [
                    'total_in_list' => 0,
                    'list_name' => null,
                ];
            }
        } else {
            // Global statistics - count unique subscribers across accessible lists
            $totalSubscribers = Subscriber::whereHas('contactLists', function ($q) use ($accessibleListIds) {
                $q->whereIn('contact_lists.id', $accessibleListIds)
                  ->where('contact_list_subscriber.status', 'active');
            })->count();
            $totalLists = $accessibleListIds->count();

            $statistics = [
                'total_subscribers' => $totalSubscribers,
                'total_lists' => $totalLists,
            ];
        }

        return Inertia::render('Subscriber/Index', [
            'subscribers' => $query
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn ($sub) => [
                    'id' => $sub->id,
                    'email' => $sub->email,
                    'first_name' => $sub->first_name,
                    'last_name' => $sub->last_name,
                    'phone' => $sub->phone,
                    'status' => $sub->is_active_global ? 'active' : 'inactive',
                    'lists' => $sub->contactLists->pluck('name'),
                    'list_ids' => $sub->contactLists->pluck('id'),
                    'created_at' => DateHelper::formatForUser($sub->created_at),
                    'custom_fields' => $sub->fieldValues->mapWithKeys(fn($fv) => [
                        'cf_' . $fv->custom_field_id => $fv->value
                    ]),
                ]),
            'lists' => auth()->user()->accessibleLists()->select('id', 'name', 'type')->get(),
            'customFields' => $customFields,
            'statistics' => $statistics,
            'filters' => $request->only(['search', 'list_id', 'list_type', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Subscriber/Create', [
            'lists' => auth()->user()->accessibleLists()->select('id', 'name', 'type')->get(),
            'customFields' => \App\Models\CustomField::where('user_id', auth()->id())->get(),
        ]);
    }

    public function store(Request $request)
    {
        // First validate contact_list_ids to determine required fields
        $request->validate([
            'contact_list_ids' => 'required|array|min:1',
            'contact_list_ids.*' => 'exists:contact_lists,id',
        ]);

        // Check list types to determine validation rules (including shared lists)
        $accessibleListIds = auth()->user()->accessibleLists()->pluck('id');
        $lists = ContactList::whereIn('id', $request->contact_list_ids)
            ->whereIn('id', $accessibleListIds)
            ->get();

        if ($lists->count() !== count($request->contact_list_ids)) {
            abort(403, 'Unauthorized access to one or more lists.');
        }

        // Determine if we have SMS-only lists or email lists
        $hasEmailList = $lists->where('type', 'email')->isNotEmpty();
        $hasSmsOnlyList = $lists->where('type', 'sms')->isNotEmpty() && !$hasEmailList;

        // Build validation rules based on list types
        $emailRule = $hasEmailList ? 'required|email|max:255' : 'nullable|email|max:255';
        $phoneRule = $hasSmsOnlyList ? 'required|string|max:50' : 'nullable|string|max:50';

        // For SMS lists, phone is required; for email lists, email is required
        // If mixed, both should be validated appropriately
        if ($lists->where('type', 'sms')->isNotEmpty()) {
            $phoneRule = 'required|string|max:50';
        }
        if ($lists->where('type', 'email')->isNotEmpty()) {
            $emailRule = 'required|email|max:255';
        }

        $validated = $request->validate([
            'email' => $emailRule,
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => $phoneRule,
            'gender' => 'nullable|in:male,female,other',
            'contact_list_ids' => 'required|array|min:1',
            'contact_list_ids.*' => 'exists:contact_lists,id',
            'status' => 'required|in:active,inactive',
        ]);

        DB::transaction(function () use ($validated, $request, $lists) {
            // Check if email was previously suppressed (GDPR forgotten)
            // If so, allow re-subscription and log the consent renewal
            if (!empty($validated['email'])) {
                $wasSuppressed = SuppressionList::handleResubscription(auth()->id(), $validated['email'], 'manual');
                if ($wasSuppressed) {
                    Log::info('Manually added subscriber was previously GDPR-forgotten', [
                        'email' => $validated['email'],
                        'added_by_user_id' => auth()->id(),
                    ]);
                }
            }

            // Find existing subscriber by email or phone depending on what was provided
            $subscriber = null;

            if (!empty($validated['email'])) {
                $subscriber = Subscriber::where('user_id', auth()->id())
                    ->where('email', $validated['email'])
                    ->first();
            }

            // For SMS-only lists, also try to find by phone if no email match
            if (!$subscriber && !empty($validated['phone']) && $lists->where('type', 'sms')->isNotEmpty()) {
                $subscriber = Subscriber::where('user_id', auth()->id())
                    ->where('phone', $validated['phone'])
                    ->first();
            }

            $data = [
                'user_id' => auth()->id(),
                'email' => $validated['email'] ?? null,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'is_active_global' => $validated['status'] === 'active',
            ];

            if ($subscriber) {
                // Update existing subscriber, but don't overwrite existing data with null
                $updateData = array_filter($data, fn($v) => $v !== null);
                unset($updateData['user_id']); // Don't update user_id
                $subscriber->update($updateData);
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

        // Always dispatch SubscriberSignedUp event for automations
        $subscriber = Subscriber::where('user_id', auth()->id())
            ->where('email', $validated['email'] ?? null)
            ->when(empty($validated['email']), function($q) use ($validated) {
                // For SMS-only lists, find by phone
                if (!empty($validated['phone'])) {
                    return $q->orWhere('phone', $validated['phone']);
                }
            })
            ->first();

        if ($subscriber) {
            $lists = ContactList::whereIn('id', $validated['contact_list_ids'])->get();
            foreach ($lists as $list) {
                // Debug: log before dispatching event
                Log::info('SubscriberController: About to dispatch SubscriberSignedUp', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                    'source' => 'manual',
                ]);

                // Dispatch event for automations
                event(new SubscriberSignedUp($subscriber, $list, null, 'manual'));

                Log::info('SubscriberController: Event dispatched');
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

        $subscriber->load(['contactLists' => function ($q) {
            $q->wherePivot('status', 'active');
        }, 'fieldValues']);

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
            'lists' => auth()->user()->accessibleLists()->select('id', 'name', 'type')->get(),
            'customFields' => \App\Models\CustomField::where('user_id', auth()->id())->get(),
        ]);
    }

    /**
     * Get all subscriber IDs from a specific list (for Select All functionality)
     */
    public function getListSubscriberIds(Request $request)
    {
        $validated = $request->validate([
            'list_id' => 'required|integer|exists:contact_lists,id',
        ]);

        // Verify list ownership
        $list = ContactList::where('id', $validated['list_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Get all subscriber IDs from this list
        $ids = DB::table('contact_list_subscriber')
            ->where('contact_list_id', $validated['list_id'])
            ->where('contact_list_subscriber.status', 'active')
            ->join('subscribers', 'subscribers.id', '=', 'contact_list_subscriber.subscriber_id')
            ->where('subscribers.user_id', auth()->id())
            ->pluck('subscribers.id')
            ->toArray();

        return response()->json([
            'ids' => $ids,
            'count' => count($ids),
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

        // Verify access to lists (including shared lists for team members)
        $accessibleListIds = auth()->user()->accessibleLists()->pluck('id');
        $count = ContactList::whereIn('id', $validated['contact_list_ids'])
            ->whereIn('id', $accessibleListIds)
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
            'lists' => auth()->user()->accessibleLists()->select('id', 'name', 'type')->get(),
        ]);
    }

    public function import(\App\Http\Requests\SubscriberImportRequest $request)
    {
        $file = $request->file('file');
        $listId = $request->contact_list_id;
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

        // Extended map to include phone for SMS lists
        $map = [
            'email' => ['email', 'e-mail', 'mail'],
            'phone' => ['phone', 'telefon', 'tel', 'mobile', 'phone_number', 'numer_telefonu', 'numer'],
            'first_name' => ['first_name', 'firstname', 'imie', 'imię', 'name'],
            'last_name' => ['last_name', 'lastname', 'nazwisko', 'surname'],
        ];

        $colIndices = ['email' => -1, 'phone' => -1, 'first_name' => -1, 'last_name' => -1];
        $startRow = 0;

        if (count($lines) > 0) {
            $firstRow = str_getcsv(trim($lines[0]), $separator);
            if (!empty($firstRow)) {
                // Check if first row contains data or headers
                $isDataRow = strpos($firstRow[0], '@') !== false || preg_match('/^\+?[0-9]{9,15}$/', trim($firstRow[0]));

                if ($isDataRow) {
                    // First row is data, guess columns based on content
                    if (strpos($firstRow[0], '@') !== false) {
                        $colIndices['email'] = 0;
                    } elseif (preg_match('/^\+?[0-9]{9,15}$/', trim($firstRow[0]))) {
                        $colIndices['phone'] = 0;
                    }
                    $colIndices['first_name'] = count($firstRow) > 1 ? 1 : -1;
                    $colIndices['last_name'] = count($firstRow) > 2 ? 2 : -1;
                    $startRow = 0;
                } else {
                    // First row is headers
                    $headers = array_map('strtolower', array_map('trim', $firstRow));
                    foreach ($map as $dbCol => $possibleNames) {
                        foreach ($headers as $index => $header) {
                            if (in_array($header, $possibleNames)) {
                                $colIndices[$dbCol] = $index;
                                break;
                            }
                        }
                    }
                    // Fallback if no email/phone found
                    if ($colIndices['email'] === -1 && $colIndices['phone'] === -1) {
                        $colIndices['email'] = 0;
                    }
                    $startRow = 1;
                }
            }
        }

        // Determine which field is primary based on list type
        $isSmsOnlyList = $list->type === 'sms';

        for ($i = $startRow; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $data = str_getcsv($line, $separator);
            $email = $colIndices['email'] !== -1 && isset($data[$colIndices['email']]) ? trim($data[$colIndices['email']]) : null;
            $phone = $colIndices['phone'] !== -1 && isset($data[$colIndices['phone']]) ? trim($data[$colIndices['phone']]) : null;
            $firstName = $colIndices['first_name'] !== -1 && isset($data[$colIndices['first_name']]) ? trim($data[$colIndices['first_name']]) : null;
            $lastName = $colIndices['last_name'] !== -1 && isset($data[$colIndices['last_name']]) ? trim($data[$colIndices['last_name']]) : null;

            // Validate based on list type
            if ($list->type === 'sms') {
                // For SMS lists, phone is required
                if (!$phone) continue;
            } else {
                // For email lists, email is required
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            }

            // Find existing subscriber by email or phone
            $subscriber = null;

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subscriber = Subscriber::where('user_id', auth()->id())
                    ->where('email', $email)
                    ->first();
            }

            // For SMS lists, also try to find by phone
            if (!$subscriber && $phone && $list->type === 'sms') {
                $subscriber = Subscriber::where('user_id', auth()->id())
                    ->where('phone', $phone)
                    ->first();
            }

            if ($subscriber) {
                // Update existing subscriber with new data (if provided)
                $updateData = [];
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && !$subscriber->email) {
                    $updateData['email'] = $email;
                }
                if ($phone && !$subscriber->phone) {
                    $updateData['phone'] = $phone;
                }
                if ($firstName && !$subscriber->first_name) {
                    $updateData['first_name'] = $firstName;
                }
                if ($lastName && !$subscriber->last_name) {
                    $updateData['last_name'] = $lastName;
                }
                if (!empty($updateData)) {
                    $subscriber->update($updateData);
                }
            } else {
                // Create new subscriber
                $subscriber = Subscriber::create([
                    'user_id' => auth()->id(),
                    'email' => $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                    'phone' => $phone,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'is_active_global' => true,
                ]);
            }

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

    /**
     * Bulk delete multiple subscribers
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:subscribers,id',
        ]);

        $count = Subscriber::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->delete();

        return back()->with('success', "Usunięto {$count} subskrybentów.");
    }

    /**
     * Bulk move subscribers to another list
     */
    public function bulkMove(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:subscribers,id',
            'source_list_id' => 'required|integer|exists:contact_lists,id',
            'target_list_id' => 'required|integer|exists:contact_lists,id|different:source_list_id',
        ]);

        // Verify ownership of both lists
        $validLists = ContactList::whereIn('id', [$validated['source_list_id'], $validated['target_list_id']])
            ->where('user_id', auth()->id())
            ->count();

        if ($validLists !== 2) {
            abort(403, 'Brak dostępu do jednej z list.');
        }

        $subscribers = Subscriber::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->get();

        $targetList = ContactList::find($validated['target_list_id']);

        foreach ($subscribers as $subscriber) {
            // Remove from source list
            $subscriber->contactLists()->detach($validated['source_list_id']);
            // Add to target list (without detaching other lists)
            $subscriber->contactLists()->syncWithoutDetaching([
                $validated['target_list_id'] => [
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]
            ]);

            // Dispatch event for automations
            if ($targetList) {
                event(new SubscriberSignedUp($subscriber, $targetList, null, 'bulk_move'));
            }
        }

        $count = count($subscribers);
        return back()->with('success', "Przeniesiono {$count} subskrybentów.");
    }

    /**
     * Bulk change status of multiple subscribers
     */
    public function bulkChangeStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:subscribers,id',
            'status' => 'required|in:active,inactive',
        ]);

        $count = Subscriber::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->update(['is_active_global' => $validated['status'] === 'active']);

        $statusLabel = $validated['status'] === 'active' ? 'aktywnych' : 'nieaktywnych';
        return back()->with('success', "Zmieniono status {$count} subskrybentów na {$statusLabel}.");
    }

    /**
     * Bulk copy subscribers to another list (keeping them in original list)
     */
    public function bulkCopy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:subscribers,id',
            'target_list_id' => 'required|integer|exists:contact_lists,id',
        ]);

        // Verify ownership of target list
        $targetList = ContactList::where('id', $validated['target_list_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$targetList) {
            abort(403, 'Brak dostępu do docelowej listy.');
        }

        $subscribers = Subscriber::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($subscribers as $subscriber) {
            // Add to target list (without detaching from other lists)
            $subscriber->contactLists()->syncWithoutDetaching([
                $validated['target_list_id'] => [
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]
            ]);

            // Dispatch event for automations
            event(new SubscriberSignedUp($subscriber, $targetList, null, 'bulk_copy'));
        }

        $count = count($subscribers);
        return back()->with('success', "Skopiowano {$count} subskrybentów do listy \"{$targetList->name}\".");
    }

    /**
     * Bulk add subscribers to another list (similar to copy, but more explicit naming)
     */
    public function bulkAddToList(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:subscribers,id',
            'target_list_id' => 'required|integer|exists:contact_lists,id',
        ]);

        // Verify ownership of target list
        $targetList = ContactList::where('id', $validated['target_list_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$targetList) {
            abort(403, 'Brak dostępu do docelowej listy.');
        }

        $subscribers = Subscriber::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($subscribers as $subscriber) {
            // Add to target list
            $subscriber->contactLists()->syncWithoutDetaching([
                $validated['target_list_id'] => [
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]
            ]);

            // Dispatch event for automations
            event(new SubscriberSignedUp($subscriber, $targetList, null, 'bulk_add'));
        }

        $count = count($subscribers);
        return back()->with('success', "Dodano {$count} subskrybentów do listy \"{$targetList->name}\".");
    }

    /**
     * Bulk delete subscribers from a specific list (without deleting the subscriber record)
     */
    public function bulkDeleteFromList(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:subscribers,id',
            'list_id' => 'required|integer|exists:contact_lists,id',
        ]);

        // Verify ownership of list
        $list = ContactList::where('id', $validated['list_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$list) {
            abort(403, 'Brak dostępu do listy.');
        }

        $subscribers = Subscriber::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($subscribers as $subscriber) {
            // Detach from specific list only
            $subscriber->contactLists()->detach($validated['list_id']);
        }

        $count = count($subscribers);
        return back()->with('success', "Usunięto {$count} subskrybentów z listy \"{$list->name}\".");
    }
}

