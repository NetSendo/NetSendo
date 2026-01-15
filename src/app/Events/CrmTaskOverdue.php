<?php

namespace App\Events;

use App\Models\CrmTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmTaskOverdue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmTask $task
    ) {}

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'user_id' => $this->task->user_id,
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_type' => $this->task->type,
            'task_priority' => $this->task->priority,
            'due_date' => $this->task->due_date?->toDateString(),
            'owner_id' => $this->task->owner_id,
            'contact_id' => $this->task->crm_contact_id,
            'deal_id' => $this->task->crm_deal_id,
        ];
    }
}
