<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PixelCartAbandoned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public int $deviceId;
    public int $userId;
    public float $cartValue;
    public ?string $productId;
    public ?string $productName;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $deviceId,
        int $userId,
        float $cartValue = 0,
        ?string $productId = null,
        ?string $productName = null
    ) {
        $this->subscriberId = $subscriberId;
        $this->deviceId = $deviceId;
        $this->userId = $userId;
        $this->cartValue = $cartValue;
        $this->productId = $productId;
        $this->productName = $productName;
    }

    /**
     * Get the context for automation processing
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'device_id' => $this->deviceId,
            'user_id' => $this->userId,
            'cart_value' => $this->cartValue,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'trigger_type' => 'pixel_cart_abandoned',
        ];
    }
}
