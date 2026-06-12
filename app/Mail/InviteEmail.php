<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invite $invite, public string $inviterName) {}

    public function envelope(): Envelope
    {
        $site = \App\Support\Settings::get('site.name', 'Convoro');

        return new Envelope(subject: "{$this->inviterName} invited you to {$site}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invite', with: [
            'inviterName' => $this->inviterName,
            'joinUrl' => url('/join/'.$this->invite->code),
            'siteName' => \App\Support\Settings::get('site.name', 'Convoro'),
        ]);
    }
}
