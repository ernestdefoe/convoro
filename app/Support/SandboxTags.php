<?php

namespace App\Support;

use App\Models\Tag;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sandbox tags are practice areas — members use them to try the editor,
 * images and formatting without their activity trending or counting.
 * This helper is the single definition of "which topics are sandboxed"
 * so Trending, community stats and profile stats can't drift apart.
 */
class SandboxTags
{
    /**
     * Subquery of topic ids carrying at least one sandbox tag, or null
     * when no tag is flagged (the common case — callers skip the WHERE
     * entirely so unflagged forums pay nothing).
     */
    public static function topicIds(): ?Builder
    {
        $ids = Tag::query()->where('is_sandbox', true)->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }

        return DB::table('topic_tag')->whereIn('tag_id', $ids)->select('topic_id');
    }
}
