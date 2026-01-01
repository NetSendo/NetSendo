<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use App\Models\WebinarSession;
use App\Models\WebinarChatMessage;
use App\Models\WebinarRegistration;
use App\Models\WebinarProduct;
use App\Events\WebinarChatMessageSent;
use Illuminate\Support\Collection;

class WebinarChatService
{
    /**
     * Send a chat message.
     */
    public function sendMessage(
        Webinar $webinar,
        string $message,
        string $senderType,
        string $senderName,
        ?WebinarSession $session = null,
        ?WebinarRegistration $registration = null,
        string $messageType = WebinarChatMessage::TYPE_TEXT,
        ?array $metadata = null
    ): WebinarChatMessage {
        $chatMessage = WebinarChatMessage::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'registration_id' => $registration?->id,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'message' => $message,
            'message_type' => $messageType,
            'metadata' => $metadata,
        ]);

        // Increment registration chat count
        if ($registration) {
            $registration->increment('chat_messages_count');
        }

        // Increment session chat count
        if ($session) {
            $session->incrementChatMessages();
        }

        // Broadcast to all viewers via WebSocket
        $this->broadcastMessage($chatMessage);

        return $chatMessage;
    }

    /**
     * Send a host/moderator message.
     */
    public function sendHostMessage(
        Webinar $webinar,
        string $message,
        ?WebinarSession $session = null,
        bool $isModerator = false
    ): WebinarChatMessage {
        $user = $webinar->user;

        return $this->sendMessage(
            $webinar,
            $message,
            $isModerator ? WebinarChatMessage::SENDER_MODERATOR : WebinarChatMessage::SENDER_HOST,
            $user->name ?? 'Host',
            $session
        );
    }

    /**
     * Send an attendee message.
     */
    public function sendAttendeeMessage(
        Webinar $webinar,
        WebinarRegistration $registration,
        string $message,
        ?WebinarSession $session = null
    ): WebinarChatMessage {
        // Check if chat is enabled and not moderated (or handle moderation)
        $settings = $webinar->settings_with_defaults;

        if (!$settings['chat_enabled']) {
            throw new \Exception('Chat is disabled for this webinar.');
        }

        $chatMessage = $this->sendMessage(
            $webinar,
            $message,
            WebinarChatMessage::SENDER_ATTENDEE,
            $registration->display_name,
            $session,
            $registration
        );

        // If moderated, mark as not visible until approved
        if ($settings['chat_moderated']) {
            $chatMessage->update(['is_visible' => false]);
        }

        return $chatMessage;
    }

    /**
     * Send a system message.
     */
    public function sendSystemMessage(
        Webinar $webinar,
        string $message,
        ?WebinarSession $session = null
    ): WebinarChatMessage {
        return WebinarChatMessage::createSystemMessage($webinar, $message, $session);
    }

    /**
     * Pin a message.
     */
    public function pinMessage(WebinarChatMessage $message): void
    {
        $message->pin();
        $this->broadcastPinChange($message, true);
    }

    /**
     * Unpin a message.
     */
    public function unpinMessage(WebinarChatMessage $message): void
    {
        $message->unpin();
        $this->broadcastPinChange($message, false);
    }

    /**
     * Pin a product (creates product message and pins it).
     */
    public function pinProduct(
        Webinar $webinar,
        WebinarProduct $product,
        ?WebinarSession $session = null
    ): WebinarChatMessage {
        $product->pin();

        $message = WebinarChatMessage::createProductMessage($webinar, $product, $session);
        $this->broadcastMessage($message);

        return $message;
    }

    /**
     * Unpin product.
     */
    public function unpinProduct(WebinarProduct $product): void
    {
        $product->unpin();
        $this->broadcastProductUnpin($product);
    }

    /**
     * Delete (soft) a message.
     */
    public function deleteMessage(WebinarChatMessage $message): void
    {
        $message->softDelete();
        $this->broadcastMessageDelete($message->id, $message->webinar_id);
    }

    /**
     * Approve a moderated message.
     */
    public function approveMessage(WebinarChatMessage $message): void
    {
        $message->update(['is_visible' => true]);
        $this->broadcastMessage($message);
    }

    /**
     * Highlight a message.
     */
    public function highlightMessage(WebinarChatMessage $message): void
    {
        $message->highlight();
        $this->broadcastMessage($message);
    }

    /**
     * Add a like to a message.
     */
    public function likeMessage(WebinarChatMessage $message): void
    {
        $message->addLike();
        $this->broadcastLikeUpdate($message);
    }

    /**
     * Get messages for a webinar/session.
     */
    public function getMessages(
        Webinar $webinar,
        ?WebinarSession $session = null,
        int $limit = 100,
        ?int $afterId = null
    ): Collection {
        $query = $webinar->chatMessages()
            ->visible()
            ->with('registration')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($session) {
            $query->where('webinar_session_id', $session->id);
        }

        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        return $query->get()->reverse()->values();
    }

    /**
     * Get pinned message.
     */
    public function getPinnedMessage(Webinar $webinar): ?WebinarChatMessage
    {
        return $webinar->chatMessages()->pinned()->first();
    }

    /**
     * Get questions for Q&A.
     */
    public function getQuestions(
        Webinar $webinar,
        ?WebinarSession $session = null,
        bool $onlyUnanswered = false
    ): Collection {
        $query = $webinar->chatMessages()
            ->questions()
            ->visible()
            ->with(['registration', 'replies'])
            ->orderBy('created_at', 'desc');

        if ($session) {
            $query->where('webinar_session_id', $session->id);
        }

        if ($onlyUnanswered) {
            $query->unanswered();
        }

        return $query->get();
    }

    /**
     * Answer a question.
     */
    public function answerQuestion(
        WebinarChatMessage $question,
        string $answer,
        Webinar $webinar,
        ?WebinarSession $session = null
    ): WebinarChatMessage {
        $question->markAsAnswered();

        $answerMessage = WebinarChatMessage::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'sender_type' => WebinarChatMessage::SENDER_HOST,
            'sender_name' => $webinar->user->name ?? 'Host',
            'message' => $answer,
            'message_type' => WebinarChatMessage::TYPE_ANSWER,
            'parent_id' => $question->id,
        ]);

        $this->broadcastMessage($answerMessage);

        return $answerMessage;
    }

    /**
     * Get pending moderated messages.
     */
    public function getPendingMessages(Webinar $webinar, ?WebinarSession $session = null): Collection
    {
        $query = $webinar->chatMessages()
            ->where('is_visible', false)
            ->where('is_deleted', false)
            ->where('sender_type', WebinarChatMessage::SENDER_ATTENDEE)
            ->orderBy('created_at', 'asc');

        if ($session) {
            $query->where('webinar_session_id', $session->id);
        }

        return $query->get();
    }

    /**
     * Broadcast message via WebSocket.
     */
    protected function broadcastMessage(WebinarChatMessage $message): void
    {
        // Broadcast to webinar channel
        // Using Laravel Broadcasting (Reverb/Pusher)
        broadcast(new WebinarChatMessageSent($message))->toOthers();
    }

    /**
     * Broadcast pin change.
     */
    protected function broadcastPinChange(WebinarChatMessage $message, bool $isPinned): void
    {
        broadcast(new \App\Events\WebinarMessagePinned($message, $isPinned))->toOthers();
    }

    /**
     * Broadcast message deletion.
     */
    protected function broadcastMessageDelete(int $messageId, int $webinarId): void
    {
        broadcast(new \App\Events\WebinarMessageDeleted($messageId, $webinarId))->toOthers();
    }

    /**
     * Broadcast product unpin.
     */
    protected function broadcastProductUnpin(WebinarProduct $product): void
    {
        broadcast(new \App\Events\WebinarProductUnpinned($product))->toOthers();
    }

    /**
     * Broadcast like update.
     */
    protected function broadcastLikeUpdate(WebinarChatMessage $message): void
    {
        broadcast(new \App\Events\WebinarMessageLiked($message))->toOthers();
    }

    /**
     * Export chat history.
     */
    public function exportChat(Webinar $webinar, ?WebinarSession $session = null): array
    {
        $query = $webinar->chatMessages()
            ->visible()
            ->with('registration')
            ->orderBy('created_at', 'asc');

        if ($session) {
            $query->where('webinar_session_id', $session->id);
        }

        return $query->get()->map(fn($m) => [
            'time' => $m->created_at->format('Y-m-d H:i:s'),
            'sender' => $m->sender_name,
            'type' => $m->sender_type,
            'message' => $m->message,
        ])->toArray();
    }
}
