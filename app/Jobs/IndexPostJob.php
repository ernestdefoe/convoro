<?php

namespace App\Jobs;

use App\Support\AskIndex;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Keeps the semantic index fresh: embeds one post when it's created/edited. */
class IndexPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $postId) {}

    public function handle(): void
    {
        AskIndex::indexPost($this->postId);
    }
}
