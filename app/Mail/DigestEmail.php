<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        $site = \App\Support\Settings::get('site.name') ?: config('app.name', 'Convoro');

        return new Envelope(subject: __('Your :site digest — :period', ['site' => $site, 'period' => $this->periodLabel]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest', with: [
            // Permanent signed URL — one-click unsubscribe straight from the email,
            // no login required.
            'unsubscribeUrl' => \Illuminate\Support\Facades\URL::signedRoute('digest.unsubscribe', ['user' => $this->user->id]),
        ]);
    }
}
