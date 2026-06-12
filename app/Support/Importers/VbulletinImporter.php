<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * vBulletin 3.x / 4.x → Convoro.
 *   forum  → categories
 *   user   → users (vB hashes are md5-based, not portable → members reset)
 *   thread → topics
 *   post   → posts (BBCode → HTML)
 *
 * Supports an optional table prefix.
 */
class VbulletinImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? ''));
        $sb = $conn->getSchemaBuilder();
        foreach (['user', 'thread', 'post', 'forum'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like a vBulletin database (missing “{$p}{$req}”). Check the table prefix.");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table($p.'user')->count(),
            'categories' => (int) $conn->table($p.'forum')->count(),
            'topics' => (int) $conn->table($p.'thread')->count(),
            'posts' => (int) $conn->table($p.'post')->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? ''));
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'reactions' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $newTopics = $topicStat = [];

        $progress('Importing categories…', 5, $summary);
        foreach ($conn->table($p.'forum')->orderBy('displayorder')->orderBy('forumid')->get() as $f) {
            $slug = Src::catSlug($f->title ?: 'forum', (int) $f->forumid);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$f->forumid] = $ex;

                continue;
            }
            $catMap[$f->forumid] = DB::table('categories')->insertGetId([
                'name' => $f->title ?: 'Forum', 'slug' => $slug,
                'description' => Str::limit(strip_tags((string) ($f->description ?? '')), 200) ?: null,
                'color' => '#5b5bd6', 'position' => (int) ($f->displayorder ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        $conn->table($p.'user')->orderBy('userid')->chunk(200, function ($rows) use (&$userMap, &$summary, $now) {
            foreach ($rows as $u) {
                try {
                    $email = trim((string) ($u->email ?? ''));
                    if ($email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$u->userid] = $ex;

                        continue;
                    }
                    $userMap[$u->userid] = DB::table('users')->insertGetId([
                        'name' => $u->username ?: ('user'.$u->userid),
                        'email' => $email,
                        'password' => Src::password(null), // vB md5 → random; members reset
                        'is_admin' => false,
                        'created_at' => Src::ts($u->joindate ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing topics…', 40, $summary);
        $conn->table($p.'thread')->orderBy('threadid')->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $userMap) {
            foreach ($rows as $t) {
                try {
                    if ((int) ($t->visible ?? 1) !== 1) {
                        continue;
                    }
                    $slug = (Str::slug($t->title ?: 'topic') ?: 'topic').'-'.$t->threadid;
                    if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                        $topicMap[$t->threadid] = $ex;

                        continue;
                    }
                    $topicMap[$t->threadid] = DB::table('topics')->insertGetId([
                        'title' => $t->title ?: 'Untitled', 'slug' => $slug,
                        'user_id' => $userMap[$t->postuserid] ?? null, 'category_id' => $catMap[$t->forumid] ?? null,
                        'is_pinned' => (bool) ($t->sticky ?? false), 'view_count' => (int) ($t->views ?? 0),
                        'is_locked' => (int) ($t->open ?? 1) === 0,
                        'created_at' => Src::ts($t->dateline ?? null),
                        'updated_at' => Src::ts($t->lastpost ?? $t->dateline ?? null),
                        'last_post_at' => Src::ts($t->lastpost ?? $t->dateline ?? null),
                    ]);
                    $newTopics[$t->threadid] = true;
                    $summary['topics']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing posts…', 65, $summary);
        $seenFirst = [];
        $conn->table($p.'post')->orderBy('threadid')->orderBy('dateline')->orderBy('postid')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, $topicMap, $newTopics, $userMap) {
                foreach ($rows as $post) {
                    if ((int) ($post->visible ?? 1) !== 1 || empty($newTopics[$post->threadid])) {
                        continue;
                    }
                    $tid = $topicMap[$post->threadid] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->threadid]);
                    $seenFirst[$post->threadid] = true;
                    $created = Src::ts($post->dateline ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$post->userid] ?? null,
                        'body_html' => Bbcode::toHtml($post->pagetext ?? ''), 'body_json' => null, 'is_first' => $isFirst,
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
