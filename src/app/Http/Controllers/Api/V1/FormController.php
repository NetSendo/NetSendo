<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\SubscriptionForm;
use App\Services\Forms\FormBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Subscription forms (Forms in the browser): CRUD, duplication and the embed
 * codes the form builder's "Code" screen shows. Validation and side effects
 * (unique slug, default fields and styles, design presets, captcha secret
 * kept encrypted and write-only) mirror the web form builder.
 */
class FormController extends Controller
{
    use ManagesContactLists;

    private const STANDARD_FIELD_IDS = ['email', 'fname', 'lname', 'phone'];

    public function __construct(private FormBuilderService $builder)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $request->validate([
            'list_id' => 'nullable|integer',
            'status' => 'nullable|in:active,draft,disabled',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SubscriptionForm::with('contactList:id,name')
            ->forUser($request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('list_id')) {
            $query->forList((int) $request->input('list_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $page = $query->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'data' => collect($page->items())->map(fn (SubscriptionForm $form) => $this->summary($form))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, int $form): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $model = $this->findForm($request, $form);

        return $model ? $this->detail($model) : $this->formNotFound();
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $validated = $request->validate($this->rules($request, true));
        $attributes = $this->attributes($request, $validated, null);

        $form = SubscriptionForm::create([
            'user_id' => $request->user()->id,
            ...$attributes,
        ]);

        return $this->detail($form->fresh(), 201);
    }

    /**
     * Partial update (PUT and PATCH): only the keys sent change. `styles` are
     * merged key by key into the stored styles; `fields` replaces the field
     * list. An empty captcha_secret_key keeps the stored secret.
     */
    public function update(Request $request, int $form): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $model = $this->findForm($request, $form);

        if (!$model) {
            return $this->formNotFound();
        }

        $validated = $request->validate($this->rules($request, false));
        $attributes = $this->attributes($request, $validated, $model);

        $model->update($attributes);

        return $this->detail($model->fresh());
    }

    /**
     * Deleting a form that has already collected signups needs confirm=true;
     * its submission history is deleted with it (subscribers stay).
     */
    public function destroy(Request $request, int $form): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $model = $this->findForm($request, $form);

        if (!$model) {
            return $this->formNotFound();
        }

        $submissions = $model->submissions()->count();

        if ($submissions > 0 && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => "Form '{$model->name}' has {$submissions} submission(s); deleting it removes that history and breaks every page that embeds it. Re-send with confirm=true to delete it.",
                'submissions_count' => $submissions,
            ], 409);
        }

        $name = $model->name;
        $model->delete();

        return response()->json([
            'message' => "Form '{$name}' deleted",
            'form_id' => $form,
        ]);
    }

    public function duplicate(Request $request, int $form): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $model = $this->findForm($request, $form);

        if (!$model) {
            return $this->formNotFound();
        }

