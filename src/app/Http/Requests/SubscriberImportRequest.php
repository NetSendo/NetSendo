<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CustomField;

class SubscriberImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
            'contact_list_id' => ['required', 'exists:contact_lists,id'],
            'separator' => ['required', \Illuminate\Validation\Rule::in([',', ';', 'tab'])], // comma, semicolon, or tab
            'has_header' => ['nullable', 'boolean'],
            'column_mapping' => ['nullable', 'array'],
            'column_mapping.*' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (blank($value) || $value === 'ignore') {
                        return;
                    }

                    $allowed = ['email', 'phone', 'first_name', 'last_name'];
                    if (in_array($value, $allowed, true)) {
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
}
