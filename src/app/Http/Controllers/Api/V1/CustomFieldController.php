<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
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
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $fields = $query->get();

        return response()->json([
            'data' => $fields->map(fn($field) => [
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
                'scope' => $field->scope,
                'contact_list_id' => $field->contact_list_id,
            ]),
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
        $user = $request->user();

        $field = CustomField::where('user_id', $user->id)->find($id);

        if (!$field) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Custom field not found',
            ], 404);
        }

        return response()->json([
            'data' => [
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
                'scope' => $field->scope,
                'contact_list_id' => $field->contact_list_id,
            ],
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
}
