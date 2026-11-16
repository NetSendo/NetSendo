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
    /** @var int[] lists the subscriber is an active member of */
    public array $listIds;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $userId,
        int $yearsSubscribed,
        array $listIds = []
    ) {
        $this->subscriberId = $subscriberId;
        $this->userId = $userId;
        $this->yearsSubscribed = $yearsSubscribed;
        $this->listIds = $listIds;
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
            'list_ids' => $this->listIds,
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
