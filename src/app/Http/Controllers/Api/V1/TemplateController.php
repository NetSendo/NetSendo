<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateCategory;
use App\Services\MjmlService;
use App\Services\PlaceholderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * API Controller for email templates (the "Templates" section of the panel).
 *
 * A template stores its body in one of two forms:
 * - `content`        – ready HTML (HTML/code templates, imports, API-created templates);
 * - `json_structure` – the visual builder's blocks, from which `mjml_content` is
 *                      generated server-side (MJML is compiled to HTML in the browser).
 * When both exist, the message editor uses `content`.
 *
 * Readable: the account's own templates and the system starter templates
 * (user_id NULL, is_public). Writable: only the account's own templates –
 * system templates are duplicated first.
 *
 * Endpoints:
 * - GET    /api/v1/templates                 - List templates (messages:read)
 * - GET    /api/v1/templates/{id}            - Full template (messages:read)
 * - POST   /api/v1/templates                 - Create template (messages:write)
 * - PUT    /api/v1/templates/{id}            - Update template (messages:write)
 * - DELETE /api/v1/templates/{id}            - Delete template (messages:write)
 * - POST   /api/v1/templates/{id}/duplicate  - Copy a template into the account (messages:write)
 * - POST   /api/v1/templates/{id}/preview    - Render placeholders (messages:read)
 * - GET    /api/v1/template-categories       - Categories (messages:read)
 */
class TemplateController extends Controller
{
    use ManagesContactLists;

    public const TYPES = ['email', 'insert', 'signature'];

