<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PostController;
use App\Models\Scopes\SocialGroupTopicScope;
use App\Models\SocialGroup;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\GroupActivityNotification;
use App\Support\Content;
use App\Support\Notifier;
use App\Support\Present;
use App\Support\Seo;
use App\Support\TrustLevels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupDiscussionController extends Controller
{
    /** Start a discussion in a group — a real Topic carrying the group's id. */
    public function store(Request $request, SocialGroup $group): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($group->canPost($actor), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:200'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['nullable', 'string', 'max:120'],
            'poll.multiple' => ['boolean'],
            'poll.closes_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $html = Content::clean($data['body_html']);
        if (TrustLevels::gatesContent($actor)) {
            $html = TrustLevels::stripLinksMedia($html);
        }
        abort_if(trim(strip_tags($html, '<img><video><audio><iframe>')) === '', 422, __('Empty post.'));

        $topic = \App\Support\TopicPublisher::publish($actor, [
            'title' => trim($data['title']),
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
            'poll' => $data['poll'] ?? null,
        ], $request->ip(), false, $group->id);

        $group->increment('topic_count');
        $group->update(['last_active_at' => now()]);

        $this->notifyMembers($group, $actor, $topic);

        return redirect($this->url($group, $topic))->with('status', __('Discussion posted.'));
    }

    /** Read a group discussion — reuses the core Topic/Show page + composer. */
    public function show(Request $request, SocialGroup $group, int $topicId): Response
    {
        $actor = $request->user();
        abort_unless($group->isVisibleTo($actor), 404);

        $topic = $this->resolve($group, $topicId);
        $actorId = $actor?->id;
        abort_if($topic->hidden && ! ($actor && ($actor->is_admin || (int) $actorId === (int) $topic->user_id)), 404);

        $topic->increment('view_count');
        $topic->load(['user', 'posts.user.groups', 'posts.reactions', 'poll.options']);

        $posts = $topic->posts->sortBy('created_at')
            ->filter(fn ($p) => ! $p->hidden || ($actor && $actor->is_admin) || ($actorId && (int) $p->user_id === (int) $actorId))
            ->map(fn ($p) => Present::post($p, $actorId, $actor))
            ->values()->all();

        return Inertia::render('Topic/Show', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'cover' => $topic->cover_image,
                'isLive' => false,
                'isLocked' => (bool) $topic->is_locked,
                'isPinned' => (bool) $topic->is_pinned,
                'canPin' => $group->canModerate($actor),
                'canLock' => $group->canModerate($actor),
                'poll' => $topic->poll ? Present::poll($topic->poll, $actorId) : null,
                'category' => null,
                'tags' => [],
                'categoryId' => null,
                'tagIds' => [],
                'replyCount' => (int) $topic->reply_count,
                'viewCount' => (int) $topic->view_count,
                'pending' => (bool) $topic->hidden,
            ],
            'posts' => $posts,
            'firstUnreadId' => null,
            'references' => [],
            'categories' => [],
            'allTags' => [],
            'canReply' => $group->canPost($actor) && ! $topic->is_locked,
            // Group context: the page shows a breadcrumb and posts replies here.
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'color' => $group->color,
                'canModerate' => $group->canModerate($actor),
            ],
            'replyUrl' => $this->url($group, $topic).'/reply',
            'moderateBase' => $this->url($group, $topic),
            'seo' => Seo::make([
                'title' => $topic->title.' — '.$group->name,
                'noindex' => $group->is_private,
            ]),
        ]);
    }

    /** Post a reply — reuses PostController so spam/trust/broadcast/notify all apply. */
    public function reply(Request $request, SocialGroup $group, int $topicId): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($group->canPost($actor), 403);
        $topic = $this->resolve($group, $topicId);

        return app(PostController::class)->store($request, $topic);
    }

    public function destroy(Request $request, SocialGroup $group, int $topicId): RedirectResponse
    {
        $actor = $request->user();
        $topic = $this->resolve($group, $topicId);
        abort_unless($group->canModerate($actor) || (int) $topic->user_id === (int) $actor->id, 403);

        $topic->delete();
        $group->decrement('topic_count');

        return redirect('/groups/'.$group->slug)->with('status', __('Discussion deleted.'));
    }

    public function pin(Request $request, SocialGroup $group, int $topicId): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        $topic = $this->resolve($group, $topicId);
        $topic->update(['is_pinned' => ! $topic->is_pinned]);

        return back();
    }

    public function lock(Request $request, SocialGroup $group, int $topicId): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        $topic = $this->resolve($group, $topicId);
        $topic->update(['is_locked' => ! $topic->is_locked]);

        return back();
    }

    // -- helpers ------------------------------------------------------------

    private function resolve(SocialGroup $group, int $topicId): Topic
    {
        $topic = Topic::withoutGlobalScope(SocialGroupTopicScope::class)
            ->where('id', $topicId)->where('group_id', $group->id)->first();
        abort_unless($topic, 404);

        return $topic;
    }

    private function url(SocialGroup $group, Topic $topic): string
    {
        return '/groups/'.$group->slug.'/d/'.$topic->id;
    }

    private function notifyMembers(SocialGroup $group, User $author, Topic $topic): void
    {
        $ids = $group->members()->whereNull('banned_at')
            ->where('user_id', '!=', $author->id)->limit(200)->pluck('user_id');
        User::whereIn('id', $ids)->get()->each(
            fn (User $u) => Notifier::send($u, new GroupActivityNotification($group, $author, 'new_discussion', $topic))
        );
    }
}
