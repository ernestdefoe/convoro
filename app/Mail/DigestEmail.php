<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class DigestEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{title:string,url:string,excerpt:string,author:string,replyCount:int}>  $topics
     */
    public function __construct(
        public User $user,
        public string $periodLabel,
        public array $topics,
        public int $unread,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Convoro digest — '.$this->periodLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest', with: [
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    /**
     * RFC 2369 + RFC 8058 one-click unsubscribe. Gmail/Yahoo bulk-sender rules
     * expect these headers; the signed URL doubles as the in-body link.
     */
    public function headers(): Headers
    {
        $url = $this->unsubscribeUrl();

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /** Signed, tamper-proof unsubscribe URL for this recipient. */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('digest.unsubscribe', ['user' => $this->user->id]);
    }
}
