<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Support\Search\TypesenseSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a single topic's current state into Typesense (or removes it if the
 * topic no longer exists). Runs off the request thread so a slow engine never
 * stalls posting; the sync helper itself is a no-op when the extension is off.
 */
class SyncTopicTypesenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $topicId) {}

    public function handle(): void
    {
        if (! TypesenseSync::active()) {
            return;
        }
        $topic = Topic::find($this->topicId);
        if ($topic) {
            TypesenseSync::index($topic);
        } else {
            TypesenseSync::remove($this->topicId);
        }
    }
}
