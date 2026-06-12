<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MyBB 1.8.x → Convoro.
 *   mybb_forums  → categories (type 'f' only; 'c' containers + redirect links skipped)
 *   mybb_users   → users (passwords are md5(md5(salt).md5(pw)) — not portable, members reset)
 *   mybb_threads → topics (visible 1 only; sticky → pinned, closed → locked)
 *   mybb_posts   → posts (MyCode BBCode → HTML)
 *
 * Table prefix defaults to `mybb_` and is configurable in the wizard.
 */
class MybbImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'mybb_')) ?: 'mybb_';
        $sb = $conn->getSchemaBuilder();
        foreach (['users', 'forums', 'threads', 'posts'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like a MyBB database (missing “{$p}{$req}”). Check the table prefix.");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table($p.'users')->count(),
            'categories' => (int) $conn->table($p.'forums')->where('type', 'f')->count(),
            'topics' => (int) $conn->table($p.'threads')->where('visible', 1)->count(),
            'posts' => (int) $conn->table($p.'posts')->where('visible', 1)->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'mybb_')) ?: 'mybb_';
        $sb = $conn->getSchemaBuilder();
        $now = now();
        $base = rtrim((string) ($cfg['source_url'] ?? ''), '/');
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $newTopics = $topicStat = [];

        $progress('Importing categories…', 5, $summary);
        foreach ($conn->table($p.'forums')->where('type', 'f')->orderBy('disporder')->orderBy('fid')->get() as $f) {
            // Redirect/link forums hold no content.
            if (trim((string) ($f->linkto ?? '')) !== '') {
                continue;
            }
            $slug = Src::catSlug($f->name ?: 'forum', (int) $f->fid);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$f->fid] = $ex;

                continue;
            }
            $catMap[$f->fid] = DB::table('categories')->insertGetId([
                'name' => $f->name ?: 'Forum', 'slug' => $slug,
                'description' => Str::limit(strip_tags((string) ($f->description ?? '')), 200) ?: null,
                'color' => '#5b5bd6', 'position' => (int) ($f->disporder ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        $conn->table($p.'users')->orderBy('uid')->chunk(200, function ($rows) use (&$userMap, &$summary, $base, $now) {
            foreach ($rows as $u) {
                try {
                    $email = trim((string) ($u->email ?? ''));
                    if ($email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$u->uid] = $ex;

                        continue;
                    }
                    // MyBB avatars: external URL as-is, or a board-relative upload path.
                    $avatar = null;
                    $av = trim((string) ($u->avatar ?? ''));
                    if ($av !== '') {
                        $av = preg_replace('/\?dateline=.*$/', '', $av); // drop cache-buster
                        if (preg_match('#^https?://#i', $av)) {
                            $avatar = $av;
                        } elseif ($base !== '') {
                            $avatar = $base.'/'.ltrim($av, './');
                        }
                    }
                    $bio = trim((string) ($u->signature ?? ''));
                    $userMap[$u->uid] = DB::table('users')->insertGetId([
                        'name' => $u->username ?: ('user'.$u->uid),
                        'email' => $email,
                        'password' => Src::password(null), // md5+salt → not portable; members reset
                        'bio' => $bio !== '' ? Str::limit(strip_tags($bio), 500) : null,
                        'avatar_path' => $avatar,
                        'is_admin' => false,
                        'created_at' => Src::ts($u->regdate ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing topics…', 40, $summary);
        $conn->table($p.'threads')->where('visible', 1)->orderBy('tid')
            ->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $userMap) {
                foreach ($rows as $t) {
                    try {
                        $slug = (Str::slug($t->subject ?: 'topic') ?: 'topic').'-'.$t->tid;
                        if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                            $topicMap[$t->tid] = $ex;

                            continue;
                        }
                        $topicMap[$t->tid] = DB::table('topics')->insertGetId([
                            'title' => $t->subject ?: 'Untitled', 'slug' => $slug,
                            'user_id' => $userMap[$t->uid] ?? null, 'category_id' => $catMap[$t->fid] ?? null,
                            'is_pinned' => (bool) ($t->sticky ?? false),
                            'is_locked' => (int) ($t->closed ?? 0) === 1,
                            'view_count' => (int) ($t->views ?? 0),
                            'created_at' => Src::ts($t->dateline ?? null),
                            'updated_at' => Src::ts($t->lastpost ?? $t->dateline ?? null),
                            'last_post_at' => Src::ts($t->lastpost ?? $t->dateline ?? null),
                        ]);
                        $newTopics[$t->tid] = true;
                        $summary['topics']++;
                    } catch (\Throwable) {
                        $summary['skipped']++;
                    }
                }
            });

        $progress('Importing posts…', 65, $summary);
        $seenFirst = [];
        $conn->table($p.'posts')->where('visible', 1)->orderBy('tid')->orderBy('dateline')->orderBy('pid')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, $topicMap, $newTopics, $userMap) {
                foreach ($rows as $post) {
                    if (empty($newTopics[$post->tid])) {
                        continue;
                    }
                    $tid = $topicMap[$post->tid] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->tid]);
                    $seenFirst[$post->tid] = true;
                    $created = Src::ts($post->dateline ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$post->uid] ?? null,
                        'body_html' => Bbcode::toHtml($post->message ?? '') ?: '<p></p>',
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
}
