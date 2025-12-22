<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriberBirthday
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public int $userId;
    public ?int $age;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $userId,
        ?int $age = null
    ) {
        $this->subscriberId = $subscriberId;
        $this->userId = $userId;
        $this->age = $age;
    }

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'user_id' => $this->userId,
            'age' => $this->age,
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
