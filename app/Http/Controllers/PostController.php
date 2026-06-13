<?php

namespace App\Http\Controllers;

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\ReplyNotification;
use App\Support\Content;
use App\Support\Mentions;
use App\Support\Notifier;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request, Topic $topic): RedirectResponse
    {
        abort_if($topic->is_locked, 403);
        abort_if($request->user()->isBanned(), 403, __('Your account is suspended.'));

        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
        ]);

        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, __('Empty post.'));

        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
        ]);

        $topic->increment('reply_count');
        $topic->update(['last_post_at' => now()]);

        // Live-broadcast the new post to everyone viewing this topic.
        $post->load(['user', 'reactions']);
        broadcast(new PostCreated(Present::post($post, null), $topic->id));

        $this->notifyParticipants($request, $topic, $post, $html);

        // If the member @mentioned the assistant, have it reply with a grounded answer.
        $mentionedIds = Mentions::parse(strip_tags($html))->pluck('id')->all();
        if (\App\Jobs\AnswerMentionJob::shouldAnswer($post, $mentionedIds)) {
            \App\Jobs\AnswerMentionJob::dispatch($post->id)->afterCommit();
        }

        // Cross-post out to the fediverse (no-op unless federation is on + relevant).
        \App\Support\Federation::announceReply($post, $topic);

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
        abort_if(trim(strip_tags($html)) === '', 422, __('Empty post.'));

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
    private function notifyParticipants(Request $request, Topic $topic, Post $post, string $html): void
    {
        $authorId = (int) $request->user()->id;

        $mentioned = Mentions::parse(strip_tags($html));
        $mentionedIds = $mentioned->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($mentioned as $user) {
            if ((int) $user->id === $authorId) {
                continue;
            }
            Notifier::send($user, new MentionNotification($post));
        }

        $participantIds = Post::where('topic_id', $topic->id)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $replyIds = array_diff(
            array_unique(array_merge($participantIds, [(int) $topic->user_id])),
            array_merge([$authorId], $mentionedIds),
        );

        if (! empty($replyIds)) {
            foreach (User::whereIn('id', $replyIds)->get() as $user) {
                Notifier::send($user, new ReplyNotification($post));
            }
        }
    }
}
