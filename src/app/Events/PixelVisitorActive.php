<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a visitor is active on a tracked page
 */
class PixelVisitorActive implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $visitorToken,
        public string $pageUrl,
        public ?string $pageTitle,
        public string $deviceType,
        public ?string $browser,
        public int $timestamp
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pixel.' . $this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'visitor.active';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'visitor_token' => $this->visitorToken,
            'page_url' => $this->pageUrl,
            'page_title' => $this->pageTitle,
            'device_type' => $this->deviceType,
            'browser' => $this->browser,
            'timestamp' => $this->timestamp,
        ];
    }
}
