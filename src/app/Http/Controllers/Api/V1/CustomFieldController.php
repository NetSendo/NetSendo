<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomFieldController extends Controller
{
    use ManagesContactLists;

    /** Field types accepted by the web form (custom_fields.type enum). */
    public const TYPES = ['text', 'number', 'date', 'select', 'checkbox', 'radio'];

    /** Types whose `options` are kept; for the others options are cleared. */
    private const OPTION_TYPES = ['select', 'radio', 'checkbox'];

    private const SORTABLE = ['id', 'name', 'label', 'type', 'sort_order', 'created_at', 'updated_at'];
    /**
     * Get all custom fields for the user
     *
     * @group Custom Fields
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CustomField::where('user_id', $user->id);

        // Filter by scope (global, list)
        if ($request->has('scope')) {
            $query->where('scope', $request->scope);
        }

        // Filter by list ID (includes global + list-specific)
        if ($request->has('list_id')) {
            $query->forList((int) $request->list_id);
        }

        // Filter by public visibility only
        if ($request->boolean('public_only', false)) {
            $query->public();
        }

        // Search by name or label
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('label', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        // (whitelisted: the values used to reach orderBy unchecked)
        $sortBy = in_array($request->get('sort_by'), self::SORTABLE, true) ? $request->get('sort_by') : 'sort_order';
        $sortOrder = strtolower((string) $request->get('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        $fields = $query->get();

        return response()->json([
            'data' => $fields->map(fn ($field) => $this->present($field)),
        ]);
    }

    /**
     * Get a single custom field
     *
     * @group Custom Fields
     * @authenticated
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $field = $this->findField($request, $id);

        if (!$field) {
            return $this->fieldNotFound();
        }

        return response()->json([
            'data' => $this->present($field),
        ]);
    }

    /**
     * Get available placeholders (system + custom fields)
     *
     * @group Custom Fields
     * @authenticated
     */
    public function placeholders(Request $request): JsonResponse
    {
        $user = $request->user();

        // System placeholders
        $systemPlaceholders = [
            ['name' => 'email', 'placeholder' => '[[email]]', 'label' => 'Email', 'type' => 'system'],
            ['name' => 'fname', 'placeholder' => '[[fname]]', 'label' => 'First Name', 'type' => 'system'],
            ['name' => '!fname', 'placeholder' => '[[!fname]]', 'label' => 'First Name (Vocative)', 'type' => 'system'],
            ['name' => 'lname', 'placeholder' => '[[lname]]', 'label' => 'Last Name', 'type' => 'system'],
            ['name' => 'phone', 'placeholder' => '[[phone]]', 'label' => 'Phone', 'type' => 'system'],
            ['name' => 'sex', 'placeholder' => '[[sex]]', 'label' => 'Gender', 'type' => 'system'],
            ['name' => 'unsubscribe', 'placeholder' => '[[unsubscribe]]', 'label' => 'Unsubscribe Link', 'type' => 'link'],
            ['name' => 'manage', 'placeholder' => '[[manage]]', 'label' => 'Manage Preferences Link', 'type' => 'link'],
        ];

        // Custom fields for this user
        $customFields = CustomField::where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($field) => [
                'name' => $field->name,
                'placeholder' => $field->placeholder,
                'label' => $field->label,
                'type' => 'custom',
                'field_type' => $field->type,
            ])
            ->toArray();

        return response()->json([
            'data' => [
                'system' => $systemPlaceholders,
                'custom' => $customFields,
            ],
        ]);
    }

    /**
     * Create a custom field. Same rules as the web form (Settings → Fields and
     * the list "fields" tab): technical name a-z/0-9/_ starting with a letter,
     * not reserved, unique per account and scope; scope follows contact_list_id
     * (none = global, otherwise list-specific). Unlike the web form, the list
     * must belong to the key owner, and select/radio fields need options.
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $userId = $request->user()->id;
        $listId = $request->filled('contact_list_id') ? $request->input('contact_list_id') : null;

        $validated = $request->validate([
            'name' => array_merge($this->nameRules(), [
                function ($attribute, $value, $fail) use ($userId, $listId) {
                    if (is_string($value) && $this->nameTaken($userId, $value, is_numeric($listId) ? (int) $listId : null)) {
                        $fail(__('Pole o tej nazwie już istnieje.'));
                    }
                },
            ]),
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(self::TYPES)],
            'options' => ['nullable', 'array', 'required_if:type,select,radio'],
            'options.*' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'is_public' => 'boolean',
            'is_required' => 'boolean',
            'is_static' => 'boolean',
            'contact_list_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$this->findList($request, (int) $value)) {
                        $fail('The selected contact list does not exist in this account.');
                    }
                },
            ],
        ]);

        $validated['contact_list_id'] = $validated['contact_list_id'] ?? null;
        $validated['scope'] = $validated['contact_list_id'] ? 'list' : 'global';
        $validated['user_id'] = $userId;
        $validated['sort_order'] = CustomField::where('user_id', $userId)
            ->where('contact_list_id', $validated['contact_list_id'])
            ->max('sort_order') + 1;
        $validated['options'] = $this->normalizeOptions($validated['type'], $validated['options'] ?? null);

        $field = CustomField::create($validated);

        return response()->json([
            'data' => $this->present($field->fresh()),
            'message' => "Custom field '{$field->name}' created. Use {$field->placeholder} in message content.",
        ], 201);
    }

    /**
     * Update a custom field (partial: only the fields sent are changed). The
     * scope / contact_list_id cannot be changed, as in the web form.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $field = $this->findField($request, $id);

        if (!$field) {
            return $this->fieldNotFound();
        }

        $validated = $request->validate([
            'name' => array_merge(['sometimes'], $this->nameRules(), [
                function ($attribute, $value, $fail) use ($field) {
                    if (is_string($value) && $this->nameTaken($field->user_id, $value, $field->contact_list_id, $field->id)) {
                        $fail(__('Pole o tej nazwie już istnieje.'));
                    }
                },
            ]),
            'label' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['sometimes', 'required', Rule::in(self::TYPES)],
            'options' => 'nullable|array',
            'options.*' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'is_public' => 'sometimes|boolean',
            'is_required' => 'sometimes|boolean',
            'is_static' => 'sometimes|boolean',
        ]);

        if (array_key_exists('type', $validated) || array_key_exists('options', $validated)) {
            $type = $validated['type'] ?? $field->type;
            $options = array_key_exists('options', $validated) ? $validated['options'] : $field->options;
            $validated['options'] = $this->normalizeOptions($type, $options);

            if (in_array($type, ['select', 'radio'], true) && empty($validated['options'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'options' => "A {$type} field needs at least one option.",
                ]);
            }
        }

        $field->update($validated);

        return response()->json([
            'data' => $this->present($field->fresh()),
            'message' => "Custom field '{$field->name}' updated",
        ]);
    }

    /**
     * Delete a custom field together with the values stored for subscribers
     * (as the web controller does). Requires confirm=true when any subscriber
     * has a value for it.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $field = $this->findField($request, $id);

        if (!$field) {
            return $this->fieldNotFound();
        }

        $valueCount = $field->values()->count();

        if ($valueCount > 0 && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => "Custom field '{$field->name}' holds values for {$valueCount} subscriber(s). Re-send with confirm=true to delete the field and those values.",
                'values_count' => $valueCount,
            ], 409);
        }

        $name = $field->name;

        DB::transaction(function () use ($field) {
            $field->values()->delete();
            $field->delete();
        });

        return response()->json([
            'message' => "Custom field '{$name}' deleted",
            'custom_field_id' => $id,
            'values_deleted' => $valueCount,
            'note' => "Messages that still use [[{$name}]] will render it empty.",
        ]);
    }

    private function nameRules(): array
    {
        return [
            'required',
            'string',
            'max:100',
            'regex:/^[a-z][a-z0-9_]*$/i',
            function ($attribute, $value, $fail) {
                if (is_string($value) && CustomField::isReservedName($value)) {
                    $fail(__('Ta nazwa pola jest zarezerwowana przez system.'));
                }
            },
        ];
    }

    private function nameTaken(int $userId, string $name, ?int $listId, ?int $ignoreId = null): bool
    {
        return CustomField::where('user_id', $userId)
            ->where('name', $name)
            ->where('contact_list_id', $listId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function normalizeOptions(string $type, ?array $options): ?array
    {
        if (empty($options) || !in_array($type, self::OPTION_TYPES, true)) {
            return null;
        }

        return array_values(array_filter($options));
    }

    private function findField(Request $request, int $id): ?CustomField
    {
        return CustomField::where('user_id', $request->user()->id)->find($id);
    }

    private function fieldNotFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Custom field not found',
        ], 404);
    }

    private function present(CustomField $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'label' => $field->label,
            'description' => $field->description,
            'type' => $field->type,
            'placeholder' => $field->placeholder,
            'options' => $field->options,
            'default_value' => $field->default_value,
            'is_public' => $field->is_public,
            'is_required' => $field->is_required,
            'is_static' => (bool) $field->is_static,
            'scope' => $field->scope,
            'contact_list_id' => $field->contact_list_id,
            'sort_order' => $field->sort_order,
        ];
    }
}
