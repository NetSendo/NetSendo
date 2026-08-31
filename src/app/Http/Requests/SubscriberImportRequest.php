<?php

namespace App\Http\Requests;

use App\Models\ContactList;
use App\Models\CustomField;
use App\Services\Lists\SubscriberFileImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriberImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,tsv,json,ndjson,jsonl', 'max:20480'], // 20MB max
            // Optional only in the round-trip case: a file carrying a `lists`
            // column can restore memberships without a single target list.
            'contact_list_id' => [
                Rule::requiredIf(fn () => !$this->boolean('restore_memberships')),
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if (blank($value)) {
                        return;
                    }

                    $list = ContactList::find($value);

                    if (!$list || !$this->user()->canAccessList($list)) {
                        $fail(__('subscribers.import.errors.list_not_accessible'));
                    }
                },
            ],
            'separator' => ['nullable', Rule::in([',', ';', 'tab', '|'])],
            'has_header' => ['nullable', 'boolean'],

            'update_mode' => ['nullable', Rule::in(SubscriberFileImportService::UPDATE_MODES)],
            'signup_events' => ['nullable', Rule::in(SubscriberFileImportService::SIGNUP_MODES)],
            'restore_memberships' => ['nullable', 'boolean'],
            'create_missing_lists' => ['nullable', 'boolean'],
            'apply_tags' => ['nullable', 'boolean'],
            'replace_tags' => ['nullable', 'boolean'],
            'apply_dates' => ['nullable', 'boolean'],
            'detect_gender' => ['nullable', 'boolean'],

            'column_mapping' => ['nullable', 'array'],
            'column_mapping.*' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (blank($value) || $value === 'ignore') {
                        return;
                    }

                    if (in_array($value, SubscriberFileImportService::MAPPABLE_FIELDS, true)) {
                        return;
                    }

                    if (str_starts_with($value, 'custom_field:')) {
                        $fieldId = (int) substr($value, strlen('custom_field:'));
                        $exists = CustomField::where('user_id', $this->user()->id)
                            ->where('id', $fieldId)
                            ->exists();

                        if (!$exists) {
                            $fail('Invalid custom field selection.');
                        }

                        return;
                    }

                    $fail('Invalid column mapping selection.');
                },
            ],
        ];
    }

    /**
     * How the file should be read.
     */
    public function parseOptions(): array
    {
        return [
            'separator' => $this->input('separator', ','),
            'has_header' => $this->has('has_header') ? $this->boolean('has_header') : null,
            'column_mapping' => $this->input('column_mapping', []),
        ];
    }

    /**
     * What the import should do with the parsed rows.
     */
    public function importOptions(): array
    {
        return [
            'update_mode' => $this->input('update_mode', SubscriberFileImportService::UPDATE_FILL_EMPTY),
            'signup_events' => $this->input('signup_events', SubscriberFileImportService::SIGNUP_NEW_ONLY),
            'restore_memberships' => $this->boolean('restore_memberships'),
            'create_missing_lists' => $this->boolean('create_missing_lists'),
            'apply_tags' => $this->has('apply_tags') ? $this->boolean('apply_tags') : true,
            'replace_tags' => $this->boolean('replace_tags'),
            'apply_dates' => $this->boolean('apply_dates'),
            'detect_gender' => $this->has('detect_gender') ? $this->boolean('detect_gender') : true,
        ];
    }
}
