<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to a requester once their on-demand demo is provisioned. */
class DemoReadyMail extends Mailable
{
    public function __construct(
        public string $url,
        public string $login,
        public string $password,
        public string $expires,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your Convoro demo is ready'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo-ready', with: [
            'url' => $this->url,
            'login' => $this->login,
            'password' => $this->password,
            'expires' => $this->expires,
        ]);
    }
}
