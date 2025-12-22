<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PageVisited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriberId;
    public string $pageUrl;
    public ?string $pageTitle;
    public int $userId;
    public ?string $visitorToken;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $subscriberId,
        string $pageUrl,
        int $userId,
        ?string $pageTitle = null,
        ?string $visitorToken = null
    ) {
        $this->subscriberId = $subscriberId;
        $this->pageUrl = $pageUrl;
        $this->userId = $userId;
        $this->pageTitle = $pageTitle;
        $this->visitorToken = $visitorToken;
    }

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'page_url' => $this->pageUrl,
            'page_title' => $this->pageTitle,
            'user_id' => $this->userId,
            'visitor_token' => $this->visitorToken,
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
