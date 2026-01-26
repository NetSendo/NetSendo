<?php

namespace App\Notifications;

use App\Models\CrmDeal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DealStageChangedNotification extends Notification
{
    use Queueable;

    /**
     * The deal that changed stage.
     */
    public CrmDeal $deal;

    /**
     * The change type (e.g., 'won', 'lost', 'stage_changed').
     */
    public string $changeType;

    /**
     * Create a new notification instance.
     */
    public function __construct(CrmDeal $deal, string $changeType = 'stage_changed')
    {
        $this->deal = $deal;
        $this->changeType = $changeType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/crm/deals/{$this->deal->id}");

        $subject = match($this->changeType) {
            'won' => __('crm.notifications.deal_won.subject', ['name' => $this->deal->name]),
            'lost' => __('crm.notifications.deal_lost.subject', ['name' => $this->deal->name]),
            default => __('crm.notifications.deal_stage_changed.subject', ['name' => $this->deal->name]),
        };

        $intro = match($this->changeType) {
            'won' => __('crm.notifications.deal_won.intro', ['name' => $this->deal->name, 'value' => number_format($this->deal->value, 2)]),
            'lost' => __('crm.notifications.deal_lost.intro', ['name' => $this->deal->name]),
            default => __('crm.notifications.deal_stage_changed.intro', ['name' => $this->deal->name]),
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('crm.notifications.deal_stage_changed.greeting'))
            ->line($intro)
            ->action(__('crm.notifications.deal_stage_changed.action'), $url);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deal_stage_changed',
            'change_type' => $this->changeType,
            'deal_id' => $this->deal->id,
            'deal_name' => $this->deal->name,
            'deal_value' => $this->deal->value,
            'stage_id' => $this->deal->crm_stage_id,
            'pipeline_id' => $this->deal->crm_pipeline_id,
        ];
    }
}
