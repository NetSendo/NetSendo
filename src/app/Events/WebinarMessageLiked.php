<?php

namespace App\Events;

use App\Models\WebinarChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebinarMessageLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WebinarChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('webinar.' . $this->message->webinar_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.liked';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'likes_count' => $this->message->likes_count,
        ];
    }
}
