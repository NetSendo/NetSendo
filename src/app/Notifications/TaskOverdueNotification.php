<?php

namespace App\Notifications;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskOverdueNotification extends Notification
{
    use Queueable;

    /**
     * The overdue task.
     */
    public CrmTask $task;

    /**
     * Create a new notification instance.
     */
    public function __construct(CrmTask $task)
    {
        $this->task = $task;
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
        $url = url("/crm/tasks/{$this->task->id}");

        return (new MailMessage)
            ->subject(__('crm.notifications.task_overdue.subject', ['title' => $this->task->title]))
            ->greeting(__('crm.notifications.task_overdue.greeting'))
            ->line(__('crm.notifications.task_overdue.intro', ['title' => $this->task->title]))
            ->line(__('crm.notifications.task_overdue.due_date', ['date' => $this->task->due_date?->format('d.m.Y H:i')]))
            ->action(__('crm.notifications.task_overdue.action'), $url)
            ->line(__('crm.notifications.task_overdue.outro'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_overdue',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_type' => $this->task->type,
            'due_date' => $this->task->due_date?->toISOString(),
            'contact_id' => $this->task->crm_contact_id,
            'deal_id' => $this->task->crm_deal_id,
        ];
    }
}
