<?php

namespace App\Observers;

use App\Jobs\DetectPostLanguageJob;
use App\Jobs\IndexPostJob;
use App\Jobs\ModeratePostJob;
use App\Jobs\SyncTopicTypesenseJob;
use App\Models\Post;
use App\Support\AskIndex;
use App\Support\ContentTranslator;
use App\Support\CrossRef;
use App\Support\Moderation;
use App\Support\Search\TypesenseSync;
use Illuminate\Support\Facades\DB;

/** Keeps the "Ask Convoro" index, cross-references and language detection in sync. */
class PostObserver
{
    /** Issue the post's per-topic sequence number from topics.post_counter.
     * The row lock makes concurrent replies take distinct numbers; the
     * unique(topic_id, number) index is the backstop. */
    public function creating(Post $post): void
    {
        if ($post->number === null && $post->topic_id) {
            $post->number = DB::transaction(function () use ($post) {
                $counter = (int) DB::table('topics')->where('id', $post->topic_id)
                    ->lockForUpdate()->value('post_counter');
                DB::table('topics')->where('id', $post->topic_id)
                    ->update(['post_counter' => $counter + 1]);

                return $counter + 1;
            });
        }
    }

    public function created(Post $post): void
    {
        $this->reindex($post);
        $this->syncTypesense($post);
        CrossRef::sync($post);
        $this->detectLanguage($post);
        if (Moderation::enabled() && ! $post->is_ai) {
            ModeratePostJob::dispatch($post->id)->afterCommit();
        }
    }

    public function updated(Post $post): void
    {
        $this->reindex($post);
        $this->syncTypesense($post);
        CrossRef::sync($post);
        // If the body actually changed, re-detect and drop stale cached translations.
        if ($post->wasChanged('body_html')) {
            DB::table('content_translations')->where('post_id', $post->id)->delete();
            $this->detectLanguage($post);
        }
    }

    public function deleted(Post $post): void
    {
        AskIndex::removePost($post->id);
        $this->syncTypesense($post);
        CrossRef::remove($post);
        DB::table('content_translations')->where('post_id', $post->id)->delete();
    }

    /** Re-index the post's topic in Typesense when the bundled extension is on. */
    private function syncTypesense(Post $post): void
    {
        if ($post->topic_id && TypesenseSync::active()) {
            SyncTopicTypesenseJob::dispatch((int) $post->topic_id)->afterCommit();
        }
    }

    private function reindex(Post $post): void
    {
        // Don't index the assistant's own answers — it shouldn't cite itself.
        if ($post->is_ai) {
            return;
        }
        if (AskIndex::configured()) {
            IndexPostJob::dispatch($post->id)->afterCommit();
        }
    }

    private function detectLanguage(Post $post): void
    {
        if (ContentTranslator::enabled()) {
            DetectPostLanguageJob::dispatch($post->id)->afterCommit();
        }
    }
}
