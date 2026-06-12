<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vanilla Forums → Convoro.
 *   GDN_Category   → categories
 *   GDN_User       → users (Vanilla hashes are phpass/portable-mixed → members reset)
 *   GDN_Discussion → topics (the discussion Body is the first post)
 *   GDN_Comment    → posts (replies)
 *
 * Vanilla stores each post's markup format in a per-row `Format` column
 * (Html / Markdown / BBCode / Text / Rich [Quill JSON] / Wysiwyg), so the body
 * converter dispatches on it. Default table prefix is `GDN_`.
 */
class VanillaImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'GDN_')) ?: 'GDN_';
        $sb = $conn->getSchemaBuilder();
        foreach (['User', 'Category', 'Discussion', 'Comment'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like a Vanilla database (missing “{$p}{$req}”). Check the table prefix.");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table($p.'User')->count(),
            'categories' => (int) $conn->table($p.'Category')->where('CategoryID', '>', 0)->count(),
            'topics' => (int) $conn->table($p.'Discussion')->count(),
            'posts' => (int) $conn->table($p.'Comment')->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'GDN_')) ?: 'GDN_';
        $sb = $conn->getSchemaBuilder();
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $topicStat = [];

        // Categories — skip the synthetic Root (CategoryID -1) and heading-only nodes.
        $progress('Importing categories…', 5, $summary);
        foreach ($conn->table($p.'Category')->where('CategoryID', '>', 0)->orderBy('Sort')->orderBy('CategoryID')->get() as $c) {
            $name = trim((string) ($c->Name ?? '')) ?: ('Category '.$c->CategoryID);
            $slug = trim((string) ($c->UrlCode ?? '')) !== '' ? Str::slug($c->UrlCode).'-'.$c->CategoryID : Src::catSlug($name, (int) $c->CategoryID);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$c->CategoryID] = $ex;

                continue;
            }
            $catMap[$c->CategoryID] = DB::table('categories')->insertGetId([
                'name' => $name, 'slug' => $slug,
                'description' => Str::limit(strip_tags((string) ($c->Description ?? '')), 200) ?: null,
                'color' => '#5b5bd6', 'position' => (int) ($c->Sort ?? 0),
                'created_at' => Src::ts($c->DateInserted ?? null), 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        $hasDeleted = $sb->hasColumn($p.'User', 'Deleted');
        $conn->table($p.'User')->orderBy('UserID')->chunk(200, function ($rows) use (&$userMap, &$summary, $hasDeleted, $now) {
            foreach ($rows as $u) {
                try {
                    if ($hasDeleted && (int) ($u->Deleted ?? 0) === 1) {
                        $summary['skipped']++;

                        continue;
                    }
                    $email = trim((string) ($u->Email ?? ''));
                    if ($email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$u->UserID] = $ex;

                        continue;
                    }
                    $userMap[$u->UserID] = DB::table('users')->insertGetId([
                        'name' => $u->Name ?: ('user'.$u->UserID),
                        'email' => $email,
                        'password' => Src::password($u->Password ?? null), // phpass etc → reset; bcrypt copies
                        'is_admin' => false,
                        'created_at' => Src::ts($u->DateInserted ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        // Discussions → topics; the discussion's own Body is the first post.
        $progress('Importing discussions…', 40, $summary);
        $conn->table($p.'Discussion')->orderBy('DiscussionID')->chunk(200, function ($rows) use (&$topicMap, &$topicStat, &$summary, $catMap, $userMap) {
            foreach ($rows as $d) {
                try {
                    $title = trim((string) ($d->Name ?? '')) ?: 'Untitled';
                    $slug = (Str::slug($title) ?: 'topic').'-'.$d->DiscussionID;
                    if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                        $topicMap[$d->DiscussionID] = $ex;

                        continue;
                    }
                    $created = Src::ts($d->DateInserted ?? null);
                    $tid = DB::table('topics')->insertGetId([
                        'title' => $title, 'slug' => $slug,
                        'user_id' => $userMap[$d->InsertUserID] ?? null, 'category_id' => $catMap[$d->CategoryID] ?? null,
                        'is_pinned' => (int) ($d->Announce ?? 0) > 0,
                        'is_locked' => (int) ($d->Closed ?? 0) === 1,
                        'view_count' => (int) ($d->CountViews ?? 0),
                        'created_at' => $created,
                        'updated_at' => Src::ts($d->DateLastComment ?? $d->DateInserted ?? null),
                        'last_post_at' => Src::ts($d->DateLastComment ?? $d->DateInserted ?? null),
                    ]);
                    $topicMap[$d->DiscussionID] = $tid;
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$d->InsertUserID] ?? null,
                        'body_html' => self::body($d->Body ?? '', $d->Format ?? '') ?: '<p></p>',
                        'body_json' => null, 'is_first' => true,
                        'created_at' => $created, 'updated_at' => $created,
                    ]);
                    $topicStat[$tid] = ['n' => 1, 'last' => $created];
                    $summary['topics']++;
                    $summary['posts']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing comments…', 70, $summary);
        $conn->table($p.'Comment')->orderBy('DiscussionID')->orderBy('DateInserted')->orderBy('CommentID')
            ->chunk(300, function ($rows) use (&$summary, &$topicStat, $topicMap, $userMap) {
                foreach ($rows as $c) {
                    $tid = $topicMap[$c->DiscussionID] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $created = Src::ts($c->DateInserted ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$c->InsertUserID] ?? null,
                        'body_html' => self::body($c->Body ?? '', $c->Format ?? '') ?: '<p></p>',
                        'body_json' => null, 'is_first' => false,
                        'created_at' => $created, 'updated_at' => $created,
                    ]);
                    $st = $topicStat[$tid] ?? ['n' => 0, 'last' => null];
                    $st['n']++;
                    if ($st['last'] === null || $created->gt($st['last'])) {
                        $st['last'] = $created;
                    }
                    $topicStat[$tid] = $st;
                    $summary['posts']++;
                }
            });

        $progress('Finishing up…', 95, $summary);
        foreach ($topicStat as $tid => $st) {
            DB::table('topics')->where('id', $tid)->update(['reply_count' => max(0, $st['n'] - 1), 'last_post_at' => $st['last']]);
        }
        $progress('Import complete.', 100, $summary);

        return $summary;
    }

    /** Convert a Vanilla body according to its per-row Format. */
    public static function body(?string $body, ?string $format): string
    {
        $body = (string) $body;
        if (trim($body) === '') {
            return '';
        }
        switch (strtolower(trim((string) $format))) {
            case 'html':
            case 'wysiwyg':
                return Src::sanitizeHtml($body);
            case 'bbcode':
                return Bbcode::toHtml($body);
            case 'markdown':
                try {
                    $conv = new \League\CommonMark\CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);

                    return Src::sanitizeHtml((string) $conv->convert($body));
                } catch (\Throwable) {
                    return self::textToHtml($body);
                }
            case 'rich':
            case 'rich2':
                return self::richToHtml($body);
            default: // 'text' and anything unknown
                return self::textToHtml($body);
        }
    }

    /** Plain text → safe paragraphs. */
    private static function textToHtml(string $text): string
    {
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($text)) as $block) {
            $block = trim((string) $block);
            if ($block !== '') {
                $out .= '<p>'.nl2br(htmlspecialchars($block, ENT_QUOTES), false).'</p>';
            }
        }

        return $out;
    }

    /**
     * Vanilla "Rich"/"Rich2" bodies are a Quill delta (JSON array of {insert,…}).
     * Best-effort: pull the text out of the inserts and paragraph-ise it (formatting
     * like bold/links is dropped, but the content and structure survive, safely).
     */
    private static function richToHtml(string $json): string
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return self::textToHtml($json);
        }
        // Quill delta may be {ops:[…]} or a bare array of ops.
        $ops = $data['ops'] ?? $data;
        if (! is_array($ops)) {
            return self::textToHtml($json);
        }
        $text = '';
        foreach ($ops as $op) {
            $insert = is_array($op) ? ($op['insert'] ?? '') : '';
            if (is_string($insert)) {
                $text .= $insert;
            } elseif (is_array($insert) && isset($insert['url'])) {
                $text .= ' '.$insert['url'].' '; // embedded media → its URL (re-embedded by the renderer)
            }
        }

        return self::textToHtml($text);
    }
}