        return $this->detail($model->duplicate(), 201);
    }

    // ------------------------------------------------------------------

    private function rules(Request $request, bool $creating): array
    {
        $userId = $request->user()->id;
        $required = $creating ? 'required' : 'sometimes';

        $ownEmailList = Rule::exists('contact_lists', 'id')
            ->where('user_id', $userId)
            ->where('type', 'email')
            ->whereNull('deleted_at');

        return [
            'name' => [$required, 'string', 'max:255'],
            'contact_list_id' => [$required, 'integer', $ownEmailList],
            'status' => 'nullable|in:active,draft,disabled',
            'type' => 'nullable|in:inline,popup,embedded',
            'fields' => 'sometimes|array|min:1',
            'fields.*' => 'array',
            'fields.*.id' => ['required', 'string', 'regex:/^(email|fname|lname|phone|custom_[0-9]+)$/'],
            'fields.*.type' => 'nullable|string|max:50',
            'fields.*.label' => 'nullable|string|max:255',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.order' => 'nullable|integer',
            'styles' => 'nullable|array',
            'design_preset' => ['nullable', 'string', Rule::in(array_keys(SubscriptionForm::$designPresets))],
            'layout' => 'nullable|in:vertical,horizontal,grid',
            'label_position' => 'nullable|in:above,left,hidden',
            'show_placeholders' => 'nullable|boolean',
            'double_optin' => 'nullable|boolean',
            'require_policy' => 'nullable|boolean',
            'policy_url' => 'nullable|url|max:500',
            'redirect_url' => 'nullable|url|max:500',
            'use_list_redirect' => 'nullable|boolean',
            'success_title' => 'nullable|string|max:255',
            'success_message' => 'nullable|string',
            'error_message' => 'nullable|string',
            'coregister_lists' => 'nullable|array',
            'coregister_lists.*' => ['integer', $ownEmailList],
            'coregister_optional' => 'nullable|boolean',
            'captcha_enabled' => 'nullable|boolean',
            'captcha_provider' => 'nullable|in:recaptcha_v2,recaptcha_v3,hcaptcha,turnstile',
            'captcha_site_key' => 'nullable|string',
            'captcha_secret_key' => 'nullable|string',
            'honeypot_enabled' => 'nullable|boolean',
        ];
    }

    /**
     * Column values from validated input: fields completed from their
     * definitions, styles layered defaults → stored → preset → sent, and the
     * structure checked like the form builder does.
     */
    private function attributes(Request $request, array $validated, ?SubscriptionForm $form): array
    {
        $attributes = collect($validated)->except(['design_preset', 'styles', 'fields'])->all();

        // Non-nullable columns: an explicit null means "column default"
        foreach (['status', 'type', 'layout', 'label_position', 'show_placeholders', 'require_policy', 'coregister_optional', 'captcha_enabled', 'honeypot_enabled', 'use_list_redirect'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] === null) {
                unset($attributes[$column]);
            }
        }

        if (array_key_exists('fields', $validated)) {
            $attributes['fields'] = $this->completeFields($request, $validated['fields']);
        } elseif (!$form) {
            $attributes['fields'] = SubscriptionForm::$defaultFields;
        }

        if (array_key_exists('styles', $validated) || array_key_exists('design_preset', $validated) || !$form) {
            $preset = isset($validated['design_preset'])
                ? (SubscriptionForm::$designPresets[$validated['design_preset']]['styles'] ?? [])
                : [];
            // A preset restyles from the defaults, like picking it in the builder
            $base = ($form && !isset($validated['design_preset'])) ? ($form->styles ?? []) : [];

            $attributes['styles'] = array_merge(
                SubscriptionForm::$defaultStyles,
                $base,
                $preset,
                $validated['styles'] ?? []
            );
        }

        // Don't overwrite the stored captcha secret when none is sent
        if (empty($attributes['captcha_secret_key'])) {
            unset($attributes['captcha_secret_key']);
        }

        $errors = $this->builder->validateStructure([
            'name' => $attributes['name'] ?? $form?->name,
            'contact_list_id' => $attributes['contact_list_id'] ?? $form?->contact_list_id,
            'fields' => $attributes['fields'] ?? $form?->fields,
        ]);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $attributes;
    }

    /**
     * Fill in type/label/placeholder for fields given by id only, from the
     * same definitions the builder offers; custom_<id> must be a custom field
     * of this account.
     */
    private function completeFields(Request $request, array $fields): array
    {
        $standard = collect($this->builder->getAvailableFields()['standard'])->keyBy('id');

        $customIds = collect($fields)
            ->pluck('id')
            ->filter(fn ($id) => str_starts_with($id, 'custom_'))
            ->map(fn ($id) => (int) substr($id, 7))
            ->unique()
            ->values();

        $custom = $customIds->isEmpty()
            ? collect()
            : CustomField::whereIn('id', $customIds)
                ->where(function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)->orWhereNull('user_id');
                })
                ->get()
                ->keyBy('id');

        $missing = $customIds->reject(fn ($id) => $custom->has($id));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fields' => 'Unknown custom field(s): ' . $missing->map(fn ($id) => "custom_{$id}")->implode(', ') . '. See list_custom_fields.',
            ]);
        }

        $seen = [];
        $completed = [];

        foreach (array_values($fields) as $index => $field) {
            $id = $field['id'];

            if (isset($seen[$id])) {
                throw ValidationException::withMessages(['fields' => "Field '{$id}' is used more than once."]);
            }
            $seen[$id] = true;

            if (in_array($id, self::STANDARD_FIELD_IDS, true)) {
                $definition = $standard->get($id, []);
            } else {
                $customField = $custom->get((int) substr($id, 7));
                $label = $customField->label ?: $customField->name;
                $definition = [
                    'type' => $customField->type,
                    'label' => $label,
                    'placeholder' => $label,
                    'required' => false,
                    'custom_field_id' => $customField->id,
                ];
            }

            $completed[] = array_filter([
                'id' => $id,
                'type' => $field['type'] ?? $definition['type'] ?? 'text',
                'label' => $field['label'] ?? $definition['label'] ?? $id,
                'placeholder' => $field['placeholder'] ?? $definition['placeholder'] ?? '',
                // The email field is always required
                'required' => $id === 'email' ? true : (bool) ($field['required'] ?? $definition['required'] ?? false),
                'order' => $field['order'] ?? $index + 1,
                'custom_field_id' => $definition['custom_field_id'] ?? null,
            ], fn ($value) => $value !== null);
        }

        return $completed;
    }

    private function findForm(Request $request, int $id): ?SubscriptionForm
    {
        return SubscriptionForm::forUser($request->user()->id)->find($id);
    }

    private function formNotFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Form not found',
        ], 404);
    }

    private function summary(SubscriptionForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'status' => $form->status,
            'type' => $form->type,
            'contact_list_id' => $form->contact_list_id,
            'list' => $form->contactList ? ['id' => $form->contactList->id, 'name' => $form->contactList->name] : null,
            'submissions_count' => (int) $form->submissions_count,
            'last_submission_at' => $form->last_submission_at?->toISOString(),
            'hosted_url' => $form->iframe_url,
            'created_at' => $form->created_at?->toISOString(),
            'updated_at' => $form->updated_at?->toISOString(),
        ];
    }

    private function detail(SubscriptionForm $form, int $status = 200): JsonResponse
    {
        $form->loadMissing(['contactList:id,name', 'integrations']);

        $data = $this->summary($form) + [
            'fields' => $form->fields ?? [],
            'styles' => (object) ($form->styles ?? []),
            'layout' => $form->layout,
            'label_position' => $form->label_position,
            'show_placeholders' => (bool) $form->show_placeholders,
            'double_optin' => $form->double_optin,
            'require_policy' => (bool) $form->require_policy,
            'policy_url' => $form->policy_url,
            'redirect_url' => $form->redirect_url,
            'use_list_redirect' => (bool) $form->use_list_redirect,
            'success_title' => $form->success_title,
            'success_message' => $form->success_message,
            'error_message' => $form->error_message,
            'coregister_lists' => $form->coregister_lists ?? [],
            'coregister_optional' => (bool) $form->coregister_optional,
            'captcha_enabled' => (bool) $form->captcha_enabled,
            'captcha_provider' => $form->captcha_provider,
            'captcha_site_key' => $form->captcha_site_key,
            'captcha_secret_key_set' => !empty($form->getRawOriginal('captcha_secret_key')),
            'honeypot_enabled' => (bool) $form->honeypot_enabled,
            'integrations' => $form->integrations->map(fn ($integration) => [
                'id' => $integration->id,
                'type' => $integration->type,
                'name' => $integration->name,
                'status' => $integration->status,
            ])->values(),
            'urls' => [
                'hosted' => $form->iframe_url,
                'submit' => $form->public_url,
                'script' => $form->js_url,
            ],
            'embed' => [
                'html' => $this->builder->generateHtmlCode($form),
                'js' => $this->builder->generateJsCode($form),
                'iframe' => $this->builder->generateIframeCode($form),
            ],
        ];

        return response()->json(['data' => $data], $status);
    }
}
