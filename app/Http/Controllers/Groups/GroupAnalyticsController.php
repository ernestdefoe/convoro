<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Scopes\SocialGroupTopicScope;
use App\Models\SocialGroup;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GroupAnalyticsController extends Controller
{
    /** Owner/moderator dashboard: 30-day growth, post volume, top discussions. */
    public function show(Request $request, SocialGroup $group): JsonResponse
    {
        abort_unless($group->canModerate($request->user()), 403);

        $since = Carbon::now()->subDays(30)->startOfDay();
        $topicIds = Topic::withoutGlobalScope(SocialGroupTopicScope::class)
            ->where('group_id', $group->id)->pluck('id');

        $joins = $group->members()
            ->where('joined_at', '>=', $since)
            ->selectRaw('DATE(joined_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');

        $posts = Post::whereIn('topic_id', $topicIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');

        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'joins' => (int) ($joins[$day] ?? 0),
                'posts' => (int) ($posts[$day] ?? 0),
            ];
        }

        $top = Topic::withoutGlobalScope(SocialGroupTopicScope::class)
            ->where('group_id', $group->id)->visible()
            ->withCount('posts')
            ->orderByDesc('posts_count')->orderByDesc('view_count')
            ->limit(10)->get(['id', 'title', 'view_count', 'reply_count'])
            ->map(fn (Topic $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'replies' => (int) $t->reply_count,
                'views' => (int) $t->view_count,
                'url' => '/groups/'.$group->slug.'/d/'.$t->id,
            ]);

        return response()->json([
            'totals' => [
                'members' => (int) $group->member_count,
                'discussions' => (int) Topic::withoutGlobalScope(SocialGroupTopicScope::class)->where('group_id', $group->id)->visible()->count(),
                'posts' => (int) Post::whereIn('topic_id', $topicIds)->count(),
            ],
            'series' => $series,
            'top' => $top,
        ]);
    }
}
