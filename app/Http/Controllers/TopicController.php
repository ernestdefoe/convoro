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

        return Inertia::render('Topic/Create', [
            'categories' => Category::orderBy('position')->get(['id', 'name', 'icon', 'color']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'draft' => $draft,
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
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:200'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['nullable', 'string', 'max:120'],
            'poll.multiple' => ['boolean'],
            'poll.closes_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $data['body_html'] = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($data['body_html'])) === '', 422, __('The post body is empty.'));

        $topic = \App\Support\TopicPublisher::publish($request->user(), $data, $request->ip());

        // Publishing from a saved draft removes it.
        if ($request->filled('draft_id')) {
            \App\Models\Draft::where('user_id', $request->user()->id)->whereKey($request->input('draft_id'))->delete();
        }

        return redirect()->route('topics.show', $topic);
    }

    /** Pin or unpin a topic so it sorts to the top of the list (moderators). */
    public function togglePin(Request $request, Topic $topic): \Illuminate\Http\RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasPermission('topic.pin'), 403);
        $topic->update(['is_pinned' => ! $topic->is_pinned]);

        return back()->with('status', $topic->is_pinned ? __('Topic pinned.') : __('Topic unpinned.'));
    }

    /** Lock or unlock a topic (locked topics can't receive new replies). */
    public function toggleLock(Request $request, Topic $topic): \Illuminate\Http\RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasPermission('topic.lock'), 403);
        $topic->update(['is_locked' => ! $topic->is_locked]);

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

    public function show(Topic $topic): Response
    {
        $topic->increment('view_count');
        $topic->load(['user', 'category', 'tags', 'posts.user.groups', 'posts.reactions', 'poll.options']);
        $actor = auth()->user();
        $actor?->loadMissing('groups');
        $actorId = $actor?->id;

        // Mark this discussion read for the current member (clears its "new" badge).
        if ($actor) {
            \Illuminate\Support\Facades\DB::table('topic_reads')->updateOrInsert(
                ['user_id' => $actor->id, 'topic_id' => $topic->id],
                ['last_read_at' => now()],
            );
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
            ],
            'posts' => $topic->posts->sortBy('created_at')
                // Posts held by the AI moderation copilot are hidden from everyone
                // except moderators and the post's own author (who sees a notice).
                ->filter(fn ($p) => ! $p->hidden || ($actor && $actor->is_admin) || ($actorId && (int) $p->user_id === (int) $actorId))
                ->values()->map(fn ($p) => Present::post($p, $actorId, $actor)),
            'references' => \App\Support\CrossRef::into($topic->id),
            'categories' => Category::orderBy('position')->get(['id', 'name', 'icon', 'color']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'canReply' => auth()->check() && ! $topic->is_locked,
            'seo' => \App\Support\Seo::make([
                'title' => $topic->title,
                'description' => \App\Support\Seo::clean(optional($topic->firstPost)->body_html),
                'image' => $topic->cover_image,
                'type' => 'article',
            ]),
        ]);
    }
}
