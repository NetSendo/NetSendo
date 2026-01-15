<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Models\CrmCompany;
use App\Models\CrmActivity;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrmContactController extends Controller
{
    /**
     * Display a listing of contacts.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $query = CrmContact::forUser($userId)
            ->with(['subscriber', 'company', 'owner']);

        // Apply filters
        if ($request->filled('status')) {
            $query->withStatus($request->status);
        }

        if ($request->filled('owner_id')) {
            $query->ownedBy($request->owner_id);
        }

        if ($request->filled('company_id')) {
            $query->where('crm_company_id', $request->company_id);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Global search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('subscriber', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })->orWhereHas('company', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $contacts = $query->paginate(25)->withQueryString();

        // Get filter options
        $companies = CrmCompany::forUser($userId)->orderBy('name')->get(['id', 'name']);
        $owners = User::where(function ($q) use ($userId) {
            $q->where('id', $userId)->orWhere('admin_user_id', $userId);
        })->get(['id', 'name']);
        $tags = Tag::where('user_id', $userId)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Crm/Contacts/Index', [
            'contacts' => $contacts,
            'companies' => $companies,
            'owners' => $owners,
            'tags' => $tags,
            'filters' => $request->only(['status', 'owner_id', 'company_id', 'source', 'search', 'sort', 'direction']),
        ]);
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $companies = CrmCompany::forUser($userId)->orderBy('name')->get(['id', 'name']);
        $owners = User::where(function ($q) use ($userId) {
            $q->where('id', $userId)->orWhere('admin_user_id', $userId);
        })->get(['id', 'name']);

        // If creating from existing subscriber
        $subscriber = null;
        if ($request->filled('subscriber_id')) {
            $subscriber = Subscriber::where('user_id', $userId)
                ->where('id', $request->subscriber_id)
                ->first();
        }

        return Inertia::render('Crm/Contacts/Create', [
            'companies' => $companies,
            'owners' => $owners,
            'subscriber' => $subscriber,
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $validated = $request->validate([
            'subscriber_id' => 'nullable|exists:subscribers,id',
            'email' => 'required_without:subscriber_id|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'crm_company_id' => 'nullable|exists:crm_companies,id',
            'owner_id' => 'nullable|exists:users,id',
            'status' => 'required|in:lead,prospect,client,dormant,archived',
            'source' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:255',
        ]);

        // Create or find subscriber
        if (empty($validated['subscriber_id'])) {
            // Use firstOrCreate to avoid duplicate entry error
            $subscriber = Subscriber::firstOrCreate(
                [
                    'user_id' => $userId,
                    'email' => $validated['email'],
                ],
                [
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'source' => 'crm_manual',
                ]
            );

            // Update subscriber data if it already existed but has new info
            if (!$subscriber->wasRecentlyCreated) {
                $subscriber->update(array_filter([
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                ]));
            }

            $validated['subscriber_id'] = $subscriber->id;
        }

        // Check if CRM contact already exists for this subscriber
        $existingContact = CrmContact::where('subscriber_id', $validated['subscriber_id'])->first();
        if ($existingContact) {
            return redirect()->route('crm.contacts.show', $existingContact)
                ->with('warning', 'Kontakt CRM już istnieje dla tego subskrybenta.');
        }

        $contact = CrmContact::create([
            'subscriber_id' => $validated['subscriber_id'],
            'user_id' => $userId,
            'crm_company_id' => $validated['crm_company_id'] ?? null,
            'owner_id' => $validated['owner_id'] ?? auth()->id(),
            'status' => $validated['status'],
            'source' => $validated['source'] ?? 'manual',
            'position' => $validated['position'] ?? null,
        ]);

        // Log activity
        $contact->logActivity('contact_created', 'Kontakt został utworzony');

        return redirect()->route('crm.contacts.show', $contact)
            ->with('success', 'Kontakt został utworzony.');
    }

    /**
     * Display the specified contact.
     */
    public function show(CrmContact $contact): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        // Authorization check
        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $contact->load([
            'subscriber.contactLists',
            'subscriber.tags',
            'company',
            'owner',
            'deals.stage',
            'tasks' => fn($q) => $q->pending()->orderBy('due_date'),
        ]);

        // Get activities timeline
        $activities = $contact->activities()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Get email/SMS campaign events from subscriber (mock for now)
        $campaignEvents = $this->getCampaignEvents($contact->subscriber);

        // Get owners for assignment
        $owners = User::where(function ($q) use ($userId) {
            $q->where('id', $userId)->orWhere('admin_user_id', $userId);
        })->get(['id', 'name']);

        $companies = CrmCompany::forUser($userId)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Crm/Contacts/Show', [
            'contact' => $contact,
            'activities' => $activities,
            'campaignEvents' => $campaignEvents,
            'owners' => $owners,
            'companies' => $companies,
        ]);
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, CrmContact $contact): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'crm_company_id' => 'nullable|exists:crm_companies,id',
            'owner_id' => 'nullable|exists:users,id',
            'status' => 'required|in:lead,prospect,client,dormant,archived',
            'source' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:255',
            'score' => 'nullable|integer|min:0|max:100',
        ]);

        $contact->update($validated);

        // Update subscriber data if provided
        if ($request->has('first_name') || $request->has('last_name') || $request->has('phone')) {
            $contact->subscriber->update($request->only(['first_name', 'last_name', 'phone']));
        }

        return redirect()->back()->with('success', 'Kontakt został zaktualizowany.');
    }

    /**
     * Remove the specified contact from CRM (keeps subscriber).
     */
    public function destroy(CrmContact $contact): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $contact->delete();

        return redirect()->route('crm.contacts.index')
            ->with('success', 'Kontakt został usunięty z CRM.');
    }

    /**
     * Add an activity to a contact.
     */
    public function addActivity(Request $request, CrmContact $contact): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:note,call,email,meeting',
            'content' => 'required|string|max:5000',
        ]);

        $contact->logActivity($validated['type'], $validated['content']);

        return redirect()->back()->with('success', 'Aktywność została dodana.');
    }

    /**
     * Get quick view data for drawer.
     */
    public function quickView(CrmContact $contact): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $contact->load(['subscriber', 'company', 'owner']);

        $recentActivities = $contact->activities()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'contact' => $contact,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Get campaign events for a subscriber (mock for MVP).
     */
    private function getCampaignEvents(?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            return [];
        }

        // TODO: Replace with real data from tracking tables
        return [
            'emailLists' => $subscriber->contactLists->where('type', 'email')->values(),
            'smsLists' => $subscriber->contactLists->where('type', 'sms')->values(),
            'stats' => [
                'emails_received' => 0, // TODO: from message queue
                'emails_opened' => $subscriber->opens_count,
                'emails_clicked' => $subscriber->clicks_count,
                'last_opened' => $subscriber->last_opened_at,
                'last_clicked' => $subscriber->last_clicked_at,
            ],
        ];
    }
}
