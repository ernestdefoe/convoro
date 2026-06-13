<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A single per-event notification email (reply / mention / DM / wall).
 * Queued, so triggering it never blocks the web request.
 */
class NotificationEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $heading,
        public ?string $excerpt,
        public string $url,
        public string $site,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->heading.' · '.$this->site,
            replyTo: $this->replyToAddress ? [new \Illuminate\Mail\Mailables\Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: [
            'heading' => $this->heading,
            'excerpt' => $this->excerpt,
            'url' => $this->url,
            'site' => $this->site,
            'prefsUrl' => url('/profile'),
        ]);
    }
}
