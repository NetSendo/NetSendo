<?php

namespace App\Events;

use App\Models\CrmContact;
use App\Models\Subscriber;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmContactReplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmContact $contact,
        public ?Subscriber $subscriber = null,
        public string $channel = 'email', // email, sms, form
        public ?string $messageId = null
    ) {}

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'user_id' => $this->contact->user_id,
            'contact_id' => $this->contact->id,
            'contact_email' => $this->contact->subscriber?->email,
            'contact_name' => $this->contact->full_name,
            'subscriber_id' => $this->subscriber?->id,
            'channel' => $this->channel,
            'message_id' => $this->messageId,
            'company_id' => $this->contact->crm_company_id,
        ];
    }
}
