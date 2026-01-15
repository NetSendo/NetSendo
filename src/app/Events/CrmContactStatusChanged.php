<?php

namespace App\Events;

use App\Models\CrmContact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmContactStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmContact $contact,
        public string $oldStatus,
        public string $newStatus,
        public ?int $changedById = null
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
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'score' => $this->contact->score,
            'owner_id' => $this->contact->owner_id,
            'changed_by_id' => $this->changedById,
        ];
    }
}
