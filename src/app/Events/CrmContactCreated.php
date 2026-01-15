<?php

namespace App\Events;

use App\Models\CrmContact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmContactCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmContact $contact,
        public ?int $createdById = null
    ) {}

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'user_id' => $this->contact->user_id,
            'contact_id' => $this->contact->id,
            'subscriber_id' => $this->contact->subscriber_id,
            'email' => $this->contact->email,
            'first_name' => $this->contact->first_name,
            'last_name' => $this->contact->last_name,
            'status' => $this->contact->status,
            'source' => $this->contact->source,
            'score' => $this->contact->score,
            'company_id' => $this->contact->crm_company_id,
            'owner_id' => $this->contact->owner_id,
            'created_by_id' => $this->createdById,
        ];
    }
}
