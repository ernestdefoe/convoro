<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Internal heads-up to the team whenever someone requests a demo. */
class DemoRequestedAdminMail extends Mailable
{
    /**
     * @param  string  $requester  The email that requested the demo.
     * @param  string  $outcome    'provisioned' or 'waitlisted'.
     * @param  string|null  $url    Demo URL when provisioned.
     * @param  int  $activeCount    Demos currently active.
     * @param  int  $waitlistCount  People currently waiting.
     */
    public function __construct(
        public string $requester,
        public string $outcome,
        public ?string $url,
        public int $activeCount,
        public int $waitlistCount,
    ) {}

    public function envelope(): Envelope
    {
        $tag = $this->outcome === 'provisioned' ? 'new demo' : 'waitlisted';

        return new Envelope(subject: "Convoro demo request ({$tag}): {$this->requester}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo-requested-admin', with: [
            'requester' => $this->requester,
            'outcome' => $this->outcome,
            'url' => $this->url,
            'activeCount' => $this->activeCount,
            'waitlistCount' => $this->waitlistCount,
        ]);
    }
}
