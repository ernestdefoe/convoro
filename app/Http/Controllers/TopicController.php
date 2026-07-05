<?php

namespace App\Http\Controllers;

use App\Jobs\AutoAnswerTopicJob;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Topic;
use App\Support\Ask;
use App\Support\Content;
use App\Support\Present;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    /** Threads at or under this many posts ship whole (classic behavior). */
    public const STREAM_FULL_LOAD = 150;

    /** Posts per window / per stream fetch above that. */
    public const STREAM_WINDOW = 50;

    /** Posts in this topic the viewer is allowed to see: held posts stay
     * visible to moderators and to their own author (who sees a notice). */
    private function viewablePosts(Topic $topic, ?\App\Models\User $actor): \Illuminate\Database\Eloquent\Builder
    {
        $canModerate = (bool) ($actor && $actor->is_admin);

        return \App\Models\Post::where('topic_id', $topic->id)
            ->when(! $canModerate, fn ($q) => $q->where(fn ($w) => $w
                ->where('hidden', false)
                ->when($actor?->id, fn ($x, $id) => $x->orWhere('user_id', $id))));
    }

    /** JSON window of posts for the streamed topic view — the page calls this
     * as the reader scrolls or scrubs instead of ever loading a whole thread. */
    public function posts(Request $request, Topic $topic): \Illuminate\Http\JsonResponse
    {
        $actor = auth()->user();
        abort_if($topic->hidden && ! ($actor && ($actor->is_admin || (int) $actor?->id === (int) $topic->user_id)), 404);

        $limit = min(100, max(10, (int) $request->query('limit', self::STREAM_WINDOW)));
        $query = $this->viewablePosts($topic, $actor)->with(['user.groups', 'reactions']);

        if ($request->filled('before')) {
            $posts = $query->where('number', '<', (int) $request->query('before'))
                ->orderByDesc('number')->limit($limit)->get()->reverse()->values();
        } else {
            $posts = $query->where('number', '>', (int) $request->query('after', 0))
                ->orderBy('number')->limit($limit)->get();
        }

        return response()->json([
            'posts' => $posts->map(fn ($p) => Present::post($p, $actor?->id, $actor))->values(),
            'total' => (int) $topic->post_counter,
        ]);
    }

    public function create(Request $request): Response
    {
        // Resume a saved draft when ?draft=<id> is present and it's the member's own.
        $draft = null;
        if ($request->filled('draft') && $request->user()) {
            $d = \App\Models\Draft::where('user_id', $request->user()->id)->find($request->query('draft'));
            if ($d) {
                $draft = [
                    'id' => $d->id, 'title' => $d->title, 'body_html' => $d->body_html, 'body_json' => $d->body_json,
                    'category_id' => $d->category_id, 'tags' => $d->tags ?? [], 'cover' => $d->cover, 'poll' => $d->poll,
                ];
            }
        }

        // The rolling autosave draft, offered for one-tap restore when the
        // member returns to a fresh composer (not while resuming a saved draft).
        $autosave = null;
        if (! $draft && $request->user()) {
            $a = \App\Models\Draft::where('user_id', $request->user()->id)->where('is_auto', true)->first();
            if ($a && (trim((string) $a->title) !== '' || trim(strip_tags((string) $a->body_html)) !== '')) {
                $autosave = [
                    'title' => $a->title, 'body_html' => $a->body_html, 'body_json' => $a->body_json,
                    'category_id' => $a->category_id, 'tags' => $a->tags ?? [], 'cover' => $a->cover, 'poll' => $a->poll,
                    'savedAt' => optional($a->updated_at)->toIso8601String(),
                    'savedAgo' => optional($a->updated_at)->diffForHumans(),
                ];
            }
        }

        return Inertia::render('Topic/Create', [
            'categories' => Category::orderBy('position')->get(['id', 'name', 'icon', 'color']),
            'tags' => Tag::whereNull('parent_id')->orderBy('position')->orderBy('name')
                ->with(['children' => fn ($c) => $c->orderBy('position')->orderBy('name')])
                ->get()->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'icon' => $t->icon, 'color' => $t->color,
                    'children' => $t->children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color])->all(),
                ]),
            'draft' => $draft,
            'autosave' => $autosave,
            // Boards where an extension supplies the content (body optional).
            'bodyOptionalCategories' => \App\Support\CategoryRules::bodyOptionalIds(),
            // Flarum-style min/max primary (top-level) tags required per topic.
            'tagRules' => [
                'minPrimary' => (int) \App\Support\Settings::get('tags.min_primary', 0),
                'maxPrimary' => (int) \App\Support\Settings::get('tags.max_primary', 0),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->isBanned(), 403, __('Your account is suspended.'));

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'body_html' => ['nullable', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:200'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['nullable', 'string', 'max:120'],
            'poll.multiple' => ['boolean'],
            'poll.closes_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        // Flarum-style min/max primary (top-level) tags per topic.
        $minP = (int) \App\Support\Settings::get('tags.min_primary', 0);
        $maxP = (int) \App\Support\Settings::get('tags.max_primary', 0);
        if ($minP > 0 || $maxP > 0) {
            $primaryCount = \App\Models\Tag::whereIn('id', $data['tags'] ?? [])->whereNull('parent_id')->count();
            abort_if($minP > 0 && $primaryCount < $minP, 422, __('Please choose at least :n primary space(s).', ['n' => $minP]));
            abort_if($maxP > 0 && $primaryCount > $maxP, 422, __('Please choose at most :n primary space(s).', ['n' => $maxP]));
        }

        $data['body_html'] = Content::clean($data['body_html'] ?? '');
        if (\App\Support\TrustLevels::gatesContent($request->user())) {
            $data['body_html'] = \App\Support\TrustLevels::stripLinksMedia($data['body_html']);
        }
        // A body is required unless the board lets an extension supply the content
        // (e.g. a TMDB movie card), in which case an empty first post is fine.
        $bodyOptional = \App\Support\CategoryRules::isBodyOptional(! empty($data['category_id']) ? (int) $data['category_id'] : null);
        abort_if(! $bodyOptional && trim(strip_tags($data['body_html'], '<img><video><audio><iframe>')) === '', 422, __('The post body is empty.'));

        // Spam, flood & slow-mode controls.
        $category = ! empty($data['category_id']) ? \App\Models\Category::find($data['category_id']) : null;
        $guard = \App\Support\PostGuard::inspect($request->user(), $data['body_html'], 'topic', $category);
        abort_if($guard['block'], 422, $guard['message'] ?? __('Your topic was blocked.'));

        $topic = \App\Support\TopicPublisher::publish($request->user(), $data, $request->ip(), $guard['hold']);

        // Held for review — file it to the mod queue and tell the author.
        if ($guard['hold']) {
            \App\Support\PostGuard::report($topic->firstPost, $guard['reason']);

            return redirect()->route('forum.index')->with('status', $guard['message']);
        }

        // Publishing from a saved draft removes it; the rolling autosave is
        // always cleared once the topic is live.
        if ($request->filled('draft_id')) {
            \App\Models\Draft::where('user_id', $request->user()->id)->whereKey($request->input('draft_id'))->delete();
        }
        \App\Models\Draft::where('user_id', $request->user()->id)->where('is_auto', true)->delete();

        // Starting a topic is participation — re-check for a promotion.
        \App\Support\TrustLevels::evaluate($request->user());

        return redirect()->route('topics.show', $topic);
    }

    /** Pin or unpin a topic so it sorts to the top of the list (moderators). */
    public function togglePin(Request $request, Topic $topic): \Illuminate\Http\RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasPermission('topic.pin'), 403);
        $topic->update(['is_pinned' => ! $topic->is_pinned]);
        \App\Support\AuditLog::record($topic->is_pinned ? 'topic.pin' : 'topic.unpin', $topic);

        return back()->with('status', $topic->is_pinned ? __('Topic pinned.') : __('Topic unpinned.'));
    }

    /** Lock or unlock a topic (locked topics can't receive new replies). */
    public function toggleLock(Request $request, Topic $topic): \Illuminate\Http\RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasPermission('topic.lock'), 403);
        $topic->update(['is_locked' => ! $topic->is_locked]);
        \App\Support\AuditLog::record($topic->is_locked ? 'topic.lock' : 'topic.unlock', $topic);

        return back()->with('status', $topic->is_locked ? __('Topic locked.') : __('Topic unlocked.'));
    }

    /** Heartbeat: the current member is viewing this topic right now (drives the LIVE badge). */
    public function heartbeat(Request $request, Topic $topic): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['count' => 0, 'live' => false]);
        }
        $count = \App\Support\LiveTopics::heartbeat($topic->id, $request->user()->id);

        return response()->json(['count' => $count, 'live' => $count >= \App\Support\LiveTopics::threshold()]);
    }

    /** Live typing signal — broadcasts this topic's tag slugs so the discussion
     *  list shows a typing bubble beside them. Throttled at the route. */
    public function typing(Request $request, Topic $topic): \Illuminate\Http\JsonResponse
    {
        $slugs = $topic->tags()->pluck('slug')->all();
        if ($slugs && $request->user()) {
            broadcast(new \App\Events\TagTyping($slugs, $request->user()->name));
        }

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, Topic $topic): Response|\Illuminate\Http\JsonResponse
    {
        // ActivityPub content negotiation: when a fediverse server fetches the
        // topic URL as application/activity+json (e.g. to dereference a boosted
        // Note), return the Note object instead of the HTML page.
        if (\App\Support\Federation::enabled() && ! $topic->hidden
            && str_contains((string) $request->header('Accept'), 'application/activity+json')) {
            $topic->loadMissing('firstPost');
            $note = \App\Support\Federation::noteForTopic($topic);

            return response()->json(['@context' => \App\Support\Federation::context()] + $note)
                ->header('Content-Type', \App\Support\Federation::CTYPE);
        }

        $actor = auth()->user();
        $actor?->loadMissing('groups');
        $actorId = $actor?->id;

        // A topic held for moderator approval is visible only to its author and
        // admins (who see a "pending review" banner); everyone else gets a 404.
        abort_if($topic->hidden && ! ($actor && ($actor->is_admin || (int) $actorId === (int) $topic->user_id)), 404);

        $topic->increment('view_count');
        $topic->load(['user', 'category', 'tags', 'poll.options']);
        $topic->loadMissing('firstPost.user');

        // Work out where the member left off BEFORE we move their read marker,
        // so the page can jump to the first reply they haven't seen yet.
        $firstUnreadId = null;
        $firstUnreadNumber = null;
        if ($actor) {
            $prev = \Illuminate\Support\Facades\DB::table('topic_reads')
                ->where('user_id', $actor->id)->where('topic_id', $topic->id)->value('last_read_at');
            if ($prev) {
                $unread = $this->viewablePosts($topic, $actor)
                    ->where('is_first', false)
                    ->where('created_at', '>', \Illuminate\Support\Carbon::parse($prev))
                    ->orderBy('number')->first(['id', 'number']);
                $firstUnreadId = $unread?->id;
                $firstUnreadNumber = $unread?->number ? (int) $unread->number : null;
            }

            // Mark this discussion read for the current member (clears its "new" badge).
            \Illuminate\Support\Facades\DB::table('topic_reads')->updateOrInsert(
                ['user_id' => $actor->id, 'topic_id' => $topic->id],
                ['last_read_at' => now()],
            );
        }

        // Post window. Small topics ship whole, exactly as before. Big ones
        // ship ~one screenful around the target position (?post=N, else the
        // first unread reply, else the top) — never the entire thread; the
        // page streams more from topics.posts as the reader moves.
        $maxNumber = (int) $topic->post_counter;
        $full = $maxNumber <= self::STREAM_FULL_LOAD;
        $target = max(1, (int) $request->query('post', 0)) ?: ($firstUnreadNumber ?? 1);

        $postsQuery = $this->viewablePosts($topic, $actor)->with(['user.groups', 'reactions']);
        if ($full) {
            $posts = $postsQuery->orderBy('number')->get();
            $stream = null; // whole thread present — page behaves classically
        } else {
            $start = max(1, min($target - 5, $maxNumber - self::STREAM_WINDOW + 1));
            $posts = $postsQuery->where('number', '>=', $start)
                ->orderBy('number')->limit(self::STREAM_WINDOW)->get();
            // The opening post is the page header — always ship it.
            if ($start > 1 && ! $posts->contains(fn ($p) => $p->is_first)) {
                $op = $this->viewablePosts($topic, $actor)->with(['user.groups', 'reactions'])
                    ->where('is_first', true)->first();
                if ($op) {
                    $posts->prepend($op);
                }
            }
            $replyNumbers = $posts->filter(fn ($p) => ! $p->is_first)->pluck('number')->filter();
            $stream = [
                'total' => $maxNumber,
                'windowStart' => (int) ($replyNumbers->min() ?? $start),
                'windowEnd' => (int) ($replyNumbers->max() ?? $maxNumber),
                'hasBefore' => $start > 1,
                'hasAfter' => (int) ($replyNumbers->max() ?? $maxNumber) < $maxNumber,
            ];
        }

        return Inertia::render('Topic/Show', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'cover' => $topic->cover_image,
                'isLive' => $topic->is_live,
                'isLocked' => $topic->is_locked,
                'isPinned' => $topic->is_pinned,
                'canPin' => (bool) $actor?->hasPermission('topic.pin'),
                'canLock' => (bool) $actor?->hasPermission('topic.lock'),
                'poll' => $topic->poll ? \App\Support\Present::poll($topic->poll, $actorId) : null,
                'category' => $topic->category ? [
                    'name' => $topic->category->name, 'slug' => $topic->category->slug,
                    'color' => $topic->category->color, 'icon' => $topic->category->icon,
                ] : null,
                'tags' => $topic->tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug, 'color' => $t->color]),
                'categoryId' => $topic->category_id,
                'tagIds' => $topic->tags->pluck('id'),
                'replyCount' => $topic->reply_count,
                'viewCount' => $topic->view_count,
                'pending' => (bool) $topic->hidden,
            ],
            'posts' => $posts->values()->map(fn ($p) => Present::post($p, $actorId, $actor)),
            'stream' => $stream,
            'firstUnreadId' => $firstUnreadId,
            'references' => \App\Support\CrossRef::into($topic->id),
            'categories' => Category::orderBy('position')->get(['id', 'name', 'icon', 'color']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'canReply' => auth()->check() && ! $topic->is_locked,
            'seo' => \App\Support\Seo::make([
                'title' => $topic->title,
                'description' => \App\Support\Seo::clean(optional($topic->firstPost)->body_html),
                'image' => $topic->cover_image,
                'type' => 'article',
                'jsonLd' => \App\Support\Seo::forumPosting([
                    'title' => $topic->title,
                    'url' => url('/t/'.$topic->slug),
                    'text' => optional($topic->firstPost)->body_html,
                    'image' => $topic->cover_image ?: \App\Support\Seo::firstImage(optional($topic->firstPost)->body_html),
                    'datePublished' => optional(optional($topic->firstPost)->created_at)->toAtomString(),
                    'dateModified' => optional($topic->last_posted_at ?? $topic->updated_at)->toAtomString(),
                    'author' => ($f = $topic->firstPost) && $f->user
                        ? ['name' => $f->user->name, 'url' => url('/u/'.$f->user->id)] : null,
                    'replyCount' => (int) $topic->reply_count,
                    'viewCount' => (int) $topic->view_count,
                    'comments' => $posts->filter(fn ($p) => ! $p->is_first)->sortBy('number')->take(10)
                        ->map(fn ($p) => [
                            'text' => $p->body_html,
                            'image' => \App\Support\Seo::firstImage($p->body_html),
                            'date' => optional($p->created_at)->toAtomString(),
                            'author' => optional($p->user)->name,
                            'authorUrl' => $p->user ? url('/u/'.$p->user->id) : null,
                            'url' => url('/t/'.$topic->slug.'#post-'.$p->id),
                        ])->values()->all(),
                ]),
            ]),
        ]);
    }
}
