<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A member's email invitation to a friend. Queued. */
class InviteEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $inviterName,
        public string $url,
        public ?string $personalMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $site = Settings::get('site.name', 'Convoro');

        return new Envelope(subject: __(':name invited you to join :site', ['name' => $this->inviterName, 'site' => $site]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invite', with: [
            'inviterName' => $this->inviterName,
            'url' => $this->url,
            'personalMessage' => $this->personalMessage,
            'site' => Settings::get('site.name', 'Convoro'),
        ]);
    }
}
