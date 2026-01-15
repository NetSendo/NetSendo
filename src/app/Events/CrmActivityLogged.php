<?php

namespace App\Events;

use App\Models\CrmActivity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmActivityLogged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CrmActivity $activity
    ) {}

    /**
     * Get context for automation processing.
     */
    public function getContext(): array
    {
        return [
            'user_id' => $this->activity->user_id,
            'activity_id' => $this->activity->id,
            'activity_type' => $this->activity->type,
            'content' => $this->activity->content,
            'subject_type' => $this->activity->subject_type,
            'subject_id' => $this->activity->subject_id,
            'created_by_id' => $this->activity->created_by_id,
        ];
    }
}
