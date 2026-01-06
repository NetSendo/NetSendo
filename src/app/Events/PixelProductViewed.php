<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PixelProductViewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public int $deviceId;
    public string $pageUrl;
    public int $userId;
    public ?string $productId;
    public ?string $productName;
    public ?float $productPrice;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $deviceId,
        string $pageUrl,
        int $userId,
        ?string $productId = null,
        ?string $productName = null,
        ?float $productPrice = null
    ) {
        $this->subscriberId = $subscriberId;
        $this->deviceId = $deviceId;
        $this->pageUrl = $pageUrl;
        $this->userId = $userId;
        $this->productId = $productId;
        $this->productName = $productName;
        $this->productPrice = $productPrice;
    }

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'device_id' => $this->deviceId,
            'page_url' => $this->pageUrl,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_price' => $this->productPrice,
            'user_id' => $this->userId,
            'trigger_event' => 'pixel_product_viewed',
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