    public function __construct(
        protected MjmlService $mjmlService,
        protected PlaceholderService $placeholderService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
            'type' => ['nullable', Rule::in([...self::TYPES, 'all'])],
            'source' => 'nullable|in:own,system,all',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $userId = $request->user()->id;
        $source = $validated['source'] ?? 'all';

        $query = match ($source) {
            'own' => Template::where('user_id', $userId),
            'system' => Template::starter(),
            default => $this->readable($userId),
        };

        $type = $validated['type'] ?? 'email';
        if ($type === 'email') {
            $query->emails();
        } elseif ($type !== 'all') {
            $query->where('type', $type);
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (!empty($validated['category'])) {
            $category = $validated['category'];
            $query->where(function (Builder $q) use ($category) {
                $q->where('category', $category)
                    ->orWhereHas('templateCategory', fn (Builder $c) => $c->where('slug', $category));
            });
        }

        if (!empty($validated['search'])) {
            $term = '%' . $validated['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        $templates = $query->with('templateCategory')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($templates->items())->map(fn (Template $t) => $this->summary($t))->all(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $template = $this->findReadable($request, $id);
        if (!$template) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->detail($template, $request->user()->id)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $userId = $request->user()->id;
        $validated = $request->validate(array_merge($this->rules($userId), [
            'name' => 'required|string|max:255',
        ]));

        $attributes = $this->prepare($this->withRawStructures($request, $validated), null);
        if ($attributes instanceof JsonResponse) {
            return $attributes;
        }

        $template = Template::create(array_merge(['type' => 'email'], $attributes, ['user_id' => $userId]));

        return response()->json([
            'data' => $this->detail($template->fresh(), $userId),
            'message' => 'Template created successfully',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $userId = $request->user()->id;
        $template = $this->findWritable($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $validated = $request->validate(array_merge($this->rules($userId), [
            'name' => 'sometimes|required|string|max:255',
        ]));

        $attributes = $this->prepare($this->withRawStructures($request, $validated), $template);
        if ($attributes instanceof JsonResponse) {
            return $attributes;
        }

        $template->update($attributes);

        return response()->json([
            'data' => $this->detail($template->fresh(), $userId),
            'message' => 'Template updated successfully',
        ]);
    }

    /**
     * Mirrors the panel: a soft delete. Campaigns keep their own copy of the
     * body (the template is copied into the message when it is chosen), so
     * nothing that was built from this template changes.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $template = $this->findWritable($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $usedBy = $this->usedByMessages($template, $request->user()->id);
        $template->delete();

        return response()->json([
            'data' => [
                'id' => $template->id,
                'deleted' => true,
                'used_by_messages' => $usedBy,
            ],
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Copy an own or system template into the account (the same model method
     * the panel's "Duplicate" uses). This is how a system template is edited.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $validated = $request->validate(['name' => 'nullable|string|max:255']);

        $template = $this->findReadable($request, $id);
        if (!$template) {
            return $this->notFound();
        }

        $userId = $request->user()->id;
        $clone = $template->duplicate();
        $clone->user_id = $userId;
        if (!empty($validated['name'])) {
            $clone->name = $validated['name'];
        }
        $clone->save();

        return response()->json([
            'data' => $this->detail($clone->fresh(), $userId),
            'message' => 'Template duplicated successfully',
        ], 201);
    }

    /**
     * Render the template's placeholders for one of the account's subscribers
     * (subscriber_id) or for sample data. HTML templates come back as HTML;
     * builder-only templates come back as MJML with placeholders rendered.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $validated = $request->validate([
            'subscriber_id' => 'nullable|integer',
            'list_id' => 'nullable|integer',
            'sample' => 'nullable|array',
            'sample.email' => 'nullable|string|max:255',
            'sample.first_name' => 'nullable|string|max:255',
            'sample.last_name' => 'nullable|string|max:255',
            'sample.phone' => 'nullable|string|max:50',
            'sample.gender' => 'nullable|in:male,female',
        ]);

        $template = $this->findReadable($request, $id);
        if (!$template) {
            return $this->notFound();
        }

        $userId = $request->user()->id;

        $list = null;
        if (!empty($validated['list_id'])) {
            $list = $this->findList($request, (int) $validated['list_id']);
            if (!$list) {
                return $this->listNotFound();
            }
        }

        [$body, $source] = $this->body($template);
        $preheader = (string) ($template->preheader ?? '');

        if (!empty($validated['subscriber_id'])) {
            $subscriber = Subscriber::where('user_id', $userId)->find($validated['subscriber_id']);
            if (!$subscriber) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Subscriber not found',
                ], 404);
            }

            $rendered = $this->placeholderService->processEmailContent($body, $preheader, $subscriber, $list);
            $body = $rendered['content'];
            $preheader = $rendered['subject'];
            $subscriberInfo = ['id' => $subscriber->id, 'email' => $subscriber->email, 'sample' => false];
        } else {
            $sample = array_merge([
                'email' => 'jan.kowalski@example.com',
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'phone' => '+48500100200',
                'gender' => 'male',
            ], array_filter($validated['sample'] ?? [], fn ($v) => $v !== null && $v !== ''));

            // Never saved: it only feeds the placeholder resolver.
            $subscriber = new Subscriber(array_merge($sample, ['user_id' => $userId]));
            $links = [
                'unsubscribe_link' => '#preview-unsubscribe',
                'unsubscribe_url' => '#preview-unsubscribe',
                'unsubscribe' => '#preview-unsubscribe',
                'unsubscribe_global' => '#preview-unsubscribe-global',
                'manage' => '#preview-manage',
                'manage_url' => '#preview-manage',
            ];

            $body = $this->placeholderService->replacePlaceholders($body, $subscriber, $links);
            $preheader = $this->placeholderService->replacePlaceholders($preheader, $subscriber, $links);
            $subscriberInfo = ['id' => null, 'email' => $sample['email'], 'sample' => true];
        }

        [$original] = $this->body($template);
        $check = $this->placeholderService->validatePlaceholders($original, $list?->id, $userId);

        return response()->json([
            'data' => [
                'template_id' => $template->id,
                'source' => $source,
                'html' => $source === 'mjml' ? null : $body,
                'mjml' => $source === 'mjml' ? $body : null,
                'preheader' => $preheader,
                'subscriber' => $subscriberInfo,
                'placeholders_used' => array_values($this->placeholderService->extractPlaceholders($original)),
                'unknown_placeholders' => array_values($check['invalid_placeholders']),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        // The panel seeds the system categories when the builder is opened.
        TemplateCategory::seedSystemCategories();

        $userId = $request->user()->id;
        $categories = TemplateCategory::query()
            ->where(fn (Builder $q) => $q->where('is_system', true)->orWhere('user_id', $userId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TemplateCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'color' => $c->color,
                'description' => $c->description,
                'is_system' => (bool) $c->is_system,
            ]);

        return response()->json(['data' => $categories]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function rules(int $userId): array
    {
        return [
            'description' => 'nullable|string|max:255',
            'preheader' => 'nullable|string|max:500',
            'type' => ['sometimes', Rule::in(self::TYPES)],
            'content' => 'nullable|string',
            'content_plain' => 'nullable|string',
            'json_structure' => 'nullable|array',
            'json_structure.blocks' => 'nullable|array',
            'json_structure.blocks.*' => 'array',
            'json_structure.blocks.*.type' => ['required', Rule::in(array_keys(TemplateBlock::BLOCK_TYPES))],
            'json_structure.blocks.*.content' => 'nullable|array',
            'json_structure.blocks.*.settings' => 'nullable|array',
            'settings' => 'nullable|array',
            'category' => 'nullable|string|max:50',
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('template_categories', 'id')->where(
                    fn ($q) => $q->where('is_system', true)->orWhere('user_id', $userId)
                ),
            ],
        ];
    }

    /**
     * Wildcard rules make validated() rebuild nested arrays from the matched
     * keys only; take the builder structure and settings verbatim instead so
     * block order and block fields the rules do not list are kept.
     */
    protected function withRawStructures(Request $request, array $validated): array
    {
        foreach (['json_structure', 'settings'] as $key) {
            if (array_key_exists($key, $validated)) {
                $validated[$key] = $request->input($key);
            }
        }

        return $validated;
    }

    /**
     * Turn validated input into model attributes: builder blocks get stable
     * ids and their MJML regenerated (as the panel does on save), a chosen
     * category also fills the legacy `category` slug.
     */
    protected function prepare(array $validated, ?Template $template): array|JsonResponse
    {
        $attributes = $validated;

        if (array_key_exists('json_structure', $validated) && !empty($validated['json_structure'])) {
            $structure = $validated['json_structure'];
            $structure['blocks'] = $this->withBlockIds($structure['blocks'] ?? []);
            $attributes['json_structure'] = $structure;
        }

        $structure = $attributes['json_structure'] ?? ($template?->json_structure);
        $structureTouched = array_key_exists('json_structure', $validated) || array_key_exists('settings', $validated);

        if ($structureTouched) {
            if (!empty($structure)) {
                $settings = $validated['settings'] ?? ($template ? $template->getSettingsWithDefaults() : Template::defaultSettings());
                try {
                    $attributes['mjml_content'] = $this->mjmlService->jsonToMjml($structure, $settings);
                } catch (\Throwable $e) {
                    return $this->badRequest('json_structure could not be converted to MJML: ' . $e->getMessage());
                }
            } elseif (array_key_exists('json_structure', $validated)) {
                $attributes['mjml_content'] = null;
            }
        }

        if (!empty($validated['category_id']) && empty($validated['category'])) {
            $attributes['category'] = TemplateCategory::find($validated['category_id'])?->slug;
        }

        return $attributes;
    }

    protected function withBlockIds(array $blocks): array
    {
        return array_map(function ($block) {
            if (!is_array($block)) {
                return $block;
            }
            if (empty($block['id'])) {
                $block['id'] = 'block_' . (int) (microtime(true) * 1000) . '_' . Str::lower(Str::random(9));
            }
            if (isset($block['content']['columnBlocks']) && is_array($block['content']['columnBlocks'])) {
                $block['content']['columnBlocks'] = array_map(
                    fn ($column) => is_array($column) ? $this->withBlockIds($column) : $column,
                    $block['content']['columnBlocks']
                );
            }

            return $block;
        }, $blocks);
    }

    protected function readable(int $userId): Builder
    {
        return Template::query()->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere(fn (Builder $s) => $s->whereNull('user_id')->where('is_public', true));
        });
    }

    protected function findReadable(Request $request, int $id): ?Template
    {
        return $this->readable($request->user()->id)->with('templateCategory')->find($id);
    }

    protected function findWritable(Request $request, int $id): Template|JsonResponse
    {
        $template = $this->findReadable($request, $id);

        if (!$template) {
            return $this->notFound();
        }

        if ($template->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'System templates are read-only. Duplicate it (POST /templates/{id}/duplicate) and edit the copy.',
            ], 403);
        }

        return $template;
    }

    /**
     * @return array{0: string, 1: string} body and where it came from
     */
    protected function body(Template $template): array
    {
        if (!empty($template->content)) {
            return [$template->content, 'html'];
        }

        if (!empty($template->mjml_content)) {
            return [$template->mjml_content, 'mjml'];
        }

        if (!empty($template->json_structure)) {
            try {
                return [$this->mjmlService->jsonToMjml($template->json_structure, $template->getSettingsWithDefaults()), 'mjml'];
            } catch (\Throwable) {
                // fall through to empty
            }
        }

        return ['', 'empty'];
    }

    protected function usedByMessages(Template $template, int $userId): int
    {
        return Message::where('user_id', $userId)->where('template_id', $template->id)->count();
    }

    protected function editor(Template $template): string
    {
        if (!empty($template->content)) {
            return 'html';
        }

        return $template->hasBlocksStructure() ? 'builder' : 'empty';
    }

    protected function summary(Template $template): array
    {
        $category = $template->templateCategory;

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'preheader' => $template->preheader,
            'type' => $template->type ?? 'email',
            'category' => $template->category,
            'category_id' => $template->category_id,
            'category_data' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
            ] : null,
            'is_system' => $template->user_id === null,
            'editable' => $template->user_id !== null,
            'editor' => $this->editor($template),
            'has_html' => !empty($template->content),
            'has_blocks' => $template->hasBlocksStructure(),
            'thumbnail' => $template->thumbnail,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    protected function detail(Template $template, int $userId): array
    {
        return array_merge($this->summary($template), [
            'html' => $template->content,
            'content_plain' => $template->content_plain,
            'mjml' => $template->mjml_content,
            'json_structure' => $template->json_structure,
            'settings' => $template->getSettingsWithDefaults(),
            'used_by_messages' => $template->user_id === $userId ? $this->usedByMessages($template, $userId) : 0,
        ]);
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Template not found',
        ], 404);
    }
}
