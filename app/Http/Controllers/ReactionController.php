<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reaction;
use App\Notifications\ReactionNotification;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    private const ALLOWED = ['👍', '❤️', '😂', '😮', '😢', '🎉', '🔥'];

    public function toggle(Request $request, Post $post): RedirectResponse
    {
        $emoji = (string) $request->input('emoji');
        abort_unless(in_array($emoji, self::ALLOWED, true), 422);

        $existing = Reaction::where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->where('emoji', $emoji)->first();

        if ($existing) {
            $existing->delete();
        } else {
            Reaction::create(['post_id' => $post->id, 'user_id' => $request->user()->id, 'emoji' => $emoji]);

            // Notify the post's author that someone reacted (not for self-reactions).
            if ((int) $post->user_id !== (int) $request->user()->id) {
                $author = $post->user;
                if ($author) {
                    Notifier::send($author, new ReactionNotification($post, $request->user(), $emoji));
                }
            }
        }

        return back();
    }
}
