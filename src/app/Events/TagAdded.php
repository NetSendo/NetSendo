<?php

namespace App\Events;

use App\Models\Subscriber;
use App\Models\Tag;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TagAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public Tag $tag
    ) {}

    /**
     * Get context for automation processing
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriber->id,
            'subscriber_email' => $this->subscriber->email,
            'tag_id' => $this->tag->id,
            'tag_name' => $this->tag->name,
        ];
    }
}
