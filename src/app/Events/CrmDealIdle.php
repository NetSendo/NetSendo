<?php

namespace App\Events;

use App\Models\CrmDeal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmDealIdle
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmDeal $deal,
        public int $idleDays
    ) {}

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'user_id' => $this->deal->user_id,
            'deal_id' => $this->deal->id,
            'deal_name' => $this->deal->name,
            'deal_value' => $this->deal->value,
            'pipeline_id' => $this->deal->crm_pipeline_id,
            'stage_id' => $this->deal->crm_stage_id,
            'contact_id' => $this->deal->crm_contact_id,
            'owner_id' => $this->deal->owner_id,
            'idle_days' => $this->idleDays,
            'last_activity_at' => $this->deal->updated_at?->toIso8601String(),
        ];
    }
}
