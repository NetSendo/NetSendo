<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionForm;
use App\Models\ContactList;
use App\Services\Forms\FormBuilderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionFormController extends Controller
{
    protected FormBuilderService $formBuilder;

    public function __construct(FormBuilderService $formBuilder)
    {
        $this->formBuilder = $formBuilder;
    }

    /**
     * Display a listing of forms.
     */
    public function index(Request $request)
    {
        $query = SubscriptionForm::with('contactList')
            ->forUser(auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by list
        if ($request->filled('list_id')) {
            $query->forList($request->list_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $forms = $query->paginate(15)->withQueryString();

        $lists = ContactList::where('user_id', auth()->id())
            ->where('type', 'email')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Forms/Index', [
            'forms' => $forms,
            'lists' => $lists,
            'filters' => $request->only(['list_id', 'status', 'search']),
        ]);
    }

    /**
     * Show the form creation page.
     */
    public function create()
    {
        $lists = ContactList::where('user_id', auth()->id())
            ->where('type', 'email')
            ->orderBy('name')
            ->get(['id', 'name']);

        $availableFields = $this->formBuilder->getAvailableFields();

        return Inertia::render('Forms/Builder', [
            'form' => null,
            'lists' => $lists,
            'availableFields' => $availableFields,
            'defaultStyles' => SubscriptionForm::$defaultStyles,
            'defaultFields' => SubscriptionForm::$defaultFields,
            'designPresets' => $this->formBuilder->getDesignPresets(),
        ]);
    }

    /**
     * Store a newly created form.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_list_id' => 'required|exists:contact_lists,id',
            'status' => 'nullable|in:active,draft,disabled',
            'type' => 'nullable|in:inline,popup,embedded',
            'fields' => 'required|array|min:1',
            'styles' => 'nullable|array',
            'layout' => 'nullable|in:vertical,horizontal,grid',
            'label_position' => 'nullable|in:above,left,hidden',
            'show_placeholders' => 'nullable|boolean',
            'double_optin' => 'nullable|boolean',
            'require_policy' => 'nullable|boolean',
            'policy_url' => 'nullable|url|max:500',
            'redirect_url' => 'nullable|url|max:500',
            'success_message' => 'nullable|string',
            'error_message' => 'nullable|string',
            'coregister_lists' => 'nullable|array',
            'coregister_optional' => 'nullable|boolean',
            'captcha_enabled' => 'nullable|boolean',
            'captcha_provider' => 'nullable|in:recaptcha_v2,recaptcha_v3,hcaptcha,turnstile',
            'captcha_site_key' => 'nullable|string',
            'captcha_secret_key' => 'nullable|string',
            'honeypot_enabled' => 'nullable|boolean',
            'use_list_redirect' => 'nullable|boolean',
        ]);

        // Validate structure
        $errors = $this->formBuilder->validateStructure($validated);
        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        // Check list ownership
        $list = ContactList::where('id', $validated['contact_list_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $form = SubscriptionForm::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Formularz został utworzony');
    }

    /**
     * Display the form details.
     */
    public function show(SubscriptionForm $form)
    {
        $this->authorize('view', $form);

        return redirect()->route('forms.edit', $form);
    }

    /**
     * Show the form editor.
     */
    public function edit(SubscriptionForm $form)
    {
        $this->authorize('update', $form);

        $form->load('contactList', 'integrations');

        $lists = ContactList::where('user_id', auth()->id())
            ->where('type', 'email')
            ->orderBy('name')
            ->get(['id', 'name']);

        $availableFields = $this->formBuilder->getAvailableFields();

        return Inertia::render('Forms/Builder', [
            'form' => $form,
            'lists' => $lists,
            'availableFields' => $availableFields,
            'defaultStyles' => SubscriptionForm::$defaultStyles,
            'defaultFields' => SubscriptionForm::$defaultFields,
            'designPresets' => $this->formBuilder->getDesignPresets(),
        ]);
    }

    /**
     * Update the form.
     */
    public function update(Request $request, SubscriptionForm $form)
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_list_id' => 'required|exists:contact_lists,id',
            'status' => 'nullable|in:active,draft,disabled',
            'type' => 'nullable|in:inline,popup,embedded',
            'fields' => 'required|array|min:1',
            'styles' => 'nullable|array',
            'layout' => 'nullable|in:vertical,horizontal,grid',
            'label_position' => 'nullable|in:above,left,hidden',
            'show_placeholders' => 'nullable|boolean',
            'double_optin' => 'nullable|boolean',
            'require_policy' => 'nullable|boolean',
            'policy_url' => 'nullable|url|max:500',
            'redirect_url' => 'nullable|url|max:500',
            'success_message' => 'nullable|string',
            'error_message' => 'nullable|string',
            'coregister_lists' => 'nullable|array',
            'coregister_optional' => 'nullable|boolean',
            'captcha_enabled' => 'nullable|boolean',
            'captcha_provider' => 'nullable|in:recaptcha_v2,recaptcha_v3,hcaptcha,turnstile',
            'captcha_site_key' => 'nullable|string',
            'captcha_secret_key' => 'nullable|string',
            'honeypot_enabled' => 'nullable|boolean',
            'use_list_redirect' => 'nullable|boolean',
        ]);

        // Don't overwrite captcha_secret_key if not provided
        if (empty($validated['captcha_secret_key'])) {
            unset($validated['captcha_secret_key']);
        }

        $form->update($validated);

        return back()->with('success', 'Formularz został zapisany');
    }

    /**
     * Remove the form.
     */
    public function destroy(SubscriptionForm $form)
    {
        $this->authorize('delete', $form);

        $form->delete();

        return redirect()
            ->route('forms.index')
            ->with('success', 'Formularz został usunięty');
    }

    /**
     * Duplicate a form.
     */
    public function duplicate(SubscriptionForm $form)
    {
        $this->authorize('view', $form);

        $newForm = $form->replicate();
        $newForm->name = "[KOPIA] " . $form->name;
        $newForm->slug = SubscriptionForm::generateUniqueSlug();
        $newForm->status = 'draft';
        $newForm->submissions_count = 0;
        $newForm->last_submission_at = null;
        $newForm->save();

        return redirect()
            ->route('forms.edit', $newForm)
            ->with('success', 'Formularz został zduplikowany');
    }

    /**
     * Show embed code generator.
     */
    public function code(SubscriptionForm $form)
    {
        $this->authorize('view', $form);

        $embedCodes = [
            'html' => $this->formBuilder->generateHtmlCode($form),
            'js' => $this->formBuilder->generateJsCode($form),
            'iframe' => $this->formBuilder->generateIframeCode($form),
        ];

        return Inertia::render('Forms/Code', [
            'form' => $form,
            'embedCodes' => $embedCodes,
        ]);
    }

    /**
     * Show form statistics.
     */
    public function stats(Request $request, SubscriptionForm $form)
    {
        $this->authorize('view', $form);

        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to = $request->get('to', now()->toDateString());

        $submissionService = app(\App\Services\Forms\FormSubmissionService::class);
        $stats = $submissionService->getStats($form, $from, $to);

        $recentSubmissions = $form->submissions()
            ->with('subscriber:id,email,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('Forms/Stats', [
            'form' => $form,
            'stats' => $stats,
            'recentSubmissions' => $recentSubmissions,
            'dateRange' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Toggle form status (activate/deactivate).
     */
    public function toggleStatus(SubscriptionForm $form)
    {
        $this->authorize('update', $form);

        // Toggle between active and disabled
        $newStatus = $form->status === 'active' ? 'disabled' : 'active';
        $form->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? 'Formularz został aktywowany'
            : 'Formularz został dezaktywowany';

        return back()->with('success', $message);
    }
}

