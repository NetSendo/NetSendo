<?php

namespace App\Http\Requests;

use App\Services\Lists\SubscriberExportService;
use App\Services\Lists\SubscriberFileImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriberExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', Rule::in(array_keys(SubscriberExportService::PRESETS))],
            'format' => ['nullable', Rule::in(SubscriberExportService::FORMATS)],
            'scope' => ['nullable', Rule::in(['filtered', 'selected'])],
            'membership' => ['nullable', Rule::in(['active', 'all', 'unsubscribed'])],
            'date_format' => ['nullable', Rule::in(['iso', 'local'])],

            'fields' => ['nullable', 'array', 'max:200'],
            'fields.*' => ['string', 'max:100'],

            // A "select all in list" can hand over every id on the list, so the
            // ceiling sits at the importer's row limit rather than a token one.
            'ids' => ['nullable', 'array', 'max:' . SubscriberFileImportService::MAX_ROWS],
            'ids.*' => ['integer', 'min:1'],

            'list_id' => ['nullable', 'integer', 'min:1'],
            'list_type' => ['nullable', Rule::in(['email', 'sms'])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The options block SubscriberExportService expects.
     */
    public function exportOptions(): array
    {
        return [
            'preset' => $this->input('preset', 'netsendo'),
            'format' => $this->input('format', SubscriberExportService::FORMAT_CSV),
            'scope' => $this->input('scope', 'filtered'),
            'membership' => $this->input('membership', 'active'),
            'date_format' => $this->input('date_format', 'iso'),
            'fields' => $this->input('fields', []),
            'ids' => $this->input('ids', []),
            'list_id' => $this->input('list_id'),
            'list_type' => $this->input('list_type'),
            'search' => $this->input('search'),
        ];
    }
}
