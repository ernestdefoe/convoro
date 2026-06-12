<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * phpBB 3.x → Convoro.
 *   phpbb_forums → categories
 *   phpbb_users  → users (bcrypt $2y$ copied; older phpass → random)
 *   phpbb_topics → topics
 *   phpbb_posts  → posts (BBCode with bbcode_uid → HTML)
 *
 * The table prefix (default `phpbb_`) is configurable in the wizard.
 */
class PhpbbImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'phpbb_')) ?: 'phpbb_';
        $sb = $conn->getSchemaBuilder();
        foreach (['users', 'forums', 'topics', 'posts'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like a phpBB database (missing “{$p}{$req}”). Check the table prefix.");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table($p.'users')->count(),
            'categories' => (int) $conn->table($p.'forums')->count(),
            'topics' => (int) $conn->table($p.'topics')->count(),
            'posts' => (int) $conn->table($p.'posts')->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? 'phpbb_')) ?: 'phpbb_';
        $sb = $conn->getSchemaBuilder();
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'reactions' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $newTopics = $topicStat = [];

        $progress('Importing categories…', 5, $summary);
        // forum_type: 0 = category container (no posts), 1 = POST forum, 2 = link.
        // Only POST forums hold topics, so skip the others when the column exists.
        $hasForumType = $sb->hasColumn($p.'forums', 'forum_type');
        foreach ($conn->table($p.'forums')->orderBy('left_id')->orderBy('forum_id')->get() as $f) {
            if ($hasForumType && (int) ($f->forum_type ?? 1) !== 1) {
                continue;
            }
            $slug = Src::catSlug($f->forum_name ?: 'forum', (int) $f->forum_id);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$f->forum_id] = $ex;

                continue;
            }
            $catMap[$f->forum_id] = DB::table('categories')->insertGetId([
                'name' => $f->forum_name ?: 'Forum', 'slug' => $slug,
                'description' => Str::limit(strip_tags((string) ($f->forum_desc ?? '')), 200) ?: null,
                'color' => '#5b5bd6', 'position' => (int) ($f->left_id ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        $conn->table($p.'users')->orderBy('user_id')->chunk(200, function ($rows) use (&$userMap, &$summary, $now) {
            foreach ($rows as $u) {
                try {
                    $email = trim((string) ($u->user_email ?? ''));
                    if ($email === '' || (int) ($u->user_type ?? 0) === 2) { // type 2 = ignore (bots)
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$u->user_id] = $ex;

                        continue;
                    }
                    $userMap[$u->user_id] = DB::table('users')->insertGetId([
                        'name' => $u->username ?: ('user'.$u->user_id),
                        'email' => $email,
                        'password' => Src::password($u->user_password ?? null),
                        'is_admin' => false,
                        'created_at' => Src::ts($u->user_regdate ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing topics…', 40, $summary);
        $visCol = $sb->hasColumn($p.'topics', 'topic_visibility') ? 'topic_visibility' : null;
        $conn->table($p.'topics')->orderBy('topic_id')->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $userMap, $visCol, $now) {
            foreach ($rows as $t) {
                try {
                    if ($visCol && (int) ($t->$visCol ?? 1) !== 1) {
                        continue;
                    }
                    $slug = (Str::slug($t->topic_title ?: 'topic') ?: 'topic').'-'.$t->topic_id;
                    if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                        $topicMap[$t->topic_id] = $ex;

                        continue;
                    }
                    $topicMap[$t->topic_id] = DB::table('topics')->insertGetId([
                        'title' => $t->topic_title ?: 'Untitled', 'slug' => $slug,
                        'user_id' => $userMap[$t->topic_poster] ?? null, 'category_id' => $catMap[$t->forum_id] ?? null,
                        'is_pinned' => (int) ($t->topic_type ?? 0) >= 1, 'view_count' => (int) ($t->topic_views ?? 0),
                        'created_at' => Src::ts($t->topic_time ?? null),
                        'updated_at' => Src::ts($t->topic_last_post_time ?? $t->topic_time ?? null),
                        'last_post_at' => Src::ts($t->topic_last_post_time ?? $t->topic_time ?? null),
                    ]);
                    $newTopics[$t->topic_id] = true;
                    $summary['topics']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing posts…', 65, $summary);
        $postVisCol = $sb->hasColumn($p.'posts', 'post_visibility') ? 'post_visibility'
            : ($sb->hasColumn($p.'posts', 'post_approved') ? 'post_approved' : null);
        $seenFirst = [];
        $conn->table($p.'posts')->orderBy('topic_id')->orderBy('post_time')->orderBy('post_id')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, $topicMap, $newTopics, $userMap, $postVisCol) {
                foreach ($rows as $post) {
                    if ($postVisCol && (int) ($post->$postVisCol ?? 1) !== 1) {
                        continue;
                    }
                    if (empty($newTopics[$post->topic_id])) {
                        continue;
                    }
                    $tid = $topicMap[$post->topic_id] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->topic_id]);
                    $seenFirst[$post->topic_id] = true;
                    $created = Src::ts($post->post_time ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$post->poster_id] ?? null,
                        'body_html' => Bbcode::toHtml($post->post_text ?? '', ['uid' => (string) ($post->bbcode_uid ?? ''), 'escaped' => true]),
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
