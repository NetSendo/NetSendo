<?php

namespace App\Mail;

use App\Models\WebinarRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebinarReplayAvailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WebinarRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nagranie dostępne: ' . $this->registration->webinar->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.webinar.replay-available',
            with: [
                'webinar' => $this->registration->webinar,
                'registration' => $this->registration,
                'replayUrl' => $this->registration->replay_url,
            ],
        );
    }
}
