<?php

namespace App\Http\Controllers;

use App\Models\CrmCompany;
use App\Models\CrmContact;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrmCompanyController extends Controller
{
    /**
     * Search companies for deal form autocomplete.
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();
        $query = $request->get('q', '');

        $companiesQuery = CrmCompany::forUser($userId);

        if (strlen($query) >= 1) {
            $companiesQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('domain', 'like', "%{$query}%");
            });
        }

        $companies = $companiesQuery
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($companies);
    }

    /**
     * Display a listing of companies.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $query = CrmCompany::forUser($userId)
            ->withCount('contacts')
            ->withCount('deals');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%");
            });
        }

        // Filter by industry
        if ($request->filled('industry')) {
            $query->where('industry', $request->industry);
        }

        $companies = $query->orderBy('name')->paginate(25)->withQueryString();

        // Get unique industries for filter
        $industries = CrmCompany::forUser($userId)
            ->whereNotNull('industry')
            ->distinct()
            ->pluck('industry');

        return Inertia::render('Crm/Companies/Index', [
            'companies' => $companies,
            'industries' => $industries,
            'filters' => $request->only(['search', 'industry']),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        return Inertia::render('Crm/Companies/Create');
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|size:2',
            'nip' => 'nullable|string|max:10',
            'regon' => 'nullable|string|max:14',
            'domain' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:5000',
        ]);

        $company = CrmCompany::create([
            ...$validated,
            'user_id' => $userId,
        ]);

        // Log activity
        $company->activities()->create([
            'user_id' => $userId,
            'created_by_id' => auth()->id(),
            'type' => 'system',
            'content' => 'Firma została utworzona',
        ]);

        return redirect()->route('crm.companies.show', $company)
            ->with('success', 'Firma została utworzona.');
    }

    /**
     * Display the specified company.
     */
    public function show(CrmCompany $company): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($company->user_id !== $userId) {
            abort(403);
        }

        $company->load([
            'contacts.subscriber',
            'contacts.owner',
            'deals.stage',
        ]);

        $activities = $company->activities()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return Inertia::render('Crm/Companies/Show', [
            'company' => $company,
            'activities' => $activities,
        ]);
    }

    /**
     * Show the form for editing the specified company.
     */
    public function edit(CrmCompany $company): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($company->user_id !== $userId) {
            abort(403);
        }

        return Inertia::render('Crm/Companies/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, CrmCompany $company): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($company->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|size:2',
            'nip' => 'nullable|string|max:10',
            'regon' => 'nullable|string|max:14',
            'domain' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:5000',
        ]);

        $company->update($validated);

        return redirect()->route('crm.companies.show', $company)
            ->with('success', 'Firma została zaktualizowana.');
    }

    /**
     * Remove the specified company.
     */
    public function destroy(Request $request, CrmCompany $company): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($company->user_id !== $userId) {
            abort(403);
        }

        $deleteContacts = $request->boolean('delete_contacts', false);

        if ($deleteContacts) {
            // Delete all associated contacts
            CrmContact::where('crm_company_id', $company->id)->delete();
        } else {
            // Just unlink contacts from company
            CrmContact::where('crm_company_id', $company->id)
                ->update(['crm_company_id' => null]);
        }

        $company->delete();

        $message = $deleteContacts
            ? 'Firma i powiązane kontakty zostały usunięte.'
            : 'Firma została usunięta. Kontakty zostały odłączone.';

        return redirect()->route('crm.companies.index')
            ->with('success', $message);
    }

    /**
     * Add a note to company.
     */
    public function addNote(Request $request, CrmCompany $company): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($company->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $company->activities()->create([
            'user_id' => $userId,
            'created_by_id' => auth()->id(),
            'type' => 'note',
            'content' => $validated['content'],
        ]);

        return redirect()->back()->with('success', 'Notatka została dodana.');
    }
}
