<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Default-deny privacy boundary for Social Groups. Group discussions are stored
 * as core Topics carrying a group_id; this global scope removes every such topic
 * from ordinary Topic queries, so they never surface in the main feed, search,
 * category pages, profiles, sitemap, RSS, digests or the AI index. Group-aware
 * code opts back in explicitly with Topic::withoutGlobalScope(SocialGroupTopicScope::class).
 *
 * Failing safe matters here: a listing we forget to touch simply hides group
 * content (correct) rather than leaking a private group's posts (a breach).
 */
class SocialGroupTopicScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->getTable().'.group_id');
    }
}
