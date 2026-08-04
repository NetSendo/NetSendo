<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Models\Subscriber;
use App\Services\Lists\ListActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Observability for lists: what happened, how the audience is performing, and
 * the full history of a single contact.
 */
class ListActivityController extends Controller
{
    use ManagesContactLists;

    public function __construct(
        protected ListActivityService $activity,
    ) {}

    /**
     * Chronological event feed (signups, confirmations, unsubscribes, bounces,
     * sends, opens, clicks).
     */
    public function feed(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'limit' => 'nullable|integer|min:1|max:500',
            'types' => 'nullable|array',
            'types.*' => ['string', Rule::in(ListActivityService::EVENT_TYPES)],
        ]);

        return response()->json([
            'data' => $this->activity->feed($list, $validated),
        ]);
    }

    /**
     * Growth, delivery and engagement metrics for a list.
     */
    public function engagement(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:read')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        return response()->json([
            'data' => $this->activity->engagement($list, $validated),
        ]);
    }

    /**
     * Timeline for one subscriber, across every list they belong to.
     */
    public function subscriber(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'subscribers:read')) {
            return $denied;
        }

        $subscriber = Subscriber::where('user_id', $request->user()->id)->find($id);

        if (!$subscriber) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:1095',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        return response()->json([
            'data' => $this->activity->subscriberActivity($subscriber, $validated),
        ]);
    }
}
