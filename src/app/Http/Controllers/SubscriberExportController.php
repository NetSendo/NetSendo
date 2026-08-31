<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriberExportRequest;
use App\Services\Lists\SubscriberExportService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download of the subscriber table, in the shape the importer reads back.
 *
 * POST rather than GET: a "select all in list" hands over tens of thousands of
 * ids, which no query string survives. The response is a streamed file, so the
 * browser opens a download dialog and the page the request came from stays put.
 */
class SubscriberExportController extends Controller
{
    public function __construct(
        private readonly SubscriberExportService $exportService,
    ) {}

    public function export(SubscriberExportRequest $request): StreamedResponse
    {
        $options = $request->exportOptions();

        Log::info('Subscriber export requested', [
            'user_id' => $request->user()->id,
            'preset' => $options['preset'],
            'format' => $options['format'],
            'scope' => $options['scope'],
            'list_id' => $options['list_id'],
            'selected' => count($options['ids']),
        ]);

        return $this->exportService->download($request->user(), $options);
    }
}
