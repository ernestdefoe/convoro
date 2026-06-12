<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * vBulletin 5 / 6 (node architecture) → Convoro.
 *
 * vB5/6 stores everything as a "node" in one tree, typed by contenttype.class:
 *   class 'Channel' node            → a forum/category
 *   'Text' node whose parent is a    → a thread (its text.rawtext is the first post)
 *      Channel
 *   'Text' node whose parent is a    → a reply
 *      thread node
 *
 * So categories come from Channel nodes, and a thread's posts are its own Text row
 * (first post) plus the Text rows of its child nodes. Bodies live in `text.rawtext`
 * as BBCode (htmlstate flags raw-HTML posts). vB password tokens aren't portable, so
 * members reset on first login. Delegated to from VbulletinImporter when a `node`
 * table is present.
 */
class Vbulletin5Importer
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? ''));
        $sb = $conn->getSchemaBuilder();
        foreach (['node', 'text', 'contenttype', 'user'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like a vBulletin 5 database (missing “{$p}{$req}”).");
            }
        }

        [$channelTypeIds, $textTypeIds] = self::typeIds($conn, $p);
        $channelIds = self::channelNodeIds($conn, $p, $channelTypeIds);

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table($p.'user')->count(),
            'categories' => count($channelIds),
            'topics' => $textTypeIds && $channelIds
                ? (int) $conn->table($p.'node')->whereIn('contenttypeid', $textTypeIds)->whereIn('parentid', $channelIds)->count()
                : 0,
            'posts' => $textTypeIds ? (int) $conn->table($p.'node')->whereIn('contenttypeid', $textTypeIds)->count() : 0,
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? ''));
        $now = now();
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $topicStat = [];

        [$channelTypeIds, $textTypeIds] = self::typeIds($conn, $p);
        if (! $textTypeIds) {
            throw new \RuntimeException('Could not find the vBulletin “Text” content type — is this a forum install?');
        }

        $progress('Importing channels…', 5, $summary);
        $channelIds = [];
        if ($channelTypeIds) {
            foreach ($conn->table($p.'node')->whereIn('contenttypeid', $channelTypeIds)->orderBy('displayorder')->orderBy('nodeid')->get() as $c) {
                $channelIds[] = (int) $c->nodeid;
                $name = trim((string) ($c->title ?? '')) ?: ('Channel '.$c->nodeid);
                $slug = Src::catSlug($name, (int) $c->nodeid);
                if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                    $catMap[$c->nodeid] = $ex;

                    continue;
                }
                $catMap[$c->nodeid] = DB::table('categories')->insertGetId([
                    'name' => $name, 'slug' => $slug,
                    'description' => Str::limit(strip_tags((string) ($c->description ?? '')), 200) ?: null,
                    'color' => '#5b5bd6', 'position' => (int) ($c->displayorder ?? 0),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $summary['categories']++;
            }
        }

        $progress('Importing members…', 20, $summary);
        $hasDisplayName = $conn->getSchemaBuilder()->hasColumn($p.'user', 'displayname');
        $conn->table($p.'user')->orderBy('userid')->chunk(200, function ($rows) use (&$userMap, &$summary, $p, $hasDisplayName, $now) {
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
                    $name = trim((string) (($hasDisplayName ? $u->displayname : null) ?: $u->username ?? ''));
                    $userMap[$u->userid] = DB::table('users')->insertGetId([
                        'name' => $name !== '' ? $name : ('user'.$u->userid),
                        'email' => $email,
                        'password' => Src::password(null), // vB5 token/scheme → not portable; members reset
                        'is_admin' => false,
                        'created_at' => Src::ts($u->joindate ?? null), 'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        // Pass 1 — thread starters: Text nodes whose parent is a Channel. Their own
        // text row is the topic's first post.
        $progress('Importing topics…', 40, $summary);
        if ($channelIds) {
            $conn->table($p.'node')
                ->join($p.'text', $p.'text.nodeid', '=', $p.'node.nodeid')
                ->whereIn($p.'node.contenttypeid', $textTypeIds)
                ->whereIn($p.'node.parentid', $channelIds)
                ->where($p.'node.approved', 1)
                ->orderBy($p.'node.nodeid')
                ->select($p.'node.*', $p.'text.rawtext', $p.'text.htmlstate')
                ->chunk(200, function ($rows) use (&$topicMap, &$topicStat, &$summary, $catMap, $userMap) {
                    foreach ($rows as $n) {
                        try {
                            $title = trim((string) ($n->title ?? '')) ?: 'Untitled';
                            $slug = (Str::slug($title) ?: 'topic').'-'.$n->nodeid;
                            if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                                $topicMap[$n->nodeid] = $ex;

                                continue;
                            }
                            $created = Src::ts($n->publishdate ?? $n->created ?? null);
                            $tid = DB::table('topics')->insertGetId([
                                'title' => $title, 'slug' => $slug,
                                'user_id' => $userMap[$n->userid] ?? null, 'category_id' => $catMap[$n->parentid] ?? null,
                                'is_pinned' => (bool) ($n->sticky ?? false),
                                'is_locked' => (int) ($n->open ?? 1) === 0,
                                'view_count' => 0,
                                'created_at' => $created, 'updated_at' => $created, 'last_post_at' => $created,
                            ]);
                            $topicMap[$n->nodeid] = $tid;
                            // The starter node's own text = the first post.
                            DB::table('posts')->insert([
                                'topic_id' => $tid, 'user_id' => $userMap[$n->userid] ?? null,
                                'body_html' => self::body($n->rawtext ?? '', $n->htmlstate ?? '') ?: '<p></p>',
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
        }

        // Pass 2 — replies: Text nodes whose parent is a thread starter we just mapped.
        $progress('Importing posts…', 70, $summary);
        $conn->table($p.'node')
            ->join($p.'text', $p.'text.nodeid', '=', $p.'node.nodeid')
            ->whereIn($p.'node.contenttypeid', $textTypeIds)
            ->when($channelIds, fn ($q) => $q->whereNotIn($p.'node.parentid', $channelIds))
            ->where($p.'node.approved', 1)
            ->orderBy($p.'node.parentid')->orderBy($p.'node.publishdate')->orderBy($p.'node.nodeid')
            ->select($p.'node.*', $p.'text.rawtext', $p.'text.htmlstate')
            ->chunk(300, function ($rows) use (&$summary, &$topicStat, $topicMap, $userMap) {
                foreach ($rows as $n) {
                    $tid = $topicMap[$n->parentid] ?? null;
                    if (! $tid) {
                        continue; // not a direct reply to an imported thread
                    }
                    $created = Src::ts($n->publishdate ?? $n->created ?? null);
                    DB::table('posts')->insert([
                        'topic_id' => $tid, 'user_id' => $userMap[$n->userid] ?? null,
                        'body_html' => self::body($n->rawtext ?? '', $n->htmlstate ?? '') ?: '<p></p>',
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

    /** @return array{0: int[], 1: int[]} [channelTypeIds, textTypeIds] */
    private static function typeIds($conn, string $p): array
    {
        $channel = $text = [];
        foreach ($conn->table($p.'contenttype')->get(['contenttypeid', 'class']) as $ct) {
            $class = (string) $ct->class;
            if ($class === 'Channel') {
                $channel[] = (int) $ct->contenttypeid;
            } elseif ($class === 'Text') {
                $text[] = (int) $ct->contenttypeid;
            }
        }

        return [$channel, $text];
    }

    /** @return int[] */
    private static function channelNodeIds($conn, string $p, array $channelTypeIds): array
    {
        if (! $channelTypeIds) {
            return [];
        }

        return $conn->table($p.'node')->whereIn('contenttypeid', $channelTypeIds)->pluck('nodeid')->map(fn ($v) => (int) $v)->all();
    }

    /** vB5 bodies are BBCode in text.rawtext; htmlstate='on' means raw HTML instead. */
    private static function body(?string $raw, ?string $htmlstate): string
    {
        $raw = (string) $raw;
        if ($htmlstate === 'on') {
            return Src::sanitizeHtml($raw);
        }

        return Bbcode::toHtml($raw);
    }
}
