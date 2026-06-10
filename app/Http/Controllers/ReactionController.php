<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reaction;
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
        }

        return back();
    }
}
