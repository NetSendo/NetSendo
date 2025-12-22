<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReadTimeThresholdReached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;
    public int $subscriberId;
    public int $readTimeSeconds;
    public int $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $messageId,
        int $subscriberId,
        int $readTimeSeconds,
        int $userId
    ) {
        $this->messageId = $messageId;
        $this->subscriberId = $subscriberId;
        $this->readTimeSeconds = $readTimeSeconds;
        $this->userId = $userId;
    }

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'message_id' => $this->messageId,
            'subscriber_id' => $this->subscriberId,
            'read_time_seconds' => $this->readTimeSeconds,
            'user_id' => $this->userId,
        ];
    }

    /**
     * Get subscriber from event.
     */
    public function getSubscriber(): ?Subscriber
    {
        return Subscriber::find($this->subscriberId);
    }
}
