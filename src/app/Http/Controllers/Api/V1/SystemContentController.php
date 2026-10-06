<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Models\ContactList;
use App\Services\PlaceholderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared copy-on-write handling for the system emails and system pages API.
 *
 * Rows with contact_list_id = NULL are the instance-wide global defaults; a
 * list override is a row with the same slug and that list's id. Reading for a
 * list returns its override when present, else the global. Writing for a list
 * creates the override on first use; writing without a list edits the global,
 * which only the account admin (not a team member) may do. Resetting deletes
 * the override; globals cannot be deleted. Records are addressed by slug plus
 * an optional list_id, mirroring the web settings screens.
 */
abstract class SystemContentController extends Controller
{
    use ManagesContactLists;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Human label used in error messages, e.g. "system email". */
    abstract protected function label(): string;

    /** Display order of the standard slugs (the web screen's order). */
    abstract protected function order(): array;

    /**
     * What each standard slug is for, and the placeholders only that slug
     * receives: slug => ['description' => string, 'placeholders' => [code => meaning]].
     */
    abstract protected function catalog(): array;

    /** Validation rules for the editable fields (list_id is handled here). */
    abstract protected function rules(): array;

    /** Type-specific fields of a presented row (title/subject, flags, url...). */
    abstract protected function presentFields(Model $row): array;

    /** Placeholders every row of this type receives, whatever its slug. */
    abstract protected function builtInPlaceholders(): array;

    /**
     * Turn validated input into the attributes to write.
     *
     * @param Model|null $target the row being edited (null when a list override is about to be created)
     * @return array|JsonResponse attributes, or a 422 response
     */
    abstract protected function attributesFor(array $validated, ?Model $target, ?Model $global, ?ContactList $list): array|JsonResponse;

    /** Attributes of a new list override copied from the global default. */
    abstract protected function copyOnWrite(Model $global, array $attributes): array;

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        [$list, $error] = $this->listFromRequest($request);
        if ($error) {
            return $error;
        }

        $model = $this->model();
        $globals = $model::whereNull('contact_list_id')->get()->keyBy('slug');
        $overrides = $list
            ? $model::where('contact_list_id', $list->id)->get()->keyBy('slug')
            : collect();

        $withContent = $request->boolean('include_content');

