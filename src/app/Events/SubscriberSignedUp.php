<?php

namespace App\Events;

use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\SubscriptionForm;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriberSignedUp
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Moment the (re-)signup happened. Queued listeners may run later, so they
     * must not assume now() — and the pivot subscribed_at may intentionally be
     * kept unchanged (resubscription_behavior = keep_date).
     */
    public ?\Illuminate\Support\Carbon $occurredAt = null;

    public function __construct(
        public Subscriber $subscriber,
        public ContactList $list,
        public ?SubscriptionForm $form = null,
        public string $source = 'manual'
    ) {
        $this->occurredAt = now();
    }

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
            'form_id' => $this->form?->id,
            'form_name' => $this->form?->name,
            'source' => $this->source,
            'user_id' => $this->list->user_id,
        ];
    }
}
