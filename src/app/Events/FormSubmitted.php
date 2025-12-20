<?php

namespace App\Events;

use App\Models\Subscriber;
use App\Models\SubscriptionForm;
use App\Models\FormSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FormSubmission $submission,
        public Subscriber $subscriber,
        public SubscriptionForm $form
    ) {}

    /**
     * Get context for automation processing
     */
    public function getContext(): array
    {
        return [
            'submission_id' => $this->submission->id,
            'subscriber_id' => $this->subscriber->id,
            'subscriber_email' => $this->subscriber->email,
            'form_id' => $this->form->id,
            'form_name' => $this->form->name,
            'list_id' => $this->form->contact_list_id,
            'submitted_data' => $this->submission->data,
        ];
    }
}
