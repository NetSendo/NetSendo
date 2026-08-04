<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Services\Lists\ListExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inline list export. The queued CSV export (ExportController) mails a link to
 * a human; this returns rows in the response so an API caller can work with
 * them directly, walking large lists via next_cursor.
 */
class ListExportController extends Controller
{
    use ManagesContactLists;

    public function __construct(
        protected ListExportService $exporter,
    ) {}

    public function data(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'subscribers:read')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'format' => 'nullable|in:' . implode(',', ListExportService::FORMATS),
            'fields' => 'nullable|array',
            'fields.*' => 'string',
            'status' => 'nullable|in:active,inactive,unsubscribed,bounced,all',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer',
            'subscribed_after' => 'nullable|date',
            'subscribed_before' => 'nullable|date',
            'engaged' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:' . ListExportService::MAX_LIMIT,
            'cursor' => 'nullable|integer|min:0',
            'delimiter' => 'nullable|string|max:4',
        ]);

        return response()->json([
            'data' => $this->exporter->export($list, $validated),
        ]);
    }

    /**
     * What can be exported — standard columns plus the account's custom fields.
     */
    public function fields(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        return response()->json([
            'data' => $this->exporter->availableFields($list),
        ]);
    }
}
