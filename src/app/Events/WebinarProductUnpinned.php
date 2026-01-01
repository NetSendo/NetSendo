<?php

namespace App\Events;

use App\Models\WebinarProduct;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebinarProductUnpinned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WebinarProduct $product) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('webinar.' . $this->product->webinar_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'product.unpinned';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
        ];
    }
}
