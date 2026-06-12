<?php

namespace App\Support\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invision Community 4.x / 5.x (IP.Board) → Convoro.
 *   forums_forums → categories (titles live in core_sys_lang_words: forums_forum_{id})
 *   core_members  → users (bcrypt copies straight; legacy IP.Board md5 → members reset)
 *   forums_topics → topics (state=closed → locked, pinned → pinned)
 *   forums_posts  → posts (IPS stores HTML — quotes/mentions remapped, sanitised)
 *   core_tags     → tags  (tag_meta_app=forums)
 *   core_reputation_index → reactions (app=forums, type=pid)
 *
 * IPS post content is HTML (not BBCode), so InvisionImporter::ipsHtml() cleans the
 * IPS-specific markup (ipsQuote blockquotes, data-mentionid links, emoticon imgs).
 */
class InvisionImporter
{
    public static function test(array $cfg): array
    {
        $conn = Src::connect($cfg);
        $sb = $conn->getSchemaBuilder();
        foreach (['core_members', 'forums_forums', 'forums_topics', 'forums_posts'] as $req) {
            if (! $sb->hasTable($req)) {
                throw new \RuntimeException("This doesn't look like an Invision Community database (missing “{$req}”).");
            }
        }

        return ['ok' => true, 'counts' => [
            'users' => (int) $conn->table('core_members')->where('member_id', '>', 0)->count(),
            'categories' => (int) $conn->table('forums_forums')->count(),
            'topics' => (int) $conn->table('forums_topics')->where('approved', 1)->count(),
            'posts' => (int) $conn->table('forums_posts')->where('queued', 0)->count(),
        ]];
    }

