<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SubscriberUnsubscribed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Account-wide suppression list — addresses that must never be mailed again,
 * regardless of which list they appear on.
 */
class SuppressionController extends Controller
{
    use ManagesContactLists;

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'subscribers:read')) {
            return $denied;
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = SuppressionList::where('user_id', $request->user()->id)
            ->orderByDesc('suppressed_at');

        if (!empty($validated['search'])) {
            $query->where('email', 'like', '%' . $validated['search'] . '%');
        }

        if (!empty($validated['reason'])) {
            $query->where('reason', $validated['reason']);
        }

        $paginator = $query->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Suppress one or more addresses and pull them off every list.
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'subscribers:write')) {
            return $denied;
        }

        $validated = $request->validate([
            'emails' => 'required|array|min:1|max:1000',
            'emails.*' => 'string|email',
            'reason' => 'nullable|string|max:100',
            'unsubscribe_existing' => 'nullable|boolean',
        ]);

        $userId = $request->user()->id;
        $reason = $validated['reason'] ?? 'api';
        $unsubscribe = $validated['unsubscribe_existing'] ?? true;

        $suppressed = 0;
        $unsubscribed = 0;

        foreach ($validated['emails'] as $email) {
            $email = mb_strtolower(trim($email));

            SuppressionList::suppress($userId, $email, $reason);
            $suppressed++;

            if (!$unsubscribe) {
                continue;
            }

            $subscriber = Subscriber::where('user_id', $userId)->where('email', $email)->first();

            if (!$subscriber) {
                continue;
            }

            foreach ($subscriber->contactLists as $list) {
                if ($list->pivot->status === 'unsubscribed') {
                    continue;
                }

                $subscriber->contactLists()->updateExistingPivot($list->id, [
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => now(),
                ]);

                event(new SubscriberUnsubscribed($subscriber, $list, $reason));
                $unsubscribed++;
            }
        }

        Log::info('API suppression added', [
            'user_id' => $userId,
            'count' => $suppressed,
            'reason' => $reason,
        ]);

        return response()->json([
            'data' => [
                'suppressed' => $suppressed,
                'memberships_unsubscribed' => $unsubscribed,
                'reason' => $reason,
            ],
            'message' => sprintf('%d address(es) suppressed.', $suppressed),
        ], 201);
    }

    /**
     * Lift suppression. Does not re-subscribe anyone — consent must be given
     * again through a normal signup path.
     */
    public function destroy(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'subscribers:write')) {
            return $denied;
        }

        $validated = $request->validate([
            'emails' => 'required|array|min:1|max:1000',
            'emails.*' => 'string|email',
        ]);

        $userId = $request->user()->id;
        $removed = 0;

        foreach ($validated['emails'] as $email) {
            if (SuppressionList::unsuppress($userId, mb_strtolower(trim($email)))) {
                $removed++;
            }
        }

        return response()->json([
            'data' => ['removed' => $removed],
            'message' => sprintf(
                '%d address(es) removed from suppression. They are not re-subscribed — consent must be collected again.',
                $removed
            ),
        ]);
    }
}
