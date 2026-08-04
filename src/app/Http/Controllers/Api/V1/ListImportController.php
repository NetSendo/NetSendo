<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Services\Lists\ListImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inline subscriber import: CSV/TSV text, JSON records or a plain address
 * list, with a mandatory-by-default dry run so the caller can see exactly what
 * an import would do before it happens.
 */
class ListImportController extends Controller
{
    use ManagesContactLists;

    public function __construct(
        protected ListImportService $importer,
    ) {}

    /**
     * Parse and classify the payload without writing anything.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $this->validatePayload($request);

        try {
            $parsed = $this->importer->parse($validated, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'data' => $this->importer->preview($list, $parsed, $this->options($validated)),
        ]);
    }

    /**
     * Run the import. Pass dry_run=true to get the preview response instead.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        if (!$list->canAcceptSignups()) {
            return response()->json([
                'error' => 'Conflict',
                'message' => "List '{$list->name}' is not accepting signups (blocked or at its subscriber limit).",
            ], 409);
        }

        $validated = $this->validatePayload($request);
        $options = $this->options($validated);

        try {
            $parsed = $this->importer->parse($validated, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        }

        if ($request->boolean('dry_run')) {
            return response()->json([
                'data' => $this->importer->preview($list, $parsed, $options),
                'message' => 'Dry run — nothing was written.',
            ]);
        }

        $result = $this->importer->import($list, $parsed, $options);

        Log::info('API list import completed', [
            'list_id' => $list->id,
            'user_id' => $request->user()->id,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'reactivated' => $result['reactivated'],
        ]);

        return response()->json([
            'data' => $result,
            'message' => sprintf(
                'Import finished: %d created, %d updated, %d reactivated, %d skipped, %d invalid, %d failed.',
                $result['created'],
                $result['updated'],
                $result['reactivated'],
                $result['skipped'],
                $result['invalid'],
                $result['failed'],
            ),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'format' => 'nullable|in:' . implode(',', ListImportService::FORMATS),
            'data' => 'nullable|string|max:8000000',
            'records' => 'nullable|array|max:' . ListImportService::MAX_ROWS,
            'records.*' => 'array',
            'delimiter' => 'nullable|string|max:4',
            'has_header' => 'nullable|boolean',
            'column_mapping' => 'nullable|array',

            'dry_run' => 'nullable|boolean',
            'update_existing' => 'nullable|boolean',
            'skip_invalid' => 'nullable|boolean',
            'skip_role' => 'nullable|boolean',
            'skip_disposable' => 'nullable|boolean',
            'skip_suppressed' => 'nullable|boolean',
            'fix_typos' => 'nullable|boolean',
            'trigger_automations' => 'nullable|boolean',
            'detect_gender' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,unsubscribed',
            'source' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'sample_size' => 'nullable|integer|min:1|max:50',
        ]);
    }

    /**
     * Only forward keys the caller actually sent, so service defaults stay
     * authoritative for everything else.
     */
    private function options(array $validated): array
    {
        $keys = [
            'update_existing', 'skip_invalid', 'skip_role', 'skip_disposable', 'skip_suppressed',
            'fix_typos', 'trigger_automations', 'detect_gender', 'status', 'source', 'tags', 'sample_size',
        ];

        return array_intersect_key($validated, array_flip($keys));
    }
}
