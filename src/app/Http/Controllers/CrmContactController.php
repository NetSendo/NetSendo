<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Models\CrmCompany;
use App\Models\CrmActivity;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Tag;
use App\Models\Mailbox;
use App\Services\Mail\MailProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrmContactController extends Controller
{
    /**
     * Search subscribers for autocomplete in contact creation form.
     */
    public function searchSubscribers(Request $request): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['subscribers' => []]);
        }

        $subscribers = Subscriber::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->select(['id', 'email', 'first_name', 'last_name', 'phone'])
            ->orderByRaw("CASE WHEN email LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
            ->orderBy('email')
            ->limit(10)
            ->get();

        return response()->json(['subscribers' => $subscribers]);
    }
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

        // Get mailboxes for email sending
        $mailboxes = Mailbox::forUser($userId)->active()->get(['id', 'name', 'from_email', 'is_default']);

        return Inertia::render('Crm/Contacts/Show', [
            'contact' => $contact,
            'activities' => $activities,
            'campaignEvents' => $campaignEvents,
            'owners' => $owners,
            'companies' => $companies,
            'mailboxes' => $mailboxes,
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
     * Send an email to the contact.
     */
    public function sendEmail(Request $request, CrmContact $contact, MailProviderService $mailProviderService): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:50000',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
        ]);

        // Get recipient email from contact's subscriber
        $recipientEmail = $contact->subscriber?->email;
        if (!$recipientEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Kontakt nie ma przypisanego adresu email.',
            ], 422);
        }

        // Get mailbox
        $mailbox = null;
        if (!empty($validated['mailbox_id'])) {
            $mailbox = Mailbox::forUser($userId)
                ->active()
                ->find($validated['mailbox_id']);
        }

        if (!$mailbox) {
            $mailbox = $mailProviderService->getBestMailbox($userId, 'system');
        }

        if (!$mailbox) {
            return response()->json([
                'success' => false,
                'message' => 'Brak skonfigurowanej skrzynki pocztowej. Przejdź do Ustawień > Skrzynki pocztowe.',
            ], 422);
        }

        try {
            $provider = $mailProviderService->getProvider($mailbox);

            // Prepare HTML body with basic formatting
            $htmlBody = '<div style="font-family: sans-serif; line-height: 1.6;">'
                . nl2br(e($validated['body']))
                . '</div>';

            $result = $provider->send(
                to: $recipientEmail,
                subject: $validated['subject'],
                html: $htmlBody,
                text: $validated['body']
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Wystąpił błąd podczas wysyłania emaila.',
                ], 500);
            }

            // Increment mailbox sent count
            $mailbox->incrementSentCount();

            // Log activity
            $recipientName = trim(($contact->subscriber?->first_name ?? '') . ' ' . ($contact->subscriber?->last_name ?? ''));
            $contact->logActivity('email', "Wysłano email: {$validated['subject']}", [
                'subject' => $validated['subject'],
                'mailbox_name' => $mailbox->name,
                'mailbox_id' => $mailbox->id,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName ?: null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email został wysłany pomyślnie.',
            ]);

        } catch (\Exception $e) {
            \Log::error('CRM email send failed', [
                'contact_id' => $contact->id,
                'mailbox_id' => $mailbox->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas wysyłania emaila: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get campaign events for a subscriber.
     */
    private function getCampaignEvents(?Subscriber $subscriber): array
    {
        if (!$subscriber) {
            return [];
        }

        // Get real stats from tracking tables
        $opensCount = \App\Models\EmailOpen::where('subscriber_id', $subscriber->id)->count();
        $clicksCount = \App\Models\EmailClick::where('subscriber_id', $subscriber->id)->count();
        $lastOpened = \App\Models\EmailOpen::where('subscriber_id', $subscriber->id)
            ->latest('opened_at')
            ->value('opened_at');
        $lastClicked = \App\Models\EmailClick::where('subscriber_id', $subscriber->id)
            ->latest('clicked_at')
            ->value('clicked_at');
        $emailsReceived = \App\Models\MessageQueueEntry::where('subscriber_id', $subscriber->id)
            ->where('status', 'sent')
            ->count();

        return [
            'emailLists' => $subscriber->contactLists->where('type', 'email')->values(),
            'smsLists' => $subscriber->contactLists->where('type', 'sms')->values(),
            'stats' => [
                'emails_received' => $emailsReceived,
                'emails_opened' => $opensCount,
                'emails_clicked' => $clicksCount,
                'last_opened' => $lastOpened,
                'last_clicked' => $lastClicked,
            ],
        ];
    }
}
