<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * NetSendo Pixel: an identified visitor views a page after being away for at
 * least a day (no pixel event of that subscriber in between).
 */
class PixelReturnVisit
{
    use Dispatchable;

    public function __construct(
        public int $subscriberId,
        public int $userId,
        public string $pageUrl,
        public int $daysAway,
    ) {}

    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'user_id' => $this->userId,
            'page_url' => $this->pageUrl,
            'days_away' => $this->daysAway,
        ];
    }

    public function getSubscriber(): ?Subscriber
    {
        return Subscriber::find($this->subscriberId);
    }
}
