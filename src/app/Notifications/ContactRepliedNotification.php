<?php

namespace App\Notifications;

use App\Models\CrmContact;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactRepliedNotification extends Notification
{
    use Queueable;

    /**
     * The contact that replied.
     */
    public CrmContact $contact;

    /**
     * The channel through which contact replied.
     */
    public string $channel;

    /**
     * Create a new notification instance.
     */
    public function __construct(CrmContact $contact, string $channel = 'email')
    {
        $this->contact = $contact;
        $this->channel = $channel;
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
        $url = url("/crm/contacts/{$this->contact->id}");
        $contactName = $this->contact->full_name ?? $this->contact->email;

        return (new MailMessage)
            ->subject(__('crm.notifications.contact_replied.subject', ['name' => $contactName]))
            ->greeting(__('crm.notifications.contact_replied.greeting'))
            ->line(__('crm.notifications.contact_replied.intro', ['name' => $contactName, 'channel' => $this->channel]))
            ->action(__('crm.notifications.contact_replied.action'), $url)
            ->line(__('crm.notifications.contact_replied.outro'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contact_replied',
            'contact_id' => $this->contact->id,
            'contact_name' => $this->contact->full_name,
            'contact_email' => $this->contact->email,
            'channel' => $this->channel,
        ];
    }
}