    public static function run(array $cfg, array $opts, callable $progress): array
    {
        @set_time_limit(0);
        $conn = Src::connect($cfg);
        $sb = $conn->getSchemaBuilder();
        $now = now();
        $base = rtrim((string) ($cfg['source_url'] ?? ''), '/');
        $summary = ['categories' => 0, 'users' => 0, 'topics' => 0, 'posts' => 0, 'tags' => 0, 'reactions' => 0, 'skipped' => 0];
        $catMap = $userMap = $topicMap = $postMap = $newTopics = $topicStat = [];

        // Forum + group names are translatable strings in core_sys_lang_words, not on
        // their own tables — pull the default-language word for each forums_forum_{id}.
        $forumNames = $forumDescs = [];
        if ($sb->hasTable('core_sys_lang_words')) {
            $words = $conn->table('core_sys_lang_words')
                ->where('word_app', 'forums')
                ->where(function ($q) {
                    $q->where('word_key', 'like', 'forums_forum_%');
                })
                ->orderBy('lang_id')
                ->get(['word_key', 'word_default', 'word_custom']);
            foreach ($words as $w) {
                $val = ($w->word_custom !== null && $w->word_custom !== '') ? $w->word_custom : $w->word_default;
                if ($val === null || $val === '') {
                    continue;
                }
                if (preg_match('/^forums_forum_(\d+)$/', $w->word_key, $m)) {
                    $forumNames[(int) $m[1]] ??= $val;
                } elseif (preg_match('/^forums_forum_(\d+)_desc$/', $w->word_key, $m)) {
                    $forumDescs[(int) $m[1]] ??= $val;
                }
            }
        }

        $progress('Importing categories…', 5, $summary);
        foreach ($conn->table('forums_forums')->orderBy('position')->orderBy('id')->get() as $f) {
            // Skip redirect-only forums (they have no content of their own).
            if ((int) ($f->redirect_on ?? 0) === 1 && trim((string) ($f->redirect_url ?? '')) !== '') {
                continue;
            }
            $name = $forumNames[(int) $f->id] ?? ($f->name_seo ? Str::headline((string) $f->name_seo) : 'Forum '.$f->id);
            $slug = Src::catSlug($name, (int) $f->id);
            if ($ex = DB::table('categories')->where('slug', $slug)->value('id')) {
                $catMap[$f->id] = $ex;

                continue;
            }
            $desc = $forumDescs[(int) $f->id] ?? null;
            $catMap[$f->id] = DB::table('categories')->insertGetId([
                'name' => $name, 'slug' => $slug,
                'description' => $desc ? Str::limit(strip_tags($desc), 200) : null,
                'color' => Src::color($f->feature_color ?? null),
                'position' => (int) ($f->position ?? 0), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $summary['categories']++;
        }

        $progress('Importing members…', 20, $summary);
        $conn->table('core_members')->where('member_id', '>', 0)->orderBy('member_id')
            ->chunk(200, function ($rows) use (&$userMap, &$summary, $base, $now) {
                foreach ($rows as $u) {
                    try {
                        $email = trim((string) ($u->email ?? ''));
                        if ($email === '') {
                            $summary['skipped']++;

                            continue;
                        }
                        if ($ex = DB::table('users')->where('email', $email)->value('id')) {
                            $userMap[$u->member_id] = $ex;

                            continue;
                        }
                        // pp_photo_type=custom → pp_main_photo is an uploads-relative path.
                        $avatar = null;
                        if ($base !== '' && ($u->pp_photo_type ?? '') === 'custom' && trim((string) ($u->pp_main_photo ?? '')) !== '') {
                            $avatar = $base.'/uploads/'.ltrim((string) $u->pp_main_photo, '/');
                        }
                        $bio = trim((string) ($u->signature ?? ''));
                        $userMap[$u->member_id] = DB::table('users')->insertGetId([
                            'name' => $u->name ?: ('member'.$u->member_id),
                            'email' => $email,
                            'password' => Src::password($u->members_pass_hash ?? null),
                            'bio' => $bio !== '' ? Str::limit(strip_tags($bio), 500) : null,
                            'avatar_path' => $avatar,
                            'is_admin' => false,
                            'created_at' => Src::ts($u->joined ?? null), 'updated_at' => $now,
                        ]);
                        $summary['users']++;
                    } catch (\Throwable) {
                        $summary['skipped']++;
                    }
                }
            });

        $progress('Importing topics…', 40, $summary);
        $conn->table('forums_topics')->where('approved', 1)->orderBy('tid')
            ->chunk(200, function ($rows) use (&$topicMap, &$newTopics, &$summary, $catMap, $userMap) {
                foreach ($rows as $t) {
                    try {
                        // Skip moved/redirect topics (state=link) — they're pointers, not content.
                        if (($t->state ?? 'open') === 'link' || trim((string) ($t->moved_to ?? '')) !== '') {
                            continue;
                        }
                        $slug = (Str::slug($t->title ?: 'topic') ?: 'topic').'-'.$t->tid;
                        if ($ex = DB::table('topics')->where('slug', $slug)->value('id')) {
                            $topicMap[$t->tid] = $ex;

                            continue;
                        }
                        $topicMap[$t->tid] = DB::table('topics')->insertGetId([
                            'title' => $t->title ?: 'Untitled', 'slug' => $slug,
                            'user_id' => $userMap[$t->starter_id] ?? null, 'category_id' => $catMap[$t->forum_id] ?? null,
                            'is_pinned' => (bool) ($t->pinned ?? false),
                            'is_locked' => ($t->state ?? 'open') === 'closed',
                            'view_count' => (int) ($t->views ?? 0),
                            'created_at' => Src::ts($t->start_date ?? null),
                            'updated_at' => Src::ts($t->last_post ?? $t->start_date ?? null),
                            'last_post_at' => Src::ts($t->last_post ?? $t->start_date ?? null),
                        ]);
                        $newTopics[$t->tid] = true;
                        $summary['topics']++;
                    } catch (\Throwable) {
                        $summary['skipped']++;
                    }
                }
            });

        $progress('Importing posts…', 60, $summary);
        $seenFirst = [];
        $conn->table('forums_posts')->where('queued', 0)->orderBy('topic_id')->orderBy('post_date')->orderBy('pid')
            ->chunk(300, function ($rows) use (&$summary, &$seenFirst, &$topicStat, &$postMap, $topicMap, $newTopics, $userMap) {
                foreach ($rows as $post) {
                    // pdelete_time > 0 marks a soft-deleted post.
                    if ((int) ($post->pdelete_time ?? 0) > 0 || empty($newTopics[$post->topic_id])) {
                        continue;
                    }
                    $tid = $topicMap[$post->topic_id] ?? null;
                    if (! $tid) {
                        continue;
                    }
                    $isFirst = empty($seenFirst[$post->topic_id]);
                    $seenFirst[$post->topic_id] = true;
                    $created = Src::ts($post->post_date ?? null);
                    $postMap[$post->pid] = DB::table('posts')->insertGetId([
                        'topic_id' => $tid, 'user_id' => $userMap[$post->author_id] ?? null,
                        'body_html' => self::ipsHtml($post->post ?? '') ?: '<p></p>',
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

        // Tags — core_tags rows for forum topics (tag_meta_app=forums, tag_meta_id=tid).
        if ($sb->hasTable('core_tags')) {
            $progress('Importing tags…', 80, $summary);
            $tagIdByName = [];
            $conn->table('core_tags')->where('tag_meta_app', 'forums')->orderBy('tag_id')
                ->chunk(500, function ($rows) use (&$summary, &$tagIdByName, $topicMap, $now) {
                    foreach ($rows as $tag) {
                        $text = trim((string) ($tag->tag_text ?? ''));
                        $tid = $topicMap[$tag->tag_meta_id] ?? null;
                        if ($text === '' || ! $tid) {
                            continue;
                        }
                        $slug = Str::slug($text) ?: 'tag';
                        $tagId = $tagIdByName[$slug] ??= (DB::table('tags')->where('slug', $slug)->value('id')
                            ?: DB::table('tags')->insertGetId(['name' => Str::limit($text, 60, ''), 'slug' => $slug, 'color' => '#6366f1', 'created_at' => $now, 'updated_at' => $now]));
                        $linked = DB::table('topic_tag')->insertOrIgnore(['topic_id' => $tid, 'tag_id' => $tagId]);
                        $summary['tags'] += $linked;
                    }
                });
        }

        // Reactions — every reputation row on a post becomes a 👍 reaction.
        if ($sb->hasTable('core_reputation_index')) {
            $progress('Importing reactions…', 90, $summary);
            $conn->table('core_reputation_index')->where('app', 'forums')->where('type', 'pid')->orderBy('id')
                ->chunk(500, function ($rows) use (&$summary, $postMap, $userMap, $now) {
                    $ins = [];
                    foreach ($rows as $r) {
                        $pid = $postMap[$r->type_id] ?? null;
                        $uid = $userMap[$r->member_id] ?? null;
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

        $progress('Finishing up…', 96, $summary);
        foreach ($topicStat as $tid => $st) {
            DB::table('topics')->where('id', $tid)->update(['reply_count' => max(0, $st['n'] - 1), 'last_post_at' => $st['last']]);
        }
        $progress('Import complete.', 100, $summary);

        return $summary;
    }

    /**
     * Convert IPS post HTML into clean Convoro HTML:
     *   - <a data-mentionid>@Name</a>        → plain @Name text
     *   - <blockquote class=ipsQuote ...>     → <blockquote> with "Name wrote:" attribution
     *   - <img data-emoticon alt=":)">        → its alt text
     *   - strips IPS class/style/data-* hooks; sanitised against XSS.
     */
    public static function ipsHtml(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $html = Src::sanitizeHtml($html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        // Wrap so we can pull innerHTML of a single root; UTF-8 hint via meta.
        $ok = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="ips-root">'.$html.'</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        if (! $ok) {
            return $html;
        }
        $root = $dom->getElementById('ips-root');
        if (! $root) {
            return $html;
        }

        // Mentions → plain text node (Convoro re-linkifies @Name itself).
        foreach (iterator_to_array($dom->getElementsByTagName('a')) as $a) {
            if ($a->hasAttribute('data-mentionid') || str_contains($a->getAttribute('class'), 'ipsMention')) {
                $a->parentNode?->replaceChild($dom->createTextNode($a->textContent), $a);
            }
        }

        // Emoticon images → their alt text.
        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
            if ($img->hasAttribute('data-emoticon') || str_contains($img->getAttribute('class'), 'ipsEmoji')) {
                $alt = $img->getAttribute('alt');
                $img->parentNode?->replaceChild($dom->createTextNode($alt), $img);
            } elseif (! $img->getAttribute('src') && $img->getAttribute('data-src')) {
                $img->setAttribute('src', $img->getAttribute('data-src'));
            }
        }

        // Quotes → clean blockquote with attribution; drop the IPS citation chrome.
        foreach (iterator_to_array($dom->getElementsByTagName('blockquote')) as $bq) {
            if (! str_contains($bq->getAttribute('class'), 'ipsQuote') && ! $bq->hasAttribute('data-ipsquote')) {
                continue;
            }
            $user = trim($bq->getAttribute('data-ipsquote-username'));
            // Find the contents div; fall back to whatever's inside minus the citation.
            $contents = null;
            foreach (iterator_to_array($bq->childNodes) as $child) {
                if ($child instanceof \DOMElement && str_contains($child->getAttribute('class'), 'ipsQuote_citation')) {
                    $bq->removeChild($child);

                    continue;
                }
                if ($child instanceof \DOMElement && str_contains($child->getAttribute('class'), 'ipsQuote_contents')) {
                    $contents = $child;
                }
            }
            // Collapse the contents wrapper into the blockquote body.
            if ($contents) {
                while ($contents->firstChild) {
                    $bq->insertBefore($contents->firstChild, $contents);
                }
                $bq->removeChild($contents);
            }
            // Reset to a plain blockquote and prepend the attribution.
            foreach (iterator_to_array($bq->attributes) as $attr) {
                $bq->removeAttribute($attr->nodeName);
            }
            if ($user !== '') {
                $cite = $dom->createElement('p');
                $strong = $dom->createElement('strong', $user.' wrote:');
                $cite->appendChild($strong);
                $bq->insertBefore($cite, $bq->firstChild);
            }
        }

        // Strip IPS styling hooks from every element (keep href/src/alt/title).
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
            foreach (iterator_to_array($el->attributes) as $attr) {
                $n = $attr->nodeName;
                if ($n === 'class' || $n === 'style' || str_starts_with($n, 'data-')) {
                    $el->removeAttribute($n);
                }
            }
        }

        // Serialise innerHTML of the root.
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }
}
