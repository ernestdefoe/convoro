<?php

namespace Convoro\Ext\FacebookAutopost;

use App\Models\Topic;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Shares one published topic to the configured Facebook Page (Graph API). */
class PostToFacebookJob implements ShouldQueue
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

        // Per-category gate (empty = all categories).
        $only = Extension::categories();
        if ($only && ! in_array((int) $topic->category_id, $only, true)) {
            self::record($topic->id, 'skipped');

            return;
        }

        // Dedupe: never post the same topic twice.
        if (DB::table('facebook_posts')->where('topic_id', $topic->id)->whereIn('status', ['posted', 'skipped'])->exists()) {
            return;
        }

        $pageId = Settings::get('fb_autopost.page_id', '');
        $token = Settings::get('fb_autopost.access_token', '');
        if ($pageId === '' || $token === '') {
            self::record($topic->id, 'failed', null, 'Page ID or access token not configured.');

            return;
        }

        $message = self::message($topic);
        $link = url('/t/'.$topic->slug);

        try {
            $resp = Http::asForm()->acceptJson()->timeout(20)->post(
                "https://graph.facebook.com/v19.0/{$pageId}/feed",
                ['message' => $message, 'link' => $link, 'access_token' => $token],
            );
            $body = $resp->json() ?? [];
            if (! $resp->successful() || empty($body['id'])) {
                $err = $body['error']['message'] ?? ('HTTP '.$resp->status());
                self::record($topic->id, 'failed', null, Str::limit((string) $err, 480));
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff);
                }

                return;
            }
            self::record($topic->id, 'posted', $body['id']);
        } catch (\Throwable $e) {
            self::record($topic->id, 'failed', null, Str::limit($e->getMessage(), 480));
            throw $e; // let the queue retry
        }
    }

    /** Build the post message from the admin template + topic fields. */
    private static function message(Topic $topic): string
    {
        $template = (string) Settings::get('fb_autopost.message_template', 'New on our community: {title}');
        $author = optional($topic->user)->name ?? '';
        $category = $topic->category_id ? (string) DB::table('categories')->where('id', $topic->category_id)->value('name') : '';

        $excerpt = '';
        if (Settings::get('fb_autopost.include_excerpt', false)) {
            $html = DB::table('posts')->where('topic_id', $topic->id)->where('is_first', true)->value('body_html');
            $excerpt = Str::limit(trim(html_entity_decode(strip_tags((string) $html))), 200);
        }

        $out = strtr($template, [
            '{title}' => $topic->title,
            '{author}' => $author,
            '{category}' => $category,
            '{excerpt}' => $excerpt,
        ]);

        // If the template doesn't reference {excerpt} but excerpts are on, append it.
        if ($excerpt !== '' && ! str_contains($template, '{excerpt}')) {
            $out .= "\n\n".$excerpt;
        }

        return trim($out);
    }

    private static function record(int $topicId, string $status, ?string $fbId = null, ?string $error = null): void
    {
        DB::table('facebook_posts')->updateOrInsert(
            ['topic_id' => $topicId],
            [
                'fb_post_id' => $fbId,
                'status' => $status,
                'error' => $error,
                'posted_at' => $status === 'posted' ? now() : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
