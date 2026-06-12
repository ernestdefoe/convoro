<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NodeBB → Convoro (Redis-backed installs).
 *
 * Unlike the SQL importers, NodeBB stores everything as Redis hashes + sorted sets,
 * so this reads the key/value store directly instead of using Src::connect:
 *   categories:cid (zset) + category:{cid} (hash)  → categories
 *   users:joindate (zset) + user:{uid} (hash)      → users
 *   topics:tid (zset) + topic:{tid} (hash)         → topics (topic.mainPid = first post)
 *   tid:{tid}:posts (zset) + post:{pid} (hash)     → replies
 *
 * Post content is Markdown. Timestamps are epoch-milliseconds. NodeBB bcrypt hashes
 * are sha512-wrapped (password:shaWrapped), so a plain bcrypt(pw) check can't verify
 * them → members reset on first login.
 *
 * cfg: host, port (default 6379), database (Redis DB index, default 0), password.
 *
 * Reads via a raw phpredis client (not Laravel's Redis facade) so Convoro's own
 * key prefix isn't prepended to NodeBB's keys, and so it talks to the foreign
 * Redis server cleanly. Requires the phpredis extension.
 */
class NodebbImporter
{
    private static function redis(array $cfg): \Redis
    {
        if (! class_exists(\Redis::class)) {
            throw new \RuntimeException('Importing from NodeBB requires the PHP “redis” (phpredis) extension on the server.');
        }
        $r = new \Redis;
        if (! @$r->connect($cfg['host'] ?: '127.0.0.1', (int) ($cfg['port'] ?: 6379), 3.0)) {
            throw new \RuntimeException('Could not connect to the NodeBB Redis server at '.($cfg['host'] ?: '127.0.0.1').':'.($cfg['port'] ?: 6379).'.');
        }
        if (($cfg['password'] ?? '') !== '') {
            $r->auth($cfg['password']);
        }
        $r->select((int) ($cfg['database'] ?? 0));

        return $r;
    }

    public static function test(array $cfg): array
    {
        $r = self::redis($cfg);
        // NodeBB keeps a `global` hash (nextUid/nextCid/…) and a categories:cid zset.
        if (! $r->exists('global') && ! $r->exists('categories:cid')) {
            throw new \RuntimeException("This doesn't look like a NodeBB Redis store (no “global” or “categories:cid” key). Check the host, port and database number.");
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $r->zCard('users:joindate'),
            'categories' => (int) $r->zCard('categories:cid'),
            'topics' => (int) $r->zCard('topics:tid'),
            'posts' => (int) $r->zCard('posts:pid'),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $r = self::redis($cfg);
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicStat = [];

        $progress('Importing categories…', 5, $summary);
        foreach ($r->zRange('categories:cid', 0, -1) as $cid) {
            $c = self::hash($r, "category:{$cid}");
            if (! $c || ($c['disabled'] ?? '0') === '1') {
                continue;
            }
            $name = trim((string) ($c['name'] ?? '')) ?: ('Category '.$cid);
            $slug = Src::catSlug($name, (int) $cid);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$cid] = $ex;

                continue;
            }
            $catMap[$cid] = DB::table('categories')->insertGetId([
                'name' => $name, 'slug' => $slug,
                'description' => Str::limit(strip_tags((string) ($c['description'] ?? '')), 200) ?: null,
                'color' => Src::color($c['bgColor'] ?? null),
                'position' => (int) ($c['order'] ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        foreach (array_chunk($r->zRange('users:joindate', 0, -1), 200) as $uids) {
            foreach ($uids as $uid) {
                try {
                    $u = self::hash($r, "user:{$uid}");
                    $email = trim((string) ($u['email'] ?? ''));
                    if (! $u || $email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                        $userMap[$uid] = $ex;

                        continue;
                    }
                    $userMap[$uid] = DB::table('users')->insertGetId([
                        'name' => ($u['username'] ?? '') ?: ('user'.$uid),
                        'email' => $email,
                        'password' => Src::password(null), // sha512-wrapped bcrypt → not portable; reset
                        'bio' => isset($u['aboutme']) && $u['aboutme'] !== '' ? Str::limit(strip_tags((string) $u['aboutme']), 500) : null,
                        'is_admin' => false,
                        'created_at' => self::ts($u['joindate'] ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        }

        $progress('Importing topics & posts…', 45, $summary);
        foreach ($r->zRange('topics:tid', 0, -1) as $tid) {
            try {
                $t = self::hash($r, "topic:{$tid}");
                if (! $t || ($t['deleted'] ?? '0') === '1') {
                    continue;
                }
                $title = trim((string) ($t['title'] ?? '')) ?: 'Untitled';
                $slug = (Str::slug($title) ?: 'topic').'-'.$tid;
                if (DB::table('topics')->where('slug', $slug)->exists()) {
                    continue;
                }
                $created = self::ts($t['timestamp'] ?? null);
                $convTid = DB::table('topics')->insertGetId([
                    'title' => $title, 'slug' => $slug,
                    'user_id' => $userMap[$t['uid'] ?? null] ?? null, 'category_id' => $catMap[$t['cid'] ?? null] ?? null,
                    'is_pinned' => ($t['pinned'] ?? '0') === '1',
                    'is_locked' => ($t['locked'] ?? '0') === '1',
                    'view_count' => (int) ($t['viewcount'] ?? 0),
                    'created_at' => $created,
                    'updated_at' => self::ts($t['lastposttime'] ?? $t['timestamp'] ?? null),
                    'last_post_at' => self::ts($t['lastposttime'] ?? $t['timestamp'] ?? null),
                ]);
                $summary['topics']++;
                $topicStat[$convTid] = ['n' => 0, 'last' => $created];

                // First post = topic.mainPid, then replies from tid:{tid}:posts.
                $mainPid = $t['mainPid'] ?? null;
                $pids = $r->zRange("tid:{$tid}:posts", 0, -1);
                if ($mainPid !== null) {
                    array_unshift($pids, $mainPid);
                }
                $seen = [];
                foreach ($pids as $pid) {
                    if (isset($seen[$pid])) {
                        continue;
                    }
                    $seen[$pid] = true;
                    $post = self::hash($r, "post:{$pid}");
                    if (! $post || ($post['deleted'] ?? '0') === '1') {
                        continue;
                    }
                    $pCreated = self::ts($post['timestamp'] ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $convTid, 'user_id' => $userMap[$post['uid'] ?? null] ?? null,
                        'body_html' => self::markdown($post['content'] ?? '') ?: '<p></p>',
                        'body_json' => null, 'is_first' => ((string) $pid === (string) $mainPid),
                        'created_at' => $pCreated, 'updated_at' => $pCreated,
                    ]);
                    $st = $topicStat[$convTid];
                    $st['n']++;
                    if ($pCreated->gt($st['last'])) {
                        $st['last'] = $pCreated;
                    }
                    $topicStat[$convTid] = $st;
                    $summary['posts']++;
                }
            } catch (\Throwable) {
                $summary['skipped']++;
            }
        }

        $progress('Finishing up…', 95, $summary);
        foreach ($topicStat as $tid => $st) {
            DB::table('topics')->where('id', $tid)->update(['reply_count' => max(0, $st['n'] - 1), 'last_post_at' => $st['last']]);
        }
        $progress('Import complete.', 100, $summary);

        return $summary;
    }

    /** phpredis returns hgetall as an assoc array; normalise to one. */
    private static function hash($r, string $key): array
    {
        $h = $r->hGetAll($key);

        return is_array($h) ? $h : [];
    }

    /** NodeBB timestamps are epoch milliseconds. */
    private static function ts($ms)
    {
        if ($ms === null || $ms === '') {
            return now();
        }

        return Src::ts((int) ((int) $ms / 1000));
    }

    private static function markdown(string $md): string
    {
        if (trim($md) === '') {
            return '';
        }
        try {
            $conv = new \League\CommonMark\CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);

            return Src::sanitizeHtml((string) $conv->convert($md));
        } catch (\Throwable) {
            return Src::sanitizeHtml('<p>'.nl2br(htmlspecialchars($md, ENT_QUOTES), false).'</p>');
        }
    }
}
