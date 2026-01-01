<?php

namespace App\Mail;

use App\Models\WebinarRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebinarReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WebinarRegistration $registration,
        public string $reminderType
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->reminderType) {
            '24h' => 'Przypomnienie: Webinar jutro!',
            '1h' => 'Za godzinę zaczynamy!',
            '15min' => 'Już za 15 minut!',
            default => 'Przypomnienie o webinarze',
        };

        return new Envelope(subject: $subject . ' - ' . $this->registration->webinar->name);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.webinar.reminder',
            with: [
                'webinar' => $this->registration->webinar,
                'registration' => $this->registration,
                'reminderType' => $this->reminderType,
                'watchUrl' => $this->registration->watch_url,
            ],
        );
    }
}
