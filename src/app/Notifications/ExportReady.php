<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $filePath,
        public string $listName
    ) {}

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
        $downloadUrl = URL::signedRoute('api.v1.exports.download', [
            'path' => $this->filePath,
        ], now()->addHours(24));

        return (new MailMessage)
            ->subject('Twoja eksportowana lista jest gotowa')
            ->line("Eksport listy kontaktów \"{$this->listName}\" został zakończony.")
            ->action('Pobierz plik CSV', $downloadUrl)
            ->line('Link do pobrania jest ważny przez 24 godziny.')
            ->line('Dziękujemy za korzystanie z naszej aplikacji!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'file_path' => $this->filePath,
            'list_name' => $this->listName,
            'message' => "Eksport listy \"{$this->listName}\" jest gotowy do pobrania.",
        ];
    }
}
