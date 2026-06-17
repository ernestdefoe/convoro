<?php

namespace App\Console\Commands;

use App\Mail\DigestEmail;
use App\Models\Topic;
use App\Models\User;
use App\Support\Present;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDigests extends Command
{
    protected $signature = 'convoro:digest {frequency=daily : daily|weekly} {--dry : preview recipients without sending}';

    protected $description = 'Queue digest emails for users subscribed at the given frequency';

    public function handle(): int
    {
        $frequency = (string) $this->argument('frequency');
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $this->error('frequency must be "daily" or "weekly".');

            return self::FAILURE;
        }

        if (! Settings::get('digests.enabled', true)) {
            $this->info('Digest emails are disabled in admin settings.');

            return self::SUCCESS;
        }

        $fallback = $frequency === 'daily' ? now()->subDay() : now()->subWeek();
        $label = $frequency === 'daily' ? 'last 24 hours' : 'past week';

        $users = User::where('digest_frequency', $frequency)->get();
        $withContent = 0;

        foreach ($users as $user) {
            if (! $user->isMailable()) {
                continue; // skip synthetic accounts (assistant/federated) — no MX
            }
            $since = $user->last_digest_at ?? $fallback;

            $topics = Topic::with(['user', 'firstPost'])
                ->where('last_post_at', '>=', $since)
                ->orderByDesc('last_post_at')
                ->limit(8)
                ->get()
                ->map(fn (Topic $t) => [
                    'title' => $t->title,
                    'url' => url('/t/'.$t->slug),
                    'excerpt' => Present::excerpt($t->firstPost?->body_html, 140),
                    'author' => $t->user?->name ?? 'Someone',
                    'replyCount' => (int) $t->reply_count,
                ])->all();

            $unread = $user->unreadNotifications()->count();

            if (empty($topics) && $unread === 0) {
                continue; // nothing worth emailing about
            }

            $withContent++;

            if ($this->option('dry')) {
                $this->line(sprintf('  would email %s — %d topic(s), %d unread', $user->email, count($topics), $unread));

                continue;
            }

            Mail::to($user)->locale($user->locale ?: (\App\Support\Settings::get('site.locale') ?: config('app.locale', 'en')))
                ->queue(new DigestEmail($user, $label, $topics, $unread));
            $user->forceFill(['last_digest_at' => now()])->save();
        }

        $this->info(sprintf(
            '%sProcessed %d %s subscriber(s); %d had content.',
            $this->option('dry') ? '(dry) ' : '',
            $users->count(),
            $frequency,
            $withContent,
        ));

        return self::SUCCESS;
    }
}
