<?php

namespace App\Events;

use App\Models\WebinarChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebinarMessagePinned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WebinarChatMessage $message,
        public bool $isPinned
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('webinar.' . $this->message->webinar_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.pinned';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'is_pinned' => $this->isPinned,
            'message' => $this->isPinned ? $this->message->toBroadcast() : null,
        ];
    }
}
