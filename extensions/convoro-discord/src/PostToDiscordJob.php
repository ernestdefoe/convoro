<?php

namespace Convoro\Ext\Discord;

use App\Models\Topic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Announces one published topic to its category's Discord channel webhook. */
class PostToDiscordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $topicId) {}

    public function handle(): void
    {
        if (! Extension::enabled()) {
            return;
        }

        $topic = Topic::find($this->topicId);
        if (! $topic) {
            return;
        }

        // Which channel webhook (if any) does this topic's category post to?
        $webhook = Extension::webhookFor((int) $topic->category_id);
        if ($webhook === null) {
            self::record($topic->id, 'skipped');

            return;
        }

        // Never announce the same topic twice.
        if (DB::table('discord_posts')->where('topic_id', $topic->id)->whereIn('status', ['posted', 'skipped'])->exists()) {
            return;
        }

        try {
            $resp = Http::acceptJson()->timeout(20)->post($webhook, self::payload($topic));
            if (! $resp->successful()) {
                $err = (string) ($resp->json('message') ?? ('HTTP '.$resp->status()));
                self::record($topic->id, 'failed', Str::limit($err, 480));
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff);
                }

                return;
            }
            self::record($topic->id, 'posted');
        } catch (\Throwable $e) {
            self::record($topic->id, 'failed', Str::limit($e->getMessage(), 480));
            throw $e; // let the queue retry
        }
    }

    /** Build the Discord webhook payload (a single rich embed + link). */
    private static function payload(Topic $topic): array
    {
        $url = url('/t/'.$topic->slug);
        $author = optional($topic->user)->name ?? 'Someone';
        $category = $topic->category_id ? (string) DB::table('categories')->where('id', $topic->category_id)->value('name') : '';

        $html = DB::table('posts')->where('topic_id', $topic->id)->where('is_first', true)->value('body_html');
        $excerpt = Str::limit(trim(html_entity_decode(strip_tags((string) $html))), 280);

        return [
            'embeds' => [[
                'title' => Str::limit($topic->title, 240),
                'url' => $url,
                'description' => $excerpt !== '' ? $excerpt : null,
                'color' => 0x5865F2,
                'author' => ['name' => $author],
                'footer' => ['text' => $category !== '' ? $category : 'New topic'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }

    private static function record(int $topicId, string $status, ?string $error = null): void
    {
        DB::table('discord_posts')->updateOrInsert(
            ['topic_id' => $topicId],
            [
                'status' => $status,
                'error' => $error,
                'posted_at' => $status === 'posted' ? now() : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
