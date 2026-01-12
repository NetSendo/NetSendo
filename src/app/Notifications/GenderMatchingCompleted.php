<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GenderMatchingCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    protected int $matched;
    protected int $unmatched;
    protected int $errors;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $matched, int $unmatched, int $errors)
    {
        $this->matched = $matched;
        $this->unmatched = $unmatched;
        $this->errors = $errors;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'gender_matching_completed',
            'title' => __('names.gender_matching.notification_title'),
            'message' => __('names.gender_matching.notification_message', [
                'matched' => $this->matched,
                'unmatched' => $this->unmatched,
            ]),
            'matched' => $this->matched,
            'unmatched' => $this->unmatched,
            'errors' => $this->errors,
            'link' => route('settings.names.index'),
        ];
    }
}
