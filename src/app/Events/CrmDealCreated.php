<?php

namespace App\Events;

use App\Models\CrmDeal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmDealCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmDeal $deal,
        public ?int $createdById = null
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
            'company_id' => $this->deal->crm_company_id,
            'owner_id' => $this->deal->owner_id,
            'created_by_id' => $this->createdById,
        ];
    }
}
