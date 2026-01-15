<?php

namespace App\Events;

use App\Models\CrmTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmTaskCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmTask $task,
        public ?int $completedById = null
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
            'contact_id' => $this->task->crm_contact_id,
            'deal_id' => $this->task->crm_deal_id,
            'owner_id' => $this->task->owner_id,
            'completed_by_id' => $this->completedById,
            'completed_at' => $this->task->completed_at?->toIso8601String(),
        ];
    }
}
