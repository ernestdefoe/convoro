<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Discourse (PostgreSQL) → Convoro.
 *   categories → categories
 *   users      → users (passwords are PBKDF2 — not portable, so members reset)
 *   topics     → topics (skips PMs + deleted)
 *   posts      → posts (uses Discourse's already-rendered `cooked` HTML, sanitised)
 *
 * Needs the pdo_pgsql PHP extension on the server.
 */
class DiscourseImporter
{
    public static function test(array $cfg): array
    {
        $cfg['driver'] = ($cfg['driver'] ?? '') ?: 'pgsql';
        $conn = Src::connect($cfg);
        $sb = $conn->getSchemaBuilder();
        foreach (['users', 'topics', 'posts', 'categories'] as $req) {
            if (! $sb->hasTable($req)) {
                throw new \RuntimeException("This doesn't look like a Discourse database (missing “{$req}”). Discourse uses PostgreSQL.");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table('users')->where('id', '>', 0)->count(),
            'categories' => (int) $conn->table('categories')->count(),
            'topics' => (int) $conn->table('topics')->whereNull('deleted_at')->count(),
            'posts' => (int) $conn->table('posts')->whereNull('deleted_at')->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $cfg['driver'] = ($cfg['driver'] ?? '') ?: 'pgsql';
        $conn = Src::connect($cfg);
        $sb = $conn->getSchemaBuilder();
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'reactions' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $newTopics = $topicStat = [];

        $progress('Importing categories…', 5, $summary);
        foreach ($conn->table('categories')->orderBy('position')->orderBy('id')->get() as $cat) {
            $slug = Src::catSlug($cat->name ?: 'category', (int) $cat->id);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$cat->id] = $ex;

                continue;
            }
            $catMap[$cat->id] = DB::table('categories')->insertGetId([
                'name' => $cat->name ?: 'Category', 'slug' => $slug,
                'description' => Str::limit(strip_tags((string) ($cat->description ?? '')), 200) ?: null,
                'color' => Src::color($cat->color ?? null),
                'position' => (int) ($cat->position ?? 0), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        // Email lives in user_emails (newer) or users.email (older).
        $progress('Importing members…', 20, $summary);
        $emailByUser = [];
        if ($sb->hasTable('user_emails')) {
            foreach ($conn->table('user_emails')->orderByDesc('primary')->get(['user_id', 'email']) as $e) {
                $emailByUser[$e->user_id] ??= $e->email;
            }
        }
        $hasInlineEmail = $sb->hasColumn('users', 'email');
        $conn->table('users')->where('id', '>', 0)->orderBy('id')->chunk(200, function ($rows) use (&$userMap, &$summary, $emailByUser, $hasInlineEmail, $now) {
            foreach ($rows as $u) {
                try {
                    $email = trim((string) ($emailByUser[$u->id] ?? ($hasInlineEmail ? ($u->email ?? '') : '')));
                    if ($email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$u->id] = $ex;

                        continue;
                    }
                    $userMap[$u->id] = DB::table('users')->insertGetId([
                        'name' => $u->username ?: ($u->name ?? 'user'.$u->id),
                        'email' => $email,
                        'password' => Src::password(null), // PBKDF2 → random; members reset
                        'is_admin' => false,
                        'created_at' => Src::ts($u->created_at ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        $progress('Importing topics…', 40, $summary);
        $conn->table('topics')->whereNull('deleted_at')->where('archetype', 'regular')->orderBy('id')
            ->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $userMap) {
                foreach ($rows as $t) {
                    try {
                        $slug = (Str::slug($t->title ?: 'topic') ?: 'topic').'-'.$t->id;
                        if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                            $topicMap[$t->id] = $ex;

                            continue;
                        }
                        $topicMap[$t->id] = DB::table('topics')->insertGetId([
                            'title' => $t->title ?: 'Untitled', 'slug' => $slug,
                            'user_id' => $userMap[$t->user_id] ?? null, 'category_id' => $catMap[$t->category_id] ?? null,
                            'is_pinned' => ! empty($t->pinned_at), 'view_count' => (int) ($t->views ?? 0),
                            'created_at' => Src::ts($t->created_at ?? null),
                            'updated_at' => Src::ts($t->last_posted_at ?? $t->created_at ?? null),
                            'last_post_at' => Src::ts($t->last_posted_at ?? $t->created_at ?? null),
                        ]);
                        $newTopics[$t->id] = true;
                        $summary['topics']++;
                    } catch (\Throwable) {
                        $summary['skipped']++;
                    }
                }
            });

        $progress('Importing posts…', 65, $summary);
        $seenFirst = [];
        $conn->table('posts')->whereNull('deleted_at')->where('post_type', 1)->orderBy('topic_id')->orderBy('post_number')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, $topicMap, $newTopics, $userMap) {
                foreach ($rows as $post) {
                    if (empty($newTopics[$post->topic_id])) {
                        continue;
                    }
                    $tid = $topicMap[$post->topic_id] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->topic_id]);
                    $seenFirst[$post->topic_id] = true;
                    $created = Src::ts($post->created_at ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$post->user_id] ?? null,
                        'body_html' => Src::sanitizeHtml($post->cooked ?? '') ?: '<p></p>',
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
