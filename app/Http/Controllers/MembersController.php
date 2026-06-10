<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    /** Browsable, searchable member directory. */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), ['newest', 'name', 'posts'], true) ? $request->query('sort') : 'newest';

        $users = User::query()
            ->withCount('posts')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'posts', fn ($query) => $query->orderByDesc('posts_count'))
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('id'))
            ->paginate(24)->withQueryString();

        return Inertia::render('Members/Index', [
            'q' => $q,
            'sort' => $sort,
            'members' => [
                'data' => collect($users->items())->map(fn (User $u) => self::card($u)),
                'next' => $users->nextPageUrl(),
            ],
            'total' => User::count(),
        ]);
    }

    /** Top members by reputation (posts + reactions received on their posts). */
    public function leaderboard(): Response
    {
        $reactions = DB::table('reactions')
            ->join('posts', 'posts.id', '=', 'reactions.post_id')
            ->select('posts.user_id', DB::raw('count(*) as c'))
            ->groupBy('posts.user_id')
            ->pluck('c', 'user_id');

        $users = User::query()->withCount(['posts', 'topics'])->get();

        $ranked = $users
            ->map(function (User $u) use ($reactions) {
                $received = (int) ($reactions[$u->id] ?? 0);
                $score = $u->posts_count + $received * 2 + $u->topics_count;

                return self::card($u) + [
                    'posts' => $u->posts_count,
                    'topics' => $u->topics_count,
                    'reactionsReceived' => $received,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->take(50);

        return Inertia::render('Members/Leaderboard', [
            'members' => $ranked,
        ]);
    }

    private static function card(User $u): array
    {
        return Present::avatar($u) + [
            'bio' => $u->bio,
            'posts' => $u->posts_count ?? 0,
            'joined' => optional($u->created_at)->format('M Y'),
            'isAdmin' => (bool) $u->is_admin,
        ];
    }
}
