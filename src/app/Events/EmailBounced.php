<?php

namespace App\Events;

use App\Models\Subscriber;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailBounced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const TYPE_SOFT = 'soft';
    public const TYPE_HARD = 'hard';

    public function __construct(
        public int $messageId,
        public int $subscriberId,
        public string $bounceType = self::TYPE_HARD,
        public ?string $bounceReason = null
    ) {}

    /**
     * Get the subscriber instance
     */
    public function getSubscriber(): ?Subscriber
    {
        return Subscriber::find($this->subscriberId);
    }

    /**
     * Get the message instance
     */
    public function getMessage(): ?Message
    {
        return Message::find($this->messageId);
    }

    /**
     * Get context for automation processing
     */
    public function getContext(): array
    {
        $subscriber = $this->getSubscriber();
        $message = $this->getMessage();
        // Messages go to several lists (contact_list_message); a rule's list
        // filter matches any of them.
        $listIds = $message ? $message->contactLists()->pluck('contact_lists.id')->map(fn ($id) => (int) $id)->all() : [];

        return [
            'subscriber_id' => $this->subscriberId,
            'subscriber_email' => $subscriber?->email,
            'message_id' => $this->messageId,
            'message_subject' => $message?->subject,
            'list_id' => $listIds[0] ?? null,
            'list_ids' => $listIds,
            'bounce_type' => $this->bounceType,
            'bounce_reason' => $this->bounceReason,
        ];
    }
}
