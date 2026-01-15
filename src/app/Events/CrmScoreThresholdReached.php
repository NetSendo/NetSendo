<?php

namespace App\Events;

use App\Models\CrmContact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmScoreThresholdReached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmContact $contact,
        public int $oldScore,
        public int $newScore,
        public int $threshold,
        public string $direction = 'above' // 'above' or 'below'
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
            'old_score' => $this->oldScore,
            'new_score' => $this->newScore,
            'threshold' => $this->threshold,
            'direction' => $this->direction,
            'status' => $this->contact->status,
            'owner_id' => $this->contact->owner_id,
        ];
    }
}
