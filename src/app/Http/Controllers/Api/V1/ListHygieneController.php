<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Services\Lists\ListHygieneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * List hygiene: report what is wrong with a list, then fix it.
 *
 * clean() and dedupe() default to dry_run=true — the caller must explicitly
 * opt into writes, and the destructive actions (delete, suppress) additionally
 * require confirm=true.
 */
class ListHygieneController extends Controller
{
    use ManagesContactLists;

    /** Actions that cannot be undone by the caller. */
    private const DESTRUCTIVE_ACTIONS = ['delete', 'suppress'];

    public function __construct(
        protected ListHygieneService $hygiene,
    ) {}

    /**
     * Full health report — counts, samples, score and ordered recommendations.
     */
    public function analyze(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'unconfirmed_after_days' => 'nullable|integer|min:1|max:365',
            'never_engaged_after_days' => 'nullable|integer|min:1|max:1095',
            'dormant_after_days' => 'nullable|integer|min:1|max:1095',
            'soft_bounce_threshold' => 'nullable|integer|min:1|max:20',
            'sample_size' => 'nullable|integer|min:0|max:50',
            'max_scan' => 'nullable|integer|min:1|max:' . ListHygieneService::MAX_SCAN,
        ]);

        return response()->json([
            'data' => $this->hygiene->analyze($list, $validated),
        ]);
    }

    /**
     * Apply an action to members matching the selected issue categories.
     */
    public function clean(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*' => ['string', Rule::in(ListHygieneService::CATEGORIES)],
            'action' => ['required', Rule::in(ListHygieneService::ACTIONS)],
            'tag' => 'nullable|string|max:255',
            'dry_run' => 'nullable|boolean',
            'confirm' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:' . ListHygieneService::MAX_SCAN,
            'reason' => 'nullable|string|max:255',
            'unconfirmed_after_days' => 'nullable|integer|min:1|max:365',
            'never_engaged_after_days' => 'nullable|integer|min:1|max:1095',
            'dormant_after_days' => 'nullable|integer|min:1|max:1095',
            'soft_bounce_threshold' => 'nullable|integer|min:1|max:20',
            'max_scan' => 'nullable|integer|min:1|max:' . ListHygieneService::MAX_SCAN,
        ]);

        // dry_run defaults to true: a caller that forgets the flag gets a
        // report, never a mutation.
        $validated['dry_run'] = $request->has('dry_run') ? $request->boolean('dry_run') : true;

        if (!$validated['dry_run']
            && in_array($validated['action'], self::DESTRUCTIVE_ACTIONS, true)
            && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => "action='{$validated['action']}' is irreversible. Re-send with confirm=true, or run with dry_run=true first.",
            ], 409);
        }

        try {
            $result = $this->hygiene->clean($list, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        }

        if (!$validated['dry_run']) {
            Log::info('API list clean applied', [
                'list_id' => $list->id,
                'user_id' => $request->user()->id,
                'action' => $validated['action'],
                'categories' => $validated['categories'],
                'affected' => $result['affected'],
            ]);
        }

        return response()->json([
            'data' => $result,
            'message' => $validated['dry_run']
                ? sprintf('Dry run — %d member(s) would be affected by action "%s".', $result['matched'], $result['action'])
                : sprintf('%d member(s) processed with action "%s".', $result['affected'], $result['action']),
        ]);
    }

    /**
     * Merge members whose addresses resolve to the same mailbox.
     */
    public function dedupe(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'dry_run' => 'nullable|boolean',
            'confirm' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:5000',
            'keep' => 'nullable|in:oldest,most_engaged',
        ]);

        $validated['dry_run'] = $request->has('dry_run') ? $request->boolean('dry_run') : true;

        if (!$validated['dry_run'] && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => 'Merging deletes the duplicate records. Re-send with confirm=true, or run with dry_run=true first.',
            ], 409);
        }

        $result = $this->hygiene->dedupe($list, $validated);

        return response()->json([
            'data' => $result,
            'message' => $validated['dry_run']
                ? sprintf('Dry run — %d duplicate group(s), %d record(s) would be merged.', $result['duplicate_groups'], $result['duplicate_records'])
                : sprintf('%d duplicate record(s) merged.', $result['merged']),
        ]);
    }

    /**
     * Deliverability check: address syntax plus MX lookups per domain.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:10000',
            'status' => 'nullable|in:active,inactive,unsubscribed,bounced,all',
            'check_mx' => 'nullable|boolean',
            'max_domains' => 'nullable|integer|min:1|max:1000',
        ]);

        return response()->json([
            'data' => $this->hygiene->verify($list, $validated),
        ]);
    }

    /**
     * Static description of the categories and actions clean() understands, so
     * a caller can discover them instead of guessing.
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => ListHygieneService::CATEGORIES,
                'actions' => ListHygieneService::ACTIONS,
                'destructive_actions' => self::DESTRUCTIVE_ACTIONS,
                'max_scan' => ListHygieneService::MAX_SCAN,
            ],
        ]);
    }
}
