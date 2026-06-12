<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a community from a Flarum (1.x / 2.x) database into Convoro.
 *
 * Connects to the source DB read-only, then maps:
 *   tags  → categories      (a discussion's first tag becomes its category)
 *   users → users           (bcrypt hash copied, so existing passwords work)
 *   discussions → topics
 *   posts (comments) → posts (s9e TextFormatter XML → safe HTML)
 *
 * Dependency-free (no Flarum/s9e code) so it runs anywhere. Idempotent enough
 * to re-run: members dedupe by email, categories by slug, topics by slug
 * (slug carries the source id), and posts are only created for newly-made topics.
 */
class FlarumImporter
{
    private const CONN = 'flarum_import';

    /** Base URL used to absolutize relative media in post content. */
    private static string $base = '';

    /** Build a runtime connection to the Flarum database. */
    public static function connect(array $cfg)
    {
        // SQLite path — importer fixtures/tests (and the rare SQLite source).
        if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
            config(['database.connections.'.self::CONN => [
                'driver' => 'sqlite', 'database' => $cfg['database'] ?? '', 'prefix' => '', 'foreign_key_constraints' => false,
            ]]);
            DB::purge(self::CONN);

            return DB::connection(self::CONN);
        }

        config(['database.connections.'.self::CONN => [
            'driver' => 'mysql',
            'host' => $cfg['host'] ?: '127.0.0.1',
            'port' => (int) ($cfg['port'] ?: 3306),
            'database' => $cfg['database'] ?? '',
            'username' => $cfg['username'] ?? '',
            'password' => $cfg['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [\PDO::ATTR_TIMEOUT => 8],
        ]]);
        DB::purge(self::CONN);

        return DB::connection(self::CONN);
    }

    /** Validate the connection and return source row counts. */
    public static function test(array $cfg): array
    {
        $conn = self::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? ''));

        // Must look like Flarum.
        $sb = $conn->getSchemaBuilder();
        foreach (['users', 'discussions', 'posts'] as $req) {
            if (! $sb->hasTable($p.$req)) {
                throw new \RuntimeException("This doesn't look like a Flarum database (missing “{$p}{$req}” table). Check the name and table prefix.");
            }
        }

        return [
            'ok' => true,
            'counts' => [
                'users' => (int) $conn->table($p.'users')->count(),
                'tags' => $sb->hasTable($p.'tags') ? (int) $conn->table($p.'tags')->count() : 0,
                'discussions' => (int) $conn->table($p.'discussions')->where('is_private', false)->count(),
                'posts' => (int) $conn->table($p.'posts')->where('type', 'comment')->count(),
            ],
        ];
    }

    /**
     * Run the full import. $progress is called as ($message, $percent, $summary).
     *
     * @return array<string,int> summary counts
     */
    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = self::connect($cfg);
        $p = trim((string) ($cfg['prefix'] ?? ''));
        $sb = $conn->getSchemaBuilder();
        $now = now();

        $summary = ['categories' => 0, 'tags' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'reactions' => 0, 'skipped' => 0];
        $catMap = [];      // flarum tag id  => convoro category id
        $tagMap = [];      // flarum tag id  => convoro tag id (secondary tags)
        $userMap = [];     // flarum user id => convoro user id
        $topicMap = [];    // flarum discussion id => convoro topic id
        $postMap = [];     // flarum post id => convoro post id (for likes → reactions)
        $newTopics = [];   // discussion ids we created this run (others: skip posts)
        $topicStat = [];   // convoro topic id => ['n'=>postCount, 'last'=>timestamp]
        $flarumUrl = rtrim((string) ($cfg['flarum_url'] ?? ''), '/');

        // ── 1. Tags → primary tags become categories, secondary tags become tags ──
        $progress('Importing categories & tags…', 5, $summary);
        if (($opts['tags'] ?? true) && $sb->hasTable($p.'tags')) {
            // Flarum convention: primary tags carry a non-null position; secondary
            // tags have position = NULL. If a board has no primaries, treat all as categories.
            $hasPrimary = $conn->table($p.'tags')->whereNotNull('position')->exists();
            foreach ($conn->table($p.'tags')->orderBy('position')->orderBy('id')->get() as $tag) {
                $isPrimary = ! $hasPrimary || $tag->position !== null;
                $slug = Str::slug($tag->slug ?: $tag->name) ?: 'tag-'.$tag->id;
                if ($isPrimary) {
                    if ($existing = DB::table('categories')->where('slug', $slug)->value('id')) {
                        $catMap[$tag->id] = $existing;
                    } else {
                        $catMap[$tag->id] = DB::table('categories')->insertGetId([
                            'name' => $tag->name ?: 'Category',
                            'slug' => $slug,
                            'description' => $tag->description ?? null,
                            'color' => self::color($tag->color ?? null),
                            'position' => (int) ($tag->position ?? 0),
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $summary['categories']++;
                    }
                } else {
                    if ($existing = DB::table('tags')->where('slug', $slug)->value('id')) {
                        $tagMap[$tag->id] = $existing;
                    } else {
                        $tagMap[$tag->id] = DB::table('tags')->insertGetId([
                            'name' => $tag->name ?: 'Tag',
                            'slug' => $slug,
                            'color' => self::color($tag->color ?? null),
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $summary['tags']++;
                    }
                }
            }
        }

        // All tags per discussion, in order.
        $discTags = [];
        if ($sb->hasTable($p.'discussion_tag')) {
            foreach ($conn->table($p.'discussion_tag')->orderBy('tag_id')->get() as $dt) {
                $discTags[$dt->discussion_id][] = $dt->tag_id;
            }
        }

        // ── 2. Users ──────────────────────────────────────────────────────
        $progress('Importing members…', 20, $summary);
        $joinedCol = $sb->hasColumn($p.'users', 'joined_at') ? 'joined_at' : ($sb->hasColumn($p.'users', 'created_at') ? 'created_at' : null);
        $hasAvatar = $sb->hasColumn($p.'users', 'avatar_url');
        $hasBio = $sb->hasColumn($p.'users', 'bio');

        $conn->table($p.'users')->orderBy('id')->chunk(200, function ($rows) use (&$userMap, &$summary, $joinedCol, $hasAvatar, $hasBio, $flarumUrl, $now) {
            foreach ($rows as $u) {
                try {
                    $email = trim((string) ($u->email ?? ''));
                    if ($email === '') {
                        $summary['skipped']++;

                        continue;
                    }
                    $existing = DB::table('users')->where('email', $email)->value('id');
                    if ($existing) {
                        $userMap[$u->id] = $existing;

                        continue;
                    }
                    $avatar = null;
                    if ($hasAvatar && $u->avatar_url) {
                        $avatar = preg_match('#^https?://#i', $u->avatar_url)
                            ? $u->avatar_url
                            : ($flarumUrl !== '' ? $flarumUrl.'/assets/avatars/'.$u->avatar_url : null);
                    }
                    $userMap[$u->id] = DB::table('users')->insertGetId([
                        'name' => $u->username ?? ('user'.$u->id),
                        'email' => $email,
                        'password' => $u->password ?: bcrypt(Str::random(24)),
                        'bio' => $hasBio ? ($u->bio ?? null) : null,
                        'avatar_path' => $avatar,
                        'created_at' => $joinedCol ? ($u->$joinedCol ?? $now) : $now,
                        'updated_at' => $now,
                    ]);
                    $summary['users']++;
                } catch (\Throwable) {
                    $summary['skipped']++;
                }
            }
        });

        // ── 3. Discussions → topics (+ secondary tags) ──────────────────────
        $progress('Importing topics…', 40, $summary);
        $conn->table($p.'discussions')->where('is_private', false)->orderBy('id')
            ->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $tagMap, $userMap, $discTags, $now) {
                foreach ($rows as $d) {
                    try {
                        $slug = (Str::slug($d->slug ?: $d->title) ?: 'topic').'-'.$d->id;
                        $existing = DB::table('topics')->where('slug', $slug)->value('id');
                        if ($existing) {
                            $topicMap[$d->id] = $existing;

                            continue;
                        }
                        // Split this discussion's tags into a category + secondary tags.
                        $categoryId = null;
                        $secondary = [];
                        foreach ($discTags[$d->id] ?? [] as $tid) {
                            if ($categoryId === null && isset($catMap[$tid])) {
                                $categoryId = $catMap[$tid];
                            } elseif (isset($tagMap[$tid])) {
                                $secondary[] = $tagMap[$tid];
                            }
                        }
                        $topicId = DB::table('topics')->insertGetId([
                            'title' => $d->title ?: 'Untitled',
                            'slug' => $slug,
                            'user_id' => $userMap[$d->user_id] ?? null,
                            'category_id' => $categoryId,
                            'is_pinned' => (bool) ($d->is_sticky ?? false),
                            'is_locked' => (bool) ($d->is_locked ?? false),
                            'created_at' => $d->created_at ?? $now,
                            'updated_at' => $d->last_posted_at ?? $d->created_at ?? $now,
                            'last_post_at' => $d->last_posted_at ?? $d->created_at ?? $now,
                        ]);
                        foreach (array_unique($secondary) as $tagId) {
                            DB::table('topic_tag')->insertOrIgnore(['topic_id' => $topicId, 'tag_id' => $tagId]);
                        }
                        $topicMap[$d->id] = $topicId;
                        $newTopics[$d->id] = true;
                        $summary['topics']++;
                    } catch (\Throwable) {
                        $summary['skipped']++;
                    }
                }
            });

        // ── 4. Posts → posts (tracking ids for likes) ───────────────────────
        $progress('Importing posts…', 65, $summary);
        $seenFirst = [];
        $conn->table($p.'posts')->where('type', 'comment')->orderBy('discussion_id')->orderBy('number')->orderBy('id')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, &$postMap, $topicMap, $newTopics, $userMap, $flarumUrl, $now) {
                foreach ($rows as $post) {
                    if (empty($newTopics[$post->discussion_id])) {
                        continue; // topic already existed → don't duplicate posts
                    }
                    $topicId = $topicMap[$post->discussion_id] ?? null;
                    if (! $topicId || ($post->is_private ?? false)) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->discussion_id]);
                    $seenFirst[$post->discussion_id] = true;
                    $created = $post->created_at ?? $now;
                    $postMap[$post->id] = DB::table('posts')->insertGetId([
                        'topic_id' => $topicId,
                        'user_id' => $userMap[$post->user_id] ?? null,
                        'body_html' => self::s9eToHtml($post->content ?? '', $flarumUrl),
                        'body_json' => null,
                        'is_first' => $isFirst,
                        'edited_at' => $post->edited_at ?? null,
                        'created_at' => $created,
                        'updated_at' => $created,
                    ]);
                    $stat = $topicStat[$topicId] ?? ['n' => 0, 'last' => null];
                    $stat['n']++;
                    if ($stat['last'] === null || $created > $stat['last']) {
                        $stat['last'] = $created;
                    }
                    $topicStat[$topicId] = $stat;
                    $summary['posts']++;
                }
            });

        // ── 5. Likes → reactions (flarum/likes) ──────────────────────────────
        if ($sb->hasTable($p.'post_likes')) {
            $progress('Importing reactions…', 85, $summary);
            $conn->table($p.'post_likes')->orderBy('post_id')
                ->chunk(500, function ($rows) use (&$summary, $postMap, $userMap, $now) {
                    $insert = [];
                    foreach ($rows as $like) {
                        $pid = $postMap[$like->post_id] ?? null;
                        $uid = $userMap[$like->user_id] ?? null;
                        if (! $pid || ! $uid) {
                            continue;
                        }
                        $insert[] = ['post_id' => $pid, 'user_id' => $uid, 'emoji' => '👍', 'created_at' => $now, 'updated_at' => $now];
                        $summary['reactions']++;
                    }
                    if ($insert) {
                        DB::table('reactions')->insertOrIgnore($insert);
                    }
                });
        }

        // ── 6. Recompute topic counters ──────────────────────────────────────
        $progress('Finishing up…', 95, $summary);
        foreach ($topicStat as $topicId => $stat) {
            DB::table('topics')->where('id', $topicId)->update([
                'reply_count' => max(0, $stat['n'] - 1),
                'last_post_at' => $stat['last'],
            ]);
        }

        $progress('Import complete.', 100, $summary);

        return $summary;
    }

    private static function color(?string $c): string
    {
        $c = trim((string) $c);

        return preg_match('/^#?[0-9a-fA-F]{6}$/', $c) ? (str_starts_with($c, '#') ? $c : '#'.$c) : '#5b5bd6';
    }

    // ── s9e TextFormatter XML → safe HTML ────────────────────────────────────

    /** Convert Flarum's stored post content (s9e XML) to sanitized HTML. */
    public static function s9eToHtml(?string $content, string $base = ''): string
    {
        self::$base = rtrim($base, '/');
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $ok = $dom->loadXML($content, LIBXML_NONET);
        libxml_clear_errors();
        if (! $ok || ! $dom->documentElement) {
            return self::plainToHtml($content);
        }

        $root = $dom->documentElement;
        if (strtoupper($root->nodeName) === 'T') { // plain text (no rich formatting)
            return self::plainToHtml($root->textContent);
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= self::node($child);
        }
        $html = trim($html);

        return $html !== '' ? $html : self::plainToHtml($root->textContent);
    }

    private static function node(\DOMNode $n): string
    {
        if ($n->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($n->nodeValue, ENT_QUOTES, 'UTF-8');
        }
        if ($n->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }
        /** @var \DOMElement $n */
        // s9e markup tokens (the literal markdown chars / ignored whitespace) are
        // lowercase s/e/i — drop them before case-folding (so they don't collide
        // with uppercase formatting tags like <I>).
        $raw = $n->nodeName;
        if ($raw === 's' || $raw === 'e' || $raw === 'i') {
            return '';
        }

        $inner = '';
        foreach ($n->childNodes as $c) {
            $inner .= self::node($c);
        }

        // Formatting tags vary in case across s9e plugins — normalise.
        $name = strtoupper($raw);

        switch ($name) {
            case 'P': return "<p>{$inner}</p>";
            case 'STRONG': case 'B': return "<strong>{$inner}</strong>";
            case 'EM': case 'I': return "<em>{$inner}</em>";
            case 'U': return "<u>{$inner}</u>";
            case 'DEL': case 'STRIKE': case 'S': return "<del>{$inner}</del>";
            case 'SUB': return "<sub>{$inner}</sub>";
            case 'SUP': return "<sup>{$inner}</sup>";
            case 'H1': case 'H2': case 'H3': case 'H4': case 'H5': case 'H6':
                return '<'.strtolower($name).'>'.$inner.'</'.strtolower($name).'>';
            case 'QUOTE': case 'BLOCKQUOTE': return "<blockquote>{$inner}</blockquote>";
            case 'LIST':
                $type = strtolower((string) $n->getAttribute('type'));
                $tag = ($type === 'decimal' || $type === '1' || is_numeric($type)) ? 'ol' : 'ul';

                return "<{$tag}>{$inner}</{$tag}>";
            case 'LI': return "<li>{$inner}</li>";
            case 'CODE': return "<pre><code>{$inner}</code></pre>";
            case 'C': return "<code>{$inner}</code>";
            case 'BR': return '<br>';
            case 'HR': return '<hr>';
            case 'URL': case 'LINK':
                $href = self::safeUrl($n->getAttribute('url') ?: $n->getAttribute('href'));

                return $href ? '<a href="'.$href.'" rel="nofollow noopener" target="_blank">'.$inner.'</a>' : $inner;
            case 'IMG':
                $src = self::safeUrl($n->getAttribute('src'));
                $alt = htmlspecialchars((string) ($n->getAttribute('alt') ?: $n->getAttribute('title')), ENT_QUOTES, 'UTF-8');

                return $src ? '<img src="'.$src.'" alt="'.$alt.'">' : '';
            case 'EMOJI': case 'EMOTICON':
                return $inner !== '' ? $inner : htmlspecialchars((string) $n->textContent, ENT_QUOTES, 'UTF-8');
            default:
                // MENTION / USERMENTION / unknown wrappers → keep their text content.
                return $inner;
        }
    }

    private static function plainToHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $blocks = preg_split('/\n{2,}/', $text);
        $html = '';
        foreach ($blocks as $block) {
            $esc = htmlspecialchars(trim($block), ENT_QUOTES, 'UTF-8');
            $esc = nl2br($esc, false);
            if ($esc !== '') {
                $html .= "<p>{$esc}</p>";
            }
        }

        return $html;
    }

    private static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^(https?:)?//#i', $url) && ! str_starts_with($url, '/')) {
            return null; // block javascript:, data:, etc.
        }
        // Absolutize root-relative media against the old forum so it still loads.
        if (str_starts_with($url, '/') && self::$base !== '') {
            $url = self::$base.$url;
        }

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
