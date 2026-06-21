<?php

namespace App\Observers;

use App\Jobs\SyncTopicTypesenseJob;
use App\Models\Topic;
use App\Support\Search\TypesenseSync;

/**
 * Keeps Typesense in step with topic-level changes the PostObserver can't see
 * (title edits, deletion). Body changes flow through the PostObserver instead.
 */
class TopicObserver
{
    public function updated(Topic $topic): void
    {
        if ($topic->wasChanged('title') && TypesenseSync::active()) {
            SyncTopicTypesenseJob::dispatch((int) $topic->id)->afterCommit();
        }
    }

    public function deleted(Topic $topic): void
    {
        if (TypesenseSync::active()) {
            SyncTopicTypesenseJob::dispatch((int) $topic->id)->afterCommit();
        }
    }
}
