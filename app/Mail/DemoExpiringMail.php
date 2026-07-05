<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Reminder sent ~24h before an on-demand demo expires. */
class DemoExpiringMail extends Mailable
{
    public function __construct(
        public string $url,
        public string $expires,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your Convoro demo expires tomorrow'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo-expiring', with: [
            'url' => $this->url,
            'expires' => $this->expires,
        ]);
    }
}
