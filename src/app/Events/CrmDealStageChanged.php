<?php

namespace App\Events;

use App\Models\CrmDeal;
use App\Models\CrmStage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmDealStageChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmDeal $deal,
        public CrmStage $oldStage,
        public CrmStage $newStage,
        public ?int $movedById = null
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
            'old_stage_id' => $this->oldStage->id,
            'old_stage_name' => $this->oldStage->name,
            'new_stage_id' => $this->newStage->id,
            'new_stage_name' => $this->newStage->name,
            'is_won' => $this->newStage->is_won,
            'is_lost' => $this->newStage->is_lost,
            'pipeline_id' => $this->deal->crm_pipeline_id,
            'contact_id' => $this->deal->crm_contact_id,
            'company_id' => $this->deal->crm_company_id,
            'moved_by_id' => $this->movedById,
        ];
    }
}
