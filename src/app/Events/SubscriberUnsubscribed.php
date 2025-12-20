<?php

namespace App\Events;

use App\Models\Subscriber;
use App\Models\ContactList;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriberUnsubscribed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public ContactList $list,
        public string $reason = 'manual'
    ) {}

    /**
     * Get context for automation processing
     */
    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriber->id,
            'subscriber_email' => $this->subscriber->email,
            'list_id' => $this->list->id,
            'list_name' => $this->list->name,
            'reason' => $this->reason,
        ];
    }
}
