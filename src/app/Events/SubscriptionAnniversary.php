<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionAnniversary
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public int $userId;
    public int $yearsSubscribed;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $userId,
        int $yearsSubscribed
    ) {
        $this->subscriberId = $subscriberId;
        $this->userId = $userId;
        $this->yearsSubscribed = $yearsSubscribed;
    }

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'user_id' => $this->userId,
            'years_subscribed' => $this->yearsSubscribed,
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
