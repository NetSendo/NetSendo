<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\MessageFieldFilter;
use App\Services\Segmentation\SubscriberFieldFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the custom-field audience builder on the message form: which fields can
 * be filtered on, which values subscribers actually hold, and how many people
 * the current selection reaches.
 */
class MessageAudienceController extends Controller
{
    public function __construct(protected SubscriberFieldFilterService $filters)
    {
    }

    /**
     * Custom fields available for the selected lists — global ones plus the
     * fields defined for those lists.
     */
    public function fields(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list_ids' => 'nullable|array',
            'list_ids.*' => 'integer',
        ]);

        $listIds = $this->accessibleListIds($validated['list_ids'] ?? []);
        $fields = $this->filters->availableFields(auth()->id(), $listIds);

        return response()->json([
            'fields' => $fields->map(fn (CustomField $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type,
                'scope' => $field->scope,
                'options' => $field->options ?? [],
                'contact_list_id' => $field->contact_list_id,
                'contact_list_name' => $field->contactList?->name,
                'placeholder' => $field->placeholder,
                'operators' => MessageFieldFilter::operatorsForType($field->type),
            ])->values(),
        ]);
    }

    /**
     * Values stored for one field, most common first — the searchable source
     * for "which cities do my subscribers actually have?".
     */
    public function fieldValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_id' => 'required|integer',
            'list_ids' => 'nullable|array',
            'list_ids.*' => 'integer',
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $field = CustomField::where('user_id', auth()->id())
            ->where('id', $validated['field_id'])
            ->first();

        if (!$field) {
            return response()->json(['values' => []]);
        }

        $listIds = $this->accessibleListIds($validated['list_ids'] ?? []);

        return response()->json([
            'field' => [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $field->options ?? [],
            ],
            'values' => $this->filters->fieldValues(
                $field,
                $listIds,
                $validated['search'] ?? null,
                $validated['limit'] ?? 50
            ),
        ]);
    }

    /**
     * How many people the current list + filter selection reaches. Counts the
     * list-based audience only; individually picked CRM contacts are added on
     * top of it when the message is sent.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_list_ids' => 'nullable|array',
            'contact_list_ids.*' => 'integer',
            'excluded_list_ids' => 'nullable|array',
            'excluded_list_ids.*' => 'integer',
            'include_field_filters' => 'nullable|array',
            'exclude_field_filters' => 'nullable|array',
            'include_field_filter_match' => 'nullable|in:all,any',
            'exclude_field_filter_match' => 'nullable|in:all,any',
        ]);

        $includedListIds = $this->accessibleListIds($validated['contact_list_ids'] ?? []);
        $excludedListIds = $this->accessibleListIds($validated['excluded_list_ids'] ?? []);

        $estimate = $this->filters->estimate(
            $includedListIds,
            $excludedListIds,
            $this->hydrateFilters($validated['include_field_filters'] ?? []),
            $validated['include_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL,
            $this->hydrateFilters($validated['exclude_field_filters'] ?? []),
            $validated['exclude_field_filter_match'] ?? MessageFieldFilter::MATCH_ALL
        );

        return response()->json($estimate);
    }

    /**
     * Keep only the lists this user may actually read.
     *
     * @return array<int, int>
     */
    protected function accessibleListIds(array $listIds): array
    {
        if (empty($listIds)) {
            return [];
        }

        return auth()->user()->accessibleLists()
            ->whereIn('id', $listIds)
            ->pluck('id')
            ->all();
    }

    /**
     * Turn posted filter rows into unsaved models so the estimate runs through
     * exactly the same code as a real send. Rows pointing at a field the user
     * does not own are dropped.
     *
     * @return \Illuminate\Support\Collection<int, MessageFieldFilter>
     */
    protected function hydrateFilters(array $rows): \Illuminate\Support\Collection
    {
        $rows = collect($rows)->filter(fn ($row) => is_array($row) && !empty($row['custom_field_id']));

        if ($rows->isEmpty()) {
            return collect();
        }

        $fields = CustomField::where('user_id', auth()->id())
            ->whereIn('id', $rows->pluck('custom_field_id')->map(fn ($id) => (int) $id)->unique())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($fields) {
                $field = $fields->get((int) $row['custom_field_id']);

                if (!$field) {
                    return null;
                }

                $operator = $row['operator'] ?? MessageFieldFilter::OP_ANY_OF;

                if (!in_array($operator, MessageFieldFilter::OPERATORS, true)) {
                    return null;
                }

                $filter = new MessageFieldFilter([
                    'custom_field_id' => $field->id,
                    'operator' => $operator,
                    'values' => array_values((array) ($row['values'] ?? [])),
                ]);
                $filter->setRelation('customField', $field);

                return $filter;
            })
            ->filter()
            ->values();
    }
}
