<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use App\Models\WebinarSession;
use App\Services\Webinar\WebinarChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebinarChatController extends Controller
{
    public function __construct(protected WebinarChatService $chatService) {}

    public function index(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        $session = $request->session_id
            ? WebinarSession::find($request->session_id)
            : null;

        $messages = $this->chatService->getMessages(
            $webinar,
            $session,
            $request->get('limit', 100),
            $request->get('after_id')
        );

        return response()->json([
            'messages' => $messages->map(fn($m) => $m->toBroadcast()),
            'pinned' => $this->chatService->getPinnedMessage($webinar)?->toBroadcast(),
        ]);
    }

    public function send(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|exists:webinar_sessions,id',
            'is_moderator' => 'boolean',
        ]);

        $session = $validated['session_id']
            ? WebinarSession::find($validated['session_id'])
            : null;

        $message = $this->chatService->sendHostMessage(
            $webinar,
            $validated['message'],
            $session,
            $validated['is_moderator'] ?? false
        );

        return response()->json([
            'message' => $message->toBroadcast(),
        ]);
    }

    public function pin(Webinar $webinar, WebinarChatMessage $message): JsonResponse
    {
        $this->authorize('update', $webinar);

        $this->chatService->pinMessage($message);

        return response()->json(['success' => true]);
    }

    public function unpin(Webinar $webinar, WebinarChatMessage $message): JsonResponse
    {
        $this->authorize('update', $webinar);

        $this->chatService->unpinMessage($message);

        return response()->json(['success' => true]);
    }

    public function delete(Webinar $webinar, WebinarChatMessage $message): JsonResponse
    {
        $this->authorize('update', $webinar);

        $this->chatService->deleteMessage($message);

        return response()->json(['success' => true]);
    }

    public function highlight(Webinar $webinar, WebinarChatMessage $message): JsonResponse
    {
        $this->authorize('update', $webinar);

        $this->chatService->highlightMessage($message);

        return response()->json(['success' => true]);
    }

    public function questions(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        $questions = $this->chatService->getQuestions(
            $webinar,
            null,
            $request->boolean('unanswered_only')
        );

        return response()->json([
            'questions' => $questions->map(fn($q) => $q->toBroadcast()),
        ]);
    }

    public function answer(Request $request, Webinar $webinar, WebinarChatMessage $question): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'answer' => 'required|string|max:1000',
        ]);

        $answer = $this->chatService->answerQuestion(
            $question,
            $validated['answer'],
            $webinar
        );

        return response()->json([
            'answer' => $answer->toBroadcast(),
        ]);
    }

    public function pending(Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $pending = $this->chatService->getPendingMessages($webinar);

        return response()->json([
            'messages' => $pending->map(fn($m) => $m->toBroadcast()),
        ]);
    }

    public function approve(Webinar $webinar, WebinarChatMessage $message): JsonResponse
    {
        $this->authorize('update', $webinar);

        $this->chatService->approveMessage($message);

        return response()->json(['success' => true]);
    }
}
