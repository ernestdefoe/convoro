<?php

namespace App\Jobs;

use App\Models\Post;
use App\Support\ContentTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Detect and store a post's source language so readers with auto-translate on
 * know whether the post needs translating. Runs after a post is created/edited.
 */
class DetectPostLanguageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public int $postId) {}

    public function handle(): void
    {
        $post = Post::find($this->postId);
        if (! $post) {
            return;
        }
        $code = ContentTranslator::detect((string) $post->body_html);
        if ($code) {
            // updateQuietly so we don't retrigger the observer (infinite loop).
            $post->updateQuietly(['detected_locale' => $code]);
        }
    }
}
