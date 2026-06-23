<?php

namespace App\Console\Commands;

use App\Mail\NotificationEmail;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Batched email for direct messages. DMs push instantly but their emails are
 * collected and sent periodically (scheduled every few hours) so a burst of
 * messages can't flood someone's inbox with one email per message.
 *
 * Only DMs that are STILL UNREAD and arrived since the member's last digest are
 * included — if they've already read it in-app, no email goes out at all.
 */
class SendMessageDigests extends Command
{
    protected $signature = 'convoro:digest-messages {--dry : preview recipients without sending}';

    protected $description = 'Email a batched summary of unread direct messages';

    public function handle(): int
    {
        if (! Settings::get('mail.configured')) {
            $this->info('Mail is not configured — nothing to send.');

            return self::SUCCESS;
        }

        // Members who currently have at least one unread direct-message notification.
        $userIds = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->where('data->type', 'message')
            ->distinct()
            ->pluck('notifiable_id');

        $sent = 0;

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            if (! $user->isMailable() || ! $user->wantsNotification('message', 'email')) {
                continue;
            }

            $since = $user->last_message_digest_at ?? now()->subDay();
            $messages = $user->unreadNotifications()
                ->where('created_at', '>', $since)
                ->get()
                ->filter(fn ($n) => ($n->data['type'] ?? null) === 'message');

            if ($messages->isEmpty()) {
                continue;
            }

            $count = $messages->count();
            $senders = $messages->map(fn ($n) => $n->data['actor']['name'] ?? __('Someone'))
                ->unique()->values();
            $locale = $user->locale ?: (Settings::get('site.locale') ?: config('app.locale', 'en'));

            if ($this->option('dry')) {
                $this->line(sprintf('  would email %s — %d unread message(s) from %d sender(s)', $user->email, $count, $senders->count()));

                continue;
            }

            $heading = $count === 1
                ? __(':actor sent you a message', ['actor' => $senders->first()], $locale)
                : __('You have :count new messages', ['count' => $count], $locale);
            $excerpt = $senders->count() > 1
                ? __('From :names', ['names' => $senders->take(3)->join(', ', ' & ')], $locale)
                : null;

            Mail::to($user)->locale($locale)->queue(new NotificationEmail(
                heading: $heading,
                excerpt: $excerpt,
                url: url('/messages'),
                site: (string) (Settings::get('site.name') ?: config('app.name', 'Convoro')),
                replyToAddress: null,
            ));
            $user->forceFill(['last_message_digest_at' => now()])->save();
            $sent++;
        }

        $this->info(sprintf('%s%d member(s) emailed a message digest.', $this->option('dry') ? '(dry) ' : '', $sent));

        return self::SUCCESS;
    }
}