        $items = $this->sortSlugs($globals->keys()->merge($overrides->keys())->unique())
            ->map(function (string $slug) use ($globals, $overrides, $withContent) {
                $override = $overrides->get($slug);

                return $this->present($override ?? $globals->get($slug), $override !== null, $globals->get($slug), $withContent);
            })
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'scope' => $list ? 'list' : 'global',
                'list' => $list ? ['id' => $list->id, 'name' => $list->name] : null,
                'total' => $items->count(),
            ],
        ]);
    }

    public function show(Request $request, string $slug, PlaceholderService $placeholderService): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        [$list, $error] = $this->listFromRequest($request);
        if ($error) {
            return $error;
        }

        [$global, $override] = $this->rowsFor($slug, $list);
        if (!$global && !$override) {
            return $this->unknownSlug($slug);
        }

        $data = $this->present($override ?? $global, $override !== null, $global, true);
        $data['placeholders'] = $this->placeholdersFor($slug, $list, $request->user()->id, $placeholderService);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        [$list, $error] = $this->listFromRequest($request);
        if ($error) {
            return $error;
        }

        if (!$list && ($denied = $this->requireAdmin($request))) {
            return $denied;
        }

        [$global, $override] = $this->rowsFor($slug, $list);
        if (!$global && !$override) {
            return $this->unknownSlug($slug);
        }

        $validated = $request->validate($this->rules());
        $target = $list ? $override : $global;

        $attributes = $this->attributesFor($validated, $target, $global, $list);
        if ($attributes instanceof JsonResponse) {
            return $attributes;
        }

        if ($attributes === []) {
            return $this->badRequest('Nothing to update: send at least one editable field.');
        }

        $created = false;
        if ($target === null) {
            // Copy-on-write: customise the global default for this list.
            $model = $this->model();
            $target = $model::create(array_merge(
                $this->copyOnWrite($global, $attributes),
                ['slug' => $attributes['slug'] ?? $global->slug, 'name' => $global->name, 'contact_list_id' => $list->id]
            ));
            $created = true;
        } else {
            $target->update($attributes);
        }

        $target->refresh();
        // Re-resolve the global by the row's (possibly renamed) slug.
        [$globalNow] = $this->rowsFor($target->slug, null);
        $data = $this->present($target, $target->contact_list_id !== null, $globalNow, true);

        return response()->json([
            'data' => $data,
            'meta' => [
                'created_override' => $created,
                'warnings' => $this->warningsFor($target),
            ],
        ], $created ? 201 : 200);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        [$list, $error] = $this->listFromRequest($request);
        if ($error) {
            return $error;
        }

        if (!$list) {
            return $this->badRequest("Global default {$this->label()}s cannot be deleted. Pass list_id to reset that list's override to the global default.");
        }

        [$global, $override] = $this->rowsFor($slug, $list);
        if (!$global && !$override) {
            return $this->unknownSlug($slug);
        }

        $deleted = false;
        if ($override) {
            $override->delete();
            $deleted = true;
        }

        return response()->json([
            'data' => $global ? $this->present($global, false, $global, false) : null,
            'meta' => [
                'deleted' => $deleted,
                'message' => $deleted
                    ? 'List override removed; the list now uses the global default.'
                    : 'This list had no override; it already uses the global default.',
            ],
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: ContactList|null, 1: JsonResponse|null}
     */
    protected function listFromRequest(Request $request): array
    {
        $raw = $request->input('list_id');

        if ($raw === null || $raw === '') {
            return [null, null];
        }

        if (!is_numeric($raw) || (int) $raw <= 0) {
            return [null, $this->badRequest('list_id must be a positive integer.')];
        }

        $list = $this->findList($request, (int) $raw);

        return $list ? [$list, null] : [null, $this->listNotFound()];
    }

    protected function requireAdmin(Request $request): ?JsonResponse
    {
        if ($request->user()->isAdmin()) {
            return null;
        }

        return response()->json([
            'error' => 'Forbidden',
            'message' => "Only the account admin can edit the global default {$this->label()}s. Pass list_id to customise it for one of your lists instead.",
        ], 403);
    }

    /**
     * @return array{0: Model|null, 1: Model|null} [global, list override]
     */
    protected function rowsFor(string $slug, ?ContactList $list): array
    {
        $model = $this->model();

        $global = $model::where('slug', $slug)->whereNull('contact_list_id')->first();
        $override = $list
            ? $model::where('slug', $slug)->where('contact_list_id', $list->id)->first()
            : null;

        return [$global, $override];
    }

    protected function present(Model $row, bool $custom, ?Model $global, bool $withContent): array
    {
        $entry = $this->catalog()[$row->slug] ?? null;

        $data = array_merge([
            'id' => $row->id,
            'slug' => $row->slug,
            'name' => $row->name,
            'description' => $entry['description']
                ?? ($global ? null : 'List-only entry without a global default; NetSendo\'s built-in flows do not use this slug.'),
            'source' => $custom ? 'list' : 'global',
            'is_custom' => $custom,
            'contact_list_id' => $row->contact_list_id,
            'global_id' => $global?->id,
        ], $this->presentFields($row));

        if ($withContent) {
            $data['content'] = $row->content;
        }

        $data['updated_at'] = $row->updated_at?->toIso8601String();

        return $data;
    }

    protected function placeholdersFor(string $slug, ?ContactList $list, int $userId, PlaceholderService $placeholderService): array
    {
        $specific = $this->catalog()[$slug]['placeholders'] ?? [];

        return [
            'for_this_slug' => $this->describePlaceholders($specific),
            'built_in' => $this->describePlaceholders($this->builtInPlaceholders()),
            'available' => $placeholderService->getAvailablePlaceholders($list?->id, $userId),
        ];
    }

    protected function describePlaceholders(array $placeholders): array
    {
        return collect($placeholders)
            ->map(fn (string $meaning, string $code) => ['placeholder' => $code, 'description' => $meaning])
            ->values()
            ->all();
    }

    /** Warnings to surface after a write; none by default. */
    protected function warningsFor(Model $row): array
    {
        return [];
    }

    protected function sortSlugs(Collection $slugs): Collection
    {
        $order = array_flip($this->order());

        return $slugs->sortBy(fn (string $slug) => [$order[$slug] ?? PHP_INT_MAX, $slug])->values();
    }

    protected function unknownSlug(string $slug): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => "Unknown {$this->label()} slug '{$slug}'. List the available slugs first.",
        ], 404);
    }
}
