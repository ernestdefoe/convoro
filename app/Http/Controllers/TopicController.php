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
    public function create(): Response
    {
        return Inertia::render('Topic/Create', [
            'categories' => Category::orderBy('position')->get(['id', 'name', 'icon', 'color']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
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

        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, __('The post body is empty.'));

        $base = Str::slug($data['title']) ?: 'topic';
        $slug = $base.'-'.Str::lower(Str::random(6));

        $topic = Topic::create([
            'title' => $data['title'],
            'slug' => $slug,
            'user_id' => $request->user()->id,
            'category_id' => $data['category_id'] ?? null,
            'cover_image' => $data['cover'] ?? null,
            'last_post_at' => now(),
        ]);

        $topic->posts()->create([
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
            'is_first' => true,
        ]);

        if (! empty($data['tags'])) {
            $topic->tags()->sync($data['tags']);
        }

        // Optional attached poll.
        if (! empty($data['poll']['question'])) {
            $options = collect($data['poll']['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->take(10)->values();
            if ($options->count() >= 2) {
                $poll = $topic->poll()->create([
                    'question' => $data['poll']['question'],
                    'max_choices' => ! empty($data['poll']['multiple']) ? $options->count() : 1,
                    'closes_at' => ! empty($data['poll']['closes_days']) ? now()->addDays((int) $data['poll']['closes_days']) : null,
                ]);
                $options->each(fn ($text, $i) => $poll->options()->create(['text' => $text, 'position' => $i]));
            }
        }

        // "No question goes unanswered" — let the assistant draft an answer.
        if (Ask::enabled() && Settings::get('ai.autoanswer_enabled', false)) {
            $delay = max(0, (int) Settings::get('ai.autoanswer_delay_minutes', 0));
            AutoAnswerTopicJob::dispatch($topic->id)->delay(now()->addMinutes($delay))->afterCommit();
        }

        return redirect()->route('topics.show', $topic);
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
