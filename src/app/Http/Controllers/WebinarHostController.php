<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use App\Models\WebinarSession;
use App\Services\Webinar\WebinarChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebinarHostController extends Controller
{
    public function __construct(protected WebinarChatService $chatService) {}

    /**
     * Get host dashboard data.
     */
    public function dashboard(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $session = $request->session_id
            ? WebinarSession::find($request->session_id)
            : null;

        $pendingMessages = $this->chatService->getPendingMessages($webinar, $session);
        $recentQuestions = $this->chatService->getQuestions($webinar, $session, true);

        return response()->json([
            'chat_settings' => $webinar->chat_settings_with_defaults,
            'viewers_count' => $webinar->getCurrentViewersCount(),
            'pending_messages_count' => $pendingMessages->count(),
            'pending_messages' => $pendingMessages->take(10)->map(fn($m) => $m->toBroadcast()),
            'unanswered_questions_count' => $recentQuestions->count(),
            'unanswered_questions' => $recentQuestions->take(10)->map(fn($q) => $q->toBroadcast()),
            'stats' => [
                'total_messages' => $webinar->chatMessages()->visible()->count(),
                'total_reactions' => $webinar->reactions()->count(),
            ],
        ]);
    }

    /**
     * Update chat settings.
     */
    public function updateChatSettings(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'mode' => 'sometimes|string|in:open,moderated,qa_only,host_only',
            'slow_mode_seconds' => 'sometimes|integer|min:0|max:120',
            'fake_viewers_base' => 'sometimes|integer|min:0|max:10000',
            'fake_viewers_variance' => 'sometimes|integer|min:0|max:500',
            'reactions_enabled' => 'sometimes|boolean',
            'allow_questions' => 'sometimes|boolean',
            'require_approval' => 'sometimes|boolean',
        ]);

        $webinar->updateChatSettings($validated);

        // Broadcast chat settings change to all viewers
        broadcast(new \App\Events\WebinarChatSettingsChanged($webinar))->toOthers();

        return response()->json([
            'success' => true,
            'chat_settings' => $webinar->fresh()->chat_settings_with_defaults,
        ]);
    }

    /**
     * Send announcement to all viewers.
     */
    public function sendAnnouncement(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'type' => 'sometimes|string|in:info,warning,success,promo',
            'session_id' => 'nullable|exists:webinar_sessions,id',
        ]);

        $session = $validated['session_id'] ?? null
            ? WebinarSession::find($validated['session_id'])
            : null;

        $announcement = $this->chatService->sendSystemMessage(
            $webinar,
            $validated['message'],
            $session
        );

        // Mark as announcement type
        $announcement->update([
            'metadata' => array_merge($announcement->metadata ?? [], [
                'announcement_type' => $validated['type'] ?? 'info',
            ]),
            'is_highlighted' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => $announcement->toBroadcast(),
        ]);
    }

    /**
     * Trigger product pin manually.
     */
    public function triggerProduct(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'product_id' => 'required|exists:webinar_products,id',
            'action' => 'required|string|in:pin,unpin',
        ]);

        $product = $webinar->products()->findOrFail($validated['product_id']);

        if ($validated['action'] === 'pin') {
            $this->chatService->pinProduct($webinar, $product);
        } else {
            $this->chatService->unpinProduct($product);
        }

        return response()->json([
            'success' => true,
            'product' => $product->fresh()->toDisplayArray(),
        ]);
    }

    /**
     * Get current viewers count (for periodic refresh).
     */
    public function viewersCount(Webinar $webinar): JsonResponse
    {
        // In real implementation, this would count active WebSocket connections
        // For now, we use fake viewers + any real tracking
        return response()->json([
            'count' => $webinar->getCurrentViewersCount(),
        ]);
    }

    /**
     * Bulk approve pending messages.
     */
    public function bulkApprove(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'message_ids' => 'required|array|min:1',
            'message_ids.*' => 'exists:webinar_chat_messages,id',
        ]);

        $messages = WebinarChatMessage::whereIn('id', $validated['message_ids'])
            ->where('webinar_id', $webinar->id)
            ->where('is_visible', false)
            ->get();

        foreach ($messages as $message) {
            $this->chatService->approveMessage($message);
        }

        return response()->json([
            'success' => true,
            'approved_count' => $messages->count(),
        ]);
    }

    /**
     * Bulk delete messages.
     */
    public function bulkDelete(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'message_ids' => 'required|array|min:1',
            'message_ids.*' => 'exists:webinar_chat_messages,id',
        ]);

        $messages = WebinarChatMessage::whereIn('id', $validated['message_ids'])
            ->where('webinar_id', $webinar->id)
            ->get();

        foreach ($messages as $message) {
            $this->chatService->deleteMessage($message);
        }

        return response()->json([
            'success' => true,
            'deleted_count' => $messages->count(),
        ]);
    }
}
