<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Message;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class SmsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $messages = Message::sms()
            ->with(['contactLists'])
            ->when($request->search, function ($query, $search) {
                $query->where('subject', 'like', "%{$search}%");
            })
            ->when($request->list_id, function ($query, $list_id) {
                $query->whereHas('contactLists', function ($q) use ($list_id) {
                    $q->where('contact_lists.id', $list_id);
                });
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->campaign_plan_id, function ($query, $campaignPlanId) {
                $query->where('campaign_plan_id', $campaignPlanId);
            })
            ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($message) => [
                'id' => $message->id,
                'subject' => $message->subject,
                'content' => $message->content, // SMS content usually short enough to show? Or maybe truncate
                'type' => $message->type, // broadcast, autoresponder
                'day' => $message->day,
                'is_active' => $message->is_active ?? true,
                'status' => $message->status, // draft, scheduled, sent
                'created_at' => DateHelper::formatForUser($message->created_at),
                'list_name' => $message->contactLists->first()->name ?? '-',
                'recipients_count' => $message->contactLists->first()?->subscribers()->count() ?? 0,
            ]);

        // Get all lists (both email and SMS) for SMS campaigns
        $lists = ContactList::query()->get(['id', 'name', 'type']);

        // Assuming groups are not used for SMS yet or shared?
        // For now, only passing lists as per Create view. Index view had group_id filter but we can hide it or implement it similarly if needed.
        // Let's pass empty groups or fetch if needed. Existing Message/Index uses groups.
        // We'll stick to Lists for SMS for now as per plan.

        return Inertia::render('Sms/Index', [
            'messages' => $messages,
            'filters' => $request->only(['search', 'type', 'list_id', 'campaign_plan_id', 'sort', 'direction']),
            'lists' => $lists,
            'groups' => [], // Placeholder if we want to add group support later
            'campaignPlans' => \App\Models\CampaignPlan::forUser(auth()->id())
                ->whereNotNull('exported_at')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Get all lists (both email and SMS) for SMS campaigns
        $lists = ContactList::query()->get(['id', 'name', 'type']);

        // Get optional pre-selected list ID from query parameter
        $preselectedListId = $request->query('list_id') ? (int) $request->query('list_id') : null;

        return Inertia::render('Sms/Create', [
            'lists' => $lists,
            'preselectedListId' => $preselectedListId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string', // SMS content
            'list_id' => 'nullable|exists:contact_lists,id',
            'type' => 'required|in:broadcast,autoresponder',
            'day' => 'nullable|integer|min:0',
            'time' => 'nullable|date_format:H:i',
            'schedule_date' => 'nullable|date', // or datetime format check
            'status' => 'required|in:draft,scheduled,sent',
        ]);

        $message = new Message($validated);
        $message->user_id = auth()->id();
        $message->channel = 'sms';

        // Handle scheduling
        if ($request->time) {
            $message->time_of_day = $request->time;
        }

        if ($request->schedule_date) {
            $message->send_at = $request->schedule_date;
            // If scheduling, ensure status is scheduled
             if ($validated['status'] === 'sent') { // If user clicked "Send/Schedule"
                $message->status = 'scheduled';
            }
        } elseif ($validated['status'] === 'sent') {
             $message->send_at = now();
             // In real app, we might dispatch a job here immediately if "Send Now"
        }

        $message->save();

        if ($request->list_id) {
            $message->contactLists()->sync([$request->list_id]);
        }

        return Redirect::route('sms.index')->with('success', 'SMS Campaign created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $message = Message::sms()->with('contactLists')->findOrFail($id);

        // Transform for view
        $smsData = [
            'id' => $message->id,
            'subject' => $message->subject,
            'content' => $message->content,
            'list_id' => $message->contactLists->first()->id ?? null,
            'type' => $message->type,
            'day' => $message->day,
            'time' => $message->time_of_day, // Assuming column name is time_of_day? Let's check model.
            // Model says 'time_of_day' in fillable.
            'scheduled_at' => $message->send_at ? $message->send_at->format('Y-m-d\TH:i') : null,
            'status' => $message->status,
        ];

        // Get all lists (both email and SMS) for SMS campaigns
        $lists = ContactList::query()->get(['id', 'name', 'type']);

        return Inertia::render('Sms/Create', [
            'sms' => $smsData,
            'lists' => $lists,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $message = Message::sms()->findOrFail($id);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'list_id' => 'nullable|exists:contact_lists,id',
            'type' => 'required|in:broadcast,autoresponder',
            'day' => 'nullable|integer|min:0',
            'time' => 'nullable|date_format:H:i', // Input 'time' might map to 'time_of_day'
            'schedule_date' => 'nullable|date',
            'status' => 'required|in:draft,scheduled,sent',
        ]);

        $message->fill($validated);

        // Map time to time_of_day if needed
         if ($request->has('time')) {
            $message->time_of_day = $request->time;
        }

        if ($request->schedule_date) {
            $message->send_at = $request->schedule_date;
             if ($validated['status'] === 'sent') {
                $message->status = 'scheduled';
            }
        } elseif ($validated['status'] === 'sent' && !$message->send_at) {
             $message->send_at = now();
        }

        $message->save();

        if ($request->list_id) {
            $message->contactLists()->sync([$request->list_id]);
        }

        return Redirect::route('sms.index')->with('success', 'SMS Campaign updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $message = Message::sms()->findOrFail($id);
        $message->delete();

        return Redirect::route('sms.index')->with('success', 'SMS Campaign deleted.');
    }

    /**
     * Toggle the active status of a queue SMS message
     */
    public function toggleActive(string $id)
    {
        $message = Message::sms()->findOrFail($id);

        if ($message->user_id !== auth()->id()) {
            abort(403);
        }

        // Only allow toggling for autoresponder/queue type messages
        if (!$message->isQueueType()) {
            return response()->json([
                'success' => false,
                'message' => 'Tylko wiadomości typu Kolejka mogą mieć status aktywności.'
            ], 422);
        }

        $message->is_active = !($message->is_active ?? true);
        $message->save();

        return response()->json([
            'success' => true,
            'is_active' => $message->is_active,
            'message' => $message->is_active ? 'SMS została aktywowana.' : 'SMS została dezaktywowana.'
        ]);
    }

    /**
     * Get subscribers from selected lists for preview selection
     */
    public function previewSubscribers(Request $request)
    {
        $validated = $request->validate([
            'contact_list_ids' => 'nullable|array',
            'contact_list_ids.*' => 'exists:contact_lists,id',
            'search' => 'nullable|string|max:100',
        ]);

        $listIds = $validated['contact_list_ids'] ?? [];
        $search = $validated['search'] ?? null;

        // Get subscribers from selected lists
        $query = \App\Models\Subscriber::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('phone') // SMS requires phone number
            ->where('phone', '!=', '');

        if (!empty($listIds)) {
            $query->whereHas('contactLists', function ($q) use ($listIds) {
                $q->whereIn('contact_lists.id', $listIds);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $subscribers = $query
            ->select(['id', 'first_name', 'last_name', 'email', 'phone'])
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        return response()->json([
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Generate preview content with placeholders substituted
     */
    public function preview(Request $request, \App\Services\PlaceholderService $placeholderService)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'subscriber_id' => 'nullable|exists:subscribers,id',
        ]);

        try {
            $content = $validated['content'];
            $subscriberId = $validated['subscriber_id'] ?? null;

            if ($subscriberId) {
                $subscriber = \App\Models\Subscriber::find($subscriberId);

                // Security: verify subscriber belongs to user
                if ($subscriber && $subscriber->user_id === auth()->id()) {
                    $content = $placeholderService->replacePlaceholders($content, $subscriber, [
                        'unsubscribe_link' => $placeholderService->generateUnsubscribeLink($subscriber),
                        'unsubscribe_url' => $placeholderService->generateUnsubscribeLink($subscriber),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
