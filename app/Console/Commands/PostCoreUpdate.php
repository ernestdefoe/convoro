<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Topic;
use App\Support\AiBot;
use App\Support\Present;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Posts Convoro core-update announcements (the changelog) into a designated
 * forum thread, authored by the assistant account. Server-side and idempotent
 * — there's no API key floating around; it runs on deploy (or by hand) and
 * skips versions it has already announced.
 *
 *   php artisan convoro:post-core-update                 # newest unposted entry
 *   php artisan convoro:post-core-update --all           # every unposted entry, oldest→newest
 *   php artisan convoro:post-core-update --topic=core-update-thread-pbbtmw
 *   php artisan convoro:post-core-update --force         # re-post the newest entry
 */
class PostCoreUpdate extends Command
{
    protected $signature = 'convoro:post-core-update {--topic= : Target topic slug or id (defaults to the saved core_updates.topic)} {--all : Post every entry newer than the last announced} {--force : Re-post the newest entry even if already announced}';

    protected $description = 'Announce Convoro core updates (changelog) in the designated forum thread.';

    public function handle(): int
    {
        $topic = $this->resolveTopic();
        if (! $topic) {
            $this->error('No target topic. Pass --topic=<slug|id> or set the core_updates.topic setting.');

            return self::FAILURE;
        }

        $changelog = array_values((array) config('trust.changelog', []));
        if ($changelog === []) {
            $this->warn('No changelog entries configured.');

            return self::SUCCESS;
        }

        $lastPosted = (string) Settings::get('core_updates.last_version', '');
        $force = (bool) $this->option('force');
        $all = (bool) $this->option('all');

        // Oldest → newest so the thread reads chronologically. Changelog is
        // stored newest-first, so reverse it.
        $ordered = array_reverse($changelog);

        $toPost = [];
        if ($force) {
            $toPost = [$changelog[0]];
        } elseif ($all) {
            $seenLast = $lastPosted === '';
            foreach ($ordered as $entry) {
                if ($seenLast) {
                    $toPost[] = $entry;
                } elseif ((string) ($entry['tag'] ?? '') === $lastPosted) {
                    $seenLast = true;
                }
            }
            // If we never matched the saved version, the changelog rolled past
            // it — post only the newest to avoid spamming the whole history.
            if (! $seenLast) {
                $toPost = [$changelog[0]];
            }
        } else {
            $newest = $changelog[0];
            if ((string) ($newest['tag'] ?? '') !== $lastPosted) {
                $toPost = [$newest];
            }
        }

        if ($toPost === []) {
            $this->info('Nothing new to announce (last posted: '.($lastPosted ?: 'none').').');

            return self::SUCCESS;
        }

        $botId = AiBot::userId();
        $posted = 0;
        foreach ($toPost as $entry) {
            $this->postEntry($topic, $botId, $entry);
            Settings::set('core_updates.last_version', (string) ($entry['tag'] ?? ''));
            $posted++;
            $this->line('Announced '.($entry['tag'] ?? '?').' — '.($entry['title'] ?? ''));
        }

        $this->info("Posted {$posted} update(s) to “{$topic->title}”.");

        return self::SUCCESS;
    }

    private function resolveTopic(): ?Topic
    {
        $ref = (string) ($this->option('topic') ?: Settings::get('core_updates.topic', ''));
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            return Topic::find((int) $ref);
        }

        return Topic::where('slug', $ref)->first();
    }

    private function postEntry(Topic $topic, int $botId, array $entry): void
    {
        $post = $topic->posts()->create([
            'user_id' => $botId,
            'body_html' => self::renderEntry($entry),
            'is_first' => false,
            'is_ai' => false,
        ]);

        $topic->update([
            'last_post_at' => now(),
            'reply_count' => $topic->posts()->where('is_first', false)->count(),
        ]);

        try {
            $post->load(['user', 'reactions']);
            broadcast(new \App\Events\PostCreated(Present::post($post, null), $topic->id));
        } catch (\Throwable) {
            // Realtime is best-effort — the post is already saved.
        }
    }

    /** Build the announcement HTML for one changelog entry. */
    public static function renderEntry(array $entry): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $tag = (string) ($entry['tag'] ?? '');
        $date = (string) ($entry['date'] ?? '');
        $title = (string) ($entry['title'] ?? '');

        $labels = ['new' => '🆕 New', 'improved' => '✨ Improved', 'fixed' => '🐛 Fixed'];

        $html = '<p><strong>Convoro '.$e($tag).'</strong>'.($date !== '' ? ' <small>· '.$e($date).'</small>' : '').'</p>';
        if ($title !== '') {
            $html .= '<p><strong>'.$e($title).'</strong></p>';
        }

        $items = (array) ($entry['items'] ?? []);
        if ($items) {
            $html .= '<ul>';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                $text = (string) ($item['text'] ?? '');
                $label = $labels[$type] ?? '';
                $html .= '<li>'.($label !== '' ? '<strong>'.$e($label).':</strong> ' : '').$e($text).'</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }
}
