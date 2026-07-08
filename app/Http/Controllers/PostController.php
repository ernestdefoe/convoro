<?php

namespace App\Http\Controllers;

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\Topic;
use App\Support\Content;
use App\Support\Mentions;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request, Topic $topic): RedirectResponse
    {
        abort_if($topic->is_locked, 403);
        abort_if($request->user()->isBanned(), 403, __('Your account is suspended.'));
        abort_unless($request->user()->hasPermissionIn($topic->category, 'post.reply'), 403, __('You can’t reply in this category.'));

        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
        ]);

        $html = Content::clean($data['body_html']);
        // New (trust level 0) accounts have links/images stripped to blunt spam.
        if (\App\Support\TrustLevels::gatesContent($request->user())) {
            $html = \App\Support\TrustLevels::stripLinksMedia($html);
        }
        abort_if(trim(strip_tags($html, '<img><video><audio><iframe>')) === '', 422, __('Empty post.'));

        // Spam, flood & slow-mode controls.
        $guard = \App\Support\PostGuard::inspect($request->user(), $html, 'reply', $topic->category);
        abort_if($guard['block'], 422, $guard['message'] ?? __('Your post was blocked.'));

        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
            'hidden' => $guard['hold'],
        ]);

        // A held post is invisible until a moderator approves it — log it for the
        // mod queue and skip the counters, broadcast, notifications and fedi-out.
        if ($guard['hold']) {
            \App\Support\PostGuard::report($post, $guard['reason']);

            return back()->with('status', $guard['message']);
        }

        $topic->increment('reply_count');
        $topic->update(['last_post_at' => now()]);

        // Live-broadcast the new post to everyone viewing this topic. A realtime
        // outage (Reverb down) must never 500 the post — it's a best-effort push.
        $post->load(['user', 'reactions']);
        try {
            broadcast(new PostCreated(Present::post($post, null), $topic->id));
        } catch (\Throwable $e) {
            report($e);
        }

        // Bump the topic in everyone's forum list (recent activity) — public
        // topics only; gated inside TopicBroadcast.
        \App\Support\TopicBroadcast::activity($topic, false);

        // Mention + reply notifications fan out on the queue — in a busy topic
        // that's hundreds of DB writes and broadcasts that must not run inline.
        \App\Jobs\NotifyParticipantsJob::dispatch($post->id)->afterCommit();

        // Social-group discussions stay inside the group: no AI auto-answer and
        // no fediverse cross-post (which would leak a private group's content).
        if (! $topic->group_id) {
            // If the member @mentioned the assistant, have it reply with a grounded answer.
            $mentionedIds = Mentions::parse(strip_tags($html))->pluck('id')->all();
            if (\App\Jobs\AnswerMentionJob::shouldAnswer($post, $mentionedIds)) {
                \App\Jobs\AnswerMentionJob::dispatch($post->id)->afterCommit();
            }

            // Cross-post out to the fediverse (no-op unless federation is on + relevant).
            \App\Support\Federation::announceReply($post, $topic);
        }

        // Posting is participation — re-check whether they've earned a promotion.
        \App\Support\TrustLevels::evaluate($request->user());

        return back();
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        $own = (int) $post->user_id === (int) $user->id;
        abort_unless($own ? $user->hasPermission('post.edit_own') : $user->hasPermission('post.edit_any'), 403);

        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:120000'],
            'title' => ['nullable', 'string', 'max:160'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);
        $html = Content::clean($data['body_html']);
        if (\App\Support\TrustLevels::gatesContent($user)) {
            $html = \App\Support\TrustLevels::stripLinksMedia($html);
        }
        abort_if(trim(strip_tags($html, '<img><video><audio><iframe>')) === '', 422, __('Empty post.'));

        $post->update(['body_html' => $html, 'edited_at' => now()]);

        // The opening post owns the topic's title, cover, category and tags.
        if ($post->is_first) {
            $topicChanges = [];
            if ($request->filled('title')) {
                $topicChanges['title'] = trim($data['title']);
            }
            if ($request->has('cover')) {
                $topicChanges['cover_image'] = $data['cover'] ?: null;
            }
            if ($request->has('category_id')) {
                $topicChanges['category_id'] = $data['category_id'] ?: null;
            }
            if ($topicChanges) {
                $post->topic->update($topicChanges);
            }
            if ($request->has('tags')) {
                $post->topic->tags()->sync($data['tags'] ?? []);
            }
        }

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        $own = (int) $post->user_id === (int) $user->id;
        abort_unless($own ? $user->hasPermission('post.delete_own') : $user->hasPermission('post.delete_any'), 403);

        $topic = $post->topic;

        // Deleting the opening post removes the whole topic.
        if ($post->is_first) {
            abort_unless($own || $user->hasPermission('topic.delete_any'), 403);
            $topic->tags()->detach();
            $topic->posts()->delete();
            $topic->delete();

            return redirect()->route('forum.index');
        }

        $post->delete();
        $topic->decrement('reply_count');

        return back();
    }

    /**
     * Notify @mentioned users (mention takes precedence) and other thread
     * participants (topic author + everyone who has posted), minus the author.
     */
}
