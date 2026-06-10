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

        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
        ]);

        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, 'Empty post.');

        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $request->user()->id,
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
        ]);

        $topic->increment('reply_count');
        $topic->update(['last_post_at' => now()]);

        // Live-broadcast the new post to everyone viewing this topic.
        $post->load(['user', 'reactions']);
        broadcast(new PostCreated(Present::post($post, null), $topic->id));

        $this->notifyParticipants($request, $topic, $post, $html);

        return back();
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        $own = (int) $post->user_id === (int) $user->id;
        abort_unless($own ? $user->hasPermission('post.edit_own') : $user->hasPermission('post.edit_any'), 403);

        $data = $request->validate(['body_html' => ['required', 'string', 'max:120000']]);
        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, 'Empty post.');

        $post->update(['body_html' => $html, 'edited_at' => now()]);

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
