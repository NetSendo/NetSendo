<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PixelPageVisited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public int $deviceId;
    public string $pageUrl;
    public int $userId;
    public ?string $pageTitle;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        int $deviceId,
        string $pageUrl,
        int $userId,
        ?string $pageTitle = null
    ) {
        $this->subscriberId = $subscriberId;
        $this->deviceId = $deviceId;
        $this->pageUrl = $pageUrl;
        $this->userId = $userId;
        $this->pageTitle = $pageTitle;
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
            'page_title' => $this->pageTitle,
            'user_id' => $this->userId,
            'trigger_event' => 'pixel_page_visited',
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
