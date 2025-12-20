<?php

namespace App\Http\Controllers;

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
            ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($message) => [
                'id' => $message->id,
                'subject' => $message->subject,
                'content' => $message->content, // SMS content usually short enough to show? Or maybe truncate
                'type' => $message->type, // broadcast, autoresponder
                'day' => $message->day,
                'status' => $message->status, // draft, scheduled, sent
                'created_at' => $message->created_at->format('Y-m-d H:i'),
                'list_name' => $message->contactLists->first()->name ?? '-',
            ]);

        $lists = ContactList::sms()->get(['id', 'name']);
        
        // Assuming groups are not used for SMS yet or shared? 
        // For now, only passing lists as per Create view. Index view had group_id filter but we can hide it or implement it similarly if needed.
        // Let's pass empty groups or fetch if needed. Existing Message/Index uses groups. 
        // We'll stick to Lists for SMS for now as per plan.
        
        return Inertia::render('Sms/Index', [
            'messages' => $messages,
            'filters' => $request->only(['search', 'type', 'list_id', 'sort', 'direction']),
            'lists' => $lists,
            'groups' => [], // Placeholder if we want to add group support later
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $lists = ContactList::sms()->get(['id', 'name']);

        return Inertia::render('Sms/Create', [
            'lists' => $lists,
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

        $lists = ContactList::sms()->get(['id', 'name']);

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
}
