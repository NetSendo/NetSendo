<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\ContactList;
use App\Services\PlaceholderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomFieldController extends Controller
{
    protected PlaceholderService $placeholderService;

    public function __construct(PlaceholderService $placeholderService)
    {
        $this->placeholderService = $placeholderService;
    }

    /**
     * Display a listing of global custom fields
     */
    public function index()
    {
        $fields = CustomField::where('user_id', Auth::id())
            ->global()
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Settings/Fields/Index', [
            'fields' => $fields,
            'standardFields' => $this->placeholderService->getStandardFields(),
        ]);
    }

    /**
     * Show the form for creating a new custom field
     */
    public function create(Request $request)
    {
        $listId = $request->query('list_id');
        $list = $listId ? ContactList::where('user_id', Auth::id())->findOrFail($listId) : null;

        return Inertia::render('Settings/Fields/Form', [
            'field' => null,
            'contactList' => $list,
            'fieldTypes' => $this->getFieldTypes(),
            'reservedNames' => CustomField::RESERVED_NAMES,
        ]);
    }

    /**
     * Store a newly created custom field
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/i',
                function ($attribute, $value, $fail) use ($request) {
                    if (CustomField::isReservedName($value)) {
                        $fail(__('Ta nazwa pola jest zarezerwowana przez system.'));
                    }
                    // Check uniqueness for this user
                    $exists = CustomField::where('user_id', Auth::id())
                        ->where('name', $value)
                        ->where('contact_list_id', $request->contact_list_id)
                        ->exists();
                    if ($exists) {
                        $fail(__('Pole o tej nazwie już istnieje.'));
                    }
                },
            ],
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(['text', 'number', 'date', 'select', 'checkbox', 'radio'])],
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'default_value' => 'nullable|string|max:255',
            'is_public' => 'boolean',
            'is_required' => 'boolean',
            'is_static' => 'boolean',
            'contact_list_id' => 'nullable|exists:contact_lists,id',
        ]);

        // Determine scope based on contact_list_id
        $validated['scope'] = $validated['contact_list_id'] ? 'list' : 'global';
        $validated['user_id'] = Auth::id();
        
        // Get next sort order
        $validated['sort_order'] = CustomField::where('user_id', Auth::id())
            ->where('contact_list_id', $validated['contact_list_id'])
            ->max('sort_order') + 1;

        // Convert options to JSON if provided
        if (!empty($validated['options']) && in_array($validated['type'], ['select', 'radio', 'checkbox'])) {
            $validated['options'] = array_values(array_filter($validated['options']));
        } else {
            $validated['options'] = null;
        }

        $field = CustomField::create($validated);

        if ($validated['contact_list_id']) {
            return redirect()
                ->route('mailing-lists.edit', ['mailing_list' => $validated['contact_list_id'], 'tab' => 'fields'])
                ->with('success', __('Pole zostało utworzone.'));
        }

        return redirect()
            ->route('settings.fields.index')
            ->with('success', __('Pole zostało utworzone.'));
    }

    /**
     * Show the form for editing a custom field
     */
    public function edit(CustomField $field)
    {
        $this->authorize('update', $field);

        return Inertia::render('Settings/Fields/Form', [
            'field' => $field,
            'contactList' => $field->contactList,
            'fieldTypes' => $this->getFieldTypes(),
            'reservedNames' => CustomField::RESERVED_NAMES,
        ]);
    }

    /**
     * Update the specified custom field
     */
    public function update(Request $request, CustomField $field)
    {
        $this->authorize('update', $field);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/i',
                function ($attribute, $value, $fail) use ($field) {
                    if (CustomField::isReservedName($value)) {
                        $fail(__('Ta nazwa pola jest zarezerwowana przez system.'));
                    }
                    // Check uniqueness for this user excluding current field
                    $exists = CustomField::where('user_id', Auth::id())
                        ->where('name', $value)
                        ->where('contact_list_id', $field->contact_list_id)
                        ->where('id', '!=', $field->id)
                        ->exists();
                    if ($exists) {
                        $fail(__('Pole o tej nazwie już istnieje.'));
                    }
                },
            ],
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(['text', 'number', 'date', 'select', 'checkbox', 'radio'])],
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'default_value' => 'nullable|string|max:255',
            'is_public' => 'boolean',
            'is_required' => 'boolean',
            'is_static' => 'boolean',
        ]);

        // Convert options to JSON if provided
        if (!empty($validated['options']) && in_array($validated['type'], ['select', 'radio', 'checkbox'])) {
            $validated['options'] = array_values(array_filter($validated['options']));
        } else {
            $validated['options'] = null;
        }

        $field->update($validated);

        if ($field->contact_list_id) {
            return redirect()
                ->route('mailing-lists.edit', ['mailing_list' => $field->contact_list_id, 'tab' => 'fields'])
                ->with('success', __('Pole zostało zaktualizowane.'));
        }

        return redirect()
            ->route('settings.fields.index')
            ->with('success', __('Pole zostało zaktualizowane.'));
    }

    /**
     * Remove the specified custom field
     */
    public function destroy(CustomField $field)
    {
        $this->authorize('delete', $field);

        $listId = $field->contact_list_id;
        
        // Delete all associated values first
        $field->values()->delete();
        $field->delete();

        if ($listId) {
            return redirect()
                ->route('mailing-lists.edit', ['mailing_list' => $listId, 'tab' => 'fields'])
                ->with('success', __('Pole zostało usunięte.'));
        }

        return redirect()
            ->route('settings.fields.index')
            ->with('success', __('Pole zostało usunięte.'));
    }

    /**
     * Update sort order of fields
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:custom_fields,id',
            'fields.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['fields'] as $fieldData) {
            CustomField::where('id', $fieldData['id'])
                ->where('user_id', Auth::id())
                ->update(['sort_order' => $fieldData['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get available placeholders for a list (API endpoint)
     */
    public function placeholders(Request $request)
    {
        $listId = $request->query('list_id');
        
        return response()->json(
            $this->placeholderService->getAvailablePlaceholders($listId, Auth::id())
        );
    }

    /**
     * Get fields for a specific list (API endpoint)
     */
    public function listFields(ContactList $list)
    {
        $this->authorize('view', $list);

        $fields = CustomField::where('user_id', Auth::id())
            ->forList($list->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json($fields);
    }

    /**
     * Get available field types with labels
     */
    protected function getFieldTypes(): array
    {
        return [
            ['value' => 'text', 'label' => __('Tekstowe')],
            ['value' => 'number', 'label' => __('Numeryczne')],
            ['value' => 'date', 'label' => __('Data')],
            ['value' => 'select', 'label' => __('Lista rozwijana')],
            ['value' => 'radio', 'label' => __('Przyciski opcji (radio)')],
            ['value' => 'checkbox', 'label' => __('Pole wyboru (checkbox)')],
        ];
    }
}
