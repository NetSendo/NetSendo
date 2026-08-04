<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * In-app notifications for the account that owns the API key.
 *
 * This is how an automated process reports back to the human — "import
 * finished", "this list has 12% hard bounces", "clean-up needs your approval".
 * It only ever writes to the owner's own notification centre; it cannot send
 * anything to subscribers or third parties.
 */
class NotificationController extends Controller
{
    use ManagesContactLists;

    public function __construct(
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread_only' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit($validated['limit'] ?? 25);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->get();

        return response()->json([
            'data' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'action_url' => $n->action_url,
                'data' => $n->data,
                'read_at' => $n->read_at?->toISOString(),
                'created_at' => $n->created_at?->toISOString(),
            ])->all(),
            'meta' => [
                'unread' => Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            ],
        ]);
    }

    /**
     * Post a notification to the account owner's notification centre.
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'notifications:write')) {
            return $denied;
        }

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['info', 'success', 'warning', 'error'])],
            'title' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
            'action_url' => 'nullable|string|max:2048',
            'list_id' => 'nullable|integer',
            'data' => 'nullable|array',
        ]);

        $actionUrl = $validated['action_url'] ?? null;

        // A list-scoped notification links straight to that list.
        if ($actionUrl === null && !empty($validated['list_id'])) {
            $list = $this->findList($request, (int) $validated['list_id']);

            if (!$list) {
                return $this->badRequest('list_id does not point to one of your lists.');
            }

            $actionUrl = '/mailing-lists/' . $list->id;
        }

        $notification = $this->notifications->create(
            $request->user()->id,
            $validated['type'] ?? Notification::TYPE_INFO,
            $validated['title'],
            $validated['message'] ?? null,
            $actionUrl,
            array_merge($validated['data'] ?? [], ['origin' => 'api']),
        );

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'action_url' => $notification->action_url,
                'created_at' => $notification->created_at?->toISOString(),
            ],
            'message' => 'Notification delivered to the account owner.',
        ], 201);
    }
}
