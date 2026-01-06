<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PixelCheckoutStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public int $deviceId;
    public int $userId;
    public ?float $cartValue;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $deviceId,
        int $userId,
        ?float $cartValue = null
    ) {
        $this->subscriberId = $subscriberId;
        $this->deviceId = $deviceId;
        $this->userId = $userId;
        $this->cartValue = $cartValue;
    }

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'device_id' => $this->deviceId,
            'cart_value' => $this->cartValue,
            'user_id' => $this->userId,
            'trigger_event' => 'pixel_checkout_started',
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
