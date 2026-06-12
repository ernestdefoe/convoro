<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SMF (Simple Machines Forum) 2.0 / 2.1 → Convoro.
 *   smf_boards   → categories (redirect boards skipped)
 *   smf_members  → users (passwords NOT portable — see below — so members reset)
 *   smf_topics   → topics (title comes from the first message; sticky/locked carried)
 *   smf_messages → posts (BBCode + <br> + HTML entities → HTML)
 *
 * Password note: SMF 2.1 hashes password_hash(strtolower(username) . password) — the
 * bcrypt string mixes in the username, so it can't be verified by a plain bcrypt(pw)
 * check. SMF 2.0 used sha1(strtolower(username) . password). Neither is portable, so
 * every member resets their password on first login.
 *
 * Table prefix defaults to `smf_` and is configurable in the wizard.
 */
class SmfImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'smf_')) ?: 'smf_';
        $sb = $conn->getSchemaBuilder();
        foreach (['members', 'boards', 'topics', 'messages'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like an SMF database (missing “{$p}{$req}”). Check the table prefix.");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table($p.'members')->count(),
            'categories' => (int) $conn->table($p.'boards')->count(),
            'topics' => (int) $conn->table($p.'topics')->where('approved', 1)->count(),
            'posts' => (int) $conn->table($p.'messages')->where('approved', 1)->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'smf_')) ?: 'smf_';
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $newTopics = $topicStat = [];

        $progress('Importing boards…', 5, $summary);
        foreach ($conn->table($p.'boards')->orderBy('board_order')->orderBy('id_board')->get() as $b) {
            if (trim((string) ($b->redirect ?? '')) !== '') { // redirect board — no content
                continue;
            }
            $name = self::decode($b->name ?: 'Board');
            $slug = Src::catSlug($name, (int) $b->id_board);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$b->id_board] = $ex;

                continue;
            }
            $catMap[$b->id_board] = DB::table('categories')->insertGetId([
                'name' => $name, 'slug' => $slug,
                'description' => Str::limit(strip_tags(self::decode((string) ($b->description ?? ''))), 200) ?: null,
                'color' => '#5b5bd6', 'position' => (int) ($b->board_order ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        $conn->table($p.'members')->where('id_member', '>', 0)->orderBy('id_member')
            ->chunk(200, function ($rows) use (&$userMap, &$summary, $now) {
                foreach ($rows as $u) {
                    try {
                        $email = trim((string) ($u->email_address ?? ''));
                        if ($email === '') {
                            $summary['skipped']++;

                            continue;
                        }
                        if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                            $userMap[$u->id_member] = $ex;

                            continue;
                        }
                        $name = self::decode(trim((string) ($u->real_name ?? '')) ?: (string) $u->member_name);
                        $avatar = preg_match('#^https?://#i', trim((string) ($u->avatar ?? ''))) ? trim((string) $u->avatar) : null;
                        $bio = trim((string) ($u->signature ?? ''));
                        $userMap[$u->id_member] = DB::table('users')->insertGetId([
                            'name' => $name ?: ('member'.$u->id_member),
                            'email' => $email,
                            'password' => Src::password(null), // username-salted hash → not portable; members reset
                            'bio' => $bio !== '' ? Str::limit(strip_tags(self::decode($bio)), 500) : null,
                            'avatar_path' => $avatar,
                            'is_admin' => false,
                            'created_at' => Src::ts($u->date_registered ?? null), 'updated_at' => $now,
                        ]);
                        $summary['users']++;
                    } catch (\Throwable) {
                        $summary['skipped']++;
                    }
                }
            });

        // SMF topics carry no title — it lives on the first message. Pull the
        // subject + time for every approved topic's first message up front.
        $progress('Importing topics…', 40, $summary);
        $topics = $conn->table($p.'topics')->where('approved', 1)->where('id_redirect_topic', 0)->orderBy('id_topic')->get();
        $firstMsgIds = $topics->pluck('id_first_msg')->filter()->all();
        $firstMsg = [];
        foreach (array_chunk($firstMsgIds, 500) as $ids) {
            foreach ($conn->table($p.'messages')->whereIn('id_msg', $ids)->get(['id_msg', 'subject', 'poster_time']) as $m) {
                $firstMsg[$m->id_msg] = $m;
            }
        }
        foreach ($topics as $t) {
            try {
                $fm = $firstMsg[$t->id_first_msg] ?? null;
                $title = self::decode($fm->subject ?? '') ?: 'Untitled';
                $slug = (Str::slug($title) ?: 'topic').'-'.$t->id_topic;
                if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                    $topicMap[$t->id_topic] = $ex;

                    continue;
                }
                $created = Src::ts($fm->poster_time ?? null);
                $topicMap[$t->id_topic] = DB::table('topics')->insertGetId([
                    'title' => $title, 'slug' => $slug,
                    'user_id' => $userMap[$t->id_member_started] ?? null, 'category_id' => $catMap[$t->id_board] ?? null,
                    'is_pinned' => (bool) ($t->is_sticky ?? false),
                    'is_locked' => (int) ($t->locked ?? 0) === 1,
                    'view_count' => (int) ($t->num_views ?? 0),
                    'created_at' => $created, 'updated_at' => $created, 'last_post_at' => $created,
                ]);
                $newTopics[$t->id_topic] = true;
                $summary['topics']++;
            } catch (\Throwable) {
                $summary['skipped']++;
            }
        }

        $progress('Importing posts…', 65, $summary);
        $seenFirst = [];
        $conn->table($p.'messages')->where('approved', 1)->orderBy('id_topic')->orderBy('id_msg')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, $topicMap, $newTopics, $userMap) {
                foreach ($rows as $m) {
                    if (empty($newTopics[$m->id_topic])) {
                        continue;
                    }
                    $tid = $topicMap[$m->id_topic] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$m->id_topic]);
                    $seenFirst[$m->id_topic] = true;
                    $created = Src::ts($m->poster_time ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$m->id_member] ?? null,
                        'body_html' => self::body($m->body ?? '') ?: '<p></p>',
                        'body_json' => null, 'is_first' => $isFirst,
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

    /** SMF stores titles/names HTML-entity-encoded. */
    private static function decode(string $s): string
    {
        return trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** SMF message body = BBCode + literal <br /> line breaks + HTML entities. */
    private static function body(?string $body): string
    {
        $body = preg_replace('#<br\s*/?>#i', "\n", (string) $body) ?? (string) $body;

        return Bbcode::toHtml($body, ['escaped' => true]);
    }
}
