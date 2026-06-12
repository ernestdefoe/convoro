<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * XenForo (1.x / 2.x) → Convoro.
 *   xf_node (Forum)  → categories
 *   xf_user          → users (bcrypt from xf_user_authenticate when available)
 *   xf_thread        → topics
 *   xf_post          → posts (BBCode → HTML)
 *   xf_reaction_content → reactions
 */
class XenForoImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $sb = $conn->getSchemaBuilder();
        foreach (['xf_user', 'xf_thread', 'xf_post'] as $req) {
            if (! $sb->hasTable($req)) {
                throw new \RuntimeException("This doesn't look like a XenForo database (missing “{$req}”).");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table('xf_user')->count(),
            'categories' => $sb->hasTable('xf_node') ? (int) $conn->table('xf_node')->where('node_type_id', 'Forum')->count() : 0,
            'topics' => (int) $conn->table('xf_thread')->count(),
            'posts' => (int) $conn->table('xf_post')->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $sb = $conn->getSchemaBuilder();
        $now = now();
        $base = rtrim((string) ($cfg['source_url'] ?? ''), '/');
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'reactions' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $postMap = $newTopics = $topicStat = [];

        $progress('Importing categories…', 5, $summary);
        if ($sb->hasTable('xf_node')) {
            foreach ($conn->table('xf_node')->where('node_type_id', 'Forum')->orderBy('display_order')->orderBy('node_id')->get() as $n) {
                $slug = Src::catSlug($n->title ?: 'forum', (int) $n->node_id);
                if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                    $catMap[$n->node_id] = $ex;

                    continue;
                }
                $catMap[$n->node_id] = DB::table('categories')->insertGetId([
                    'name' => $n->title ?: 'Forum', 'slug' => $slug,
                    'description' => Str::limit(strip_tags((string) ($n->description ?? '')), 200) ?: null,
                    'color' => '#5b5bd6', 'position' => (int) ($n->display_order ?? 0),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $summary['categories']++;
            }
        }

        $progress('Importing members…', 20, $summary);
        $pw = [];
        if ($sb->hasTable('xf_user_authenticate')) {
            foreach ($conn->table('xf_user_authenticate')->get(['user_id', 'data']) as $a) {
                $arr = @unserialize((string) $a->data);
                if (is_array($arr) && ! empty($arr['hash']) && ($arr['hashFunc'] ?? '') === 'password_hash') {
                    $pw[$a->user_id] = $arr['hash'];
                }
            }
        }
        $about = [];
        if ($sb->hasTable('xf_user_profile') && $sb->hasColumn('xf_user_profile', 'about')) {
            foreach ($conn->table('xf_user_profile')->get(['user_id', 'about']) as $pr) {
                $about[$pr->user_id] = $pr->about;
            }
        }
        $hasAvatarDate = $sb->hasColumn('xf_user', 'avatar_date');
        $conn->table('xf_user')->orderBy('user_id')->chunk(200, function ($rows) use (&$userMap, &$summary, $pw, $about, $base, $hasAvatarDate, $now) {
            foreach ($rows as $u) {
                try {
                    $email = trim((string) ($u->email ?? ''));
                    if ($email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$u->user_id] = $ex;

                        continue;
                    }
                    $avatar = ($base !== '' && $hasAvatarDate && (int) ($u->avatar_date ?? 0) > 0)
                        ? $base.'/data/avatars/l/'.intdiv((int) $u->user_id, 1000).'/'.$u->user_id.'.jpg'
                        : null;
                    $userMap[$u->user_id] = DB::table('users')->insertGetId([
                        'name' => $u->username ?: ('user'.$u->user_id),
                        'email' => $email,
                        'password' => Src::password($pw[$u->user_id] ?? null),
                        'bio' => isset($about[$u->user_id]) ? Str::limit(strip_tags((string) $about[$u->user_id]), 500) : null,
                        'avatar_path' => $avatar,
                        'is_admin' => false,
                        'created_at' => Src::ts($u->register_date ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing topics…', 40, $summary);
        $conn->table('xf_thread')->orderBy('thread_id')->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $userMap, $now) {
            foreach ($rows as $t) {
                try {
                    if (($t->discussion_state ?? 'visible') !== 'visible') {
                        continue;
                    }
                    $slug = (Str::slug($t->title ?: 'topic') ?: 'topic').'-'.$t->thread_id;
                    if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                        $topicMap[$t->thread_id] = $ex;

                        continue;
                    }
                    $topicMap[$t->thread_id] = DB::table('topics')->insertGetId([
                        'title' => $t->title ?: 'Untitled', 'slug' => $slug,
                        'user_id' => $userMap[$t->user_id] ?? null, 'category_id' => $catMap[$t->node_id] ?? null,
                        'is_pinned' => (bool) ($t->sticky ?? false), 'view_count' => (int) ($t->view_count ?? 0),
                        'created_at' => Src::ts($t->post_date ?? null),
                        'updated_at' => Src::ts($t->last_post_date ?? $t->post_date ?? null),
                        'last_post_at' => Src::ts($t->last_post_date ?? $t->post_date ?? null),
                    ]);
                    $newTopics[$t->thread_id] = true;
                    $summary['topics']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing posts…', 65, $summary);
        $seenFirst = [];
        $conn->table('xf_post')->orderBy('thread_id')->orderBy('position')->orderBy('post_id')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, &$postMap, $topicMap, $newTopics, $userMap) {
                foreach ($rows as $post) {
                    if (($post->message_state ?? 'visible') !== 'visible' || empty($newTopics[$post->thread_id])) {
                        continue;
                    }
                    $tid = $topicMap[$post->thread_id] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->thread_id]);
                    $seenFirst[$post->thread_id] = true;
                    $created = Src::ts($post->post_date ?? null);
                    $postMap[$post->post_id] = DB::table('posts')->insertGetId([
                        'topic_id' => $tid, 'user_id' => $userMap[$post->user_id] ?? null,
                        'body_html' => Bbcode::toHtml($post->message ?? ''), 'body_json' => null, 'is_first' => $isFirst,
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

        if ($sb->hasTable('xf_reaction_content')) {
            $progress('Importing reactions…', 85, $summary);
            $conn->table('xf_reaction_content')->where('content_type', 'post')->orderBy('reaction_content_id')
                ->chunk(500, function ($rows) use (&$summary, $postMap, $userMap, $now) {
                    $ins = [];
                    foreach ($rows as $r) {
                        $pid = $postMap[$r->content_id] ?? null;
                        $uid = $userMap[$r->reaction_user_id] ?? null;
                        if ($pid && $uid) {
                            $ins[] = ['post_id' => $pid, 'user_id' => $uid, 'emoji' => '👍', 'created_at' => $now, 'updated_at' => $now];
                            $summary['reactions']++;
                        }
                    }
                    if ($ins) {
                        DB::table('reactions')->insertOrIgnore($ins);
                    }
                });
        }

        $progress('Finishing up…', 95, $summary);
        foreach ($topicStat as $tid => $st) {
            DB::table('topics')->where('id', $tid)->update(['reply_count' => max(0, $st['n'] - 1), 'last_post_at' => $st['last']]);
        }
        $progress('Import complete.', 100, $summary);

        return $summary;
    }
}
