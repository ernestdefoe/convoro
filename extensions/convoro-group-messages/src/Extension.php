<?php

namespace Convoro\Ext\GroupMessages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Group Messages — multi-participant conversations as a first-party extension.
 * Core DMs stay 1:1; this adds its own group tables + API, surfaced by a
 * "Groups" panel the bundle injects into the forum header. No core changes.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->prefix('api/ext/gm')->group(function () {
            Route::get('/groups', [self::class, 'listGroups']);
            Route::post('/groups', [self::class, 'createGroup']);
            Route::get('/groups/{group}/messages', [self::class, 'messages']);
            Route::post('/groups/{group}/messages', [self::class, 'postMessage']);
        });
    }

    private static function uid(): int
    {
        return (int) auth()->id();
    }

    private static function isMember(int $groupId): bool
    {
        return DB::table('gm_group_user')->where('group_id', $groupId)->where('user_id', self::uid())->exists();
    }

    public static function listGroups(): array
    {
        $uid = self::uid();
        $groupIds = DB::table('gm_group_user')->where('user_id', $uid)->pluck('group_id');

        return DB::table('gm_groups')->whereIn('id', $groupIds)
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get()
            ->map(function ($g) {
                $members = DB::table('gm_group_user')
                    ->join('users', 'users.id', '=', 'gm_group_user.user_id')
                    ->where('gm_group_user.group_id', $g->id)
                    ->pluck('users.name')->all();

                return ['id' => $g->id, 'title' => $g->title, 'members' => $members];
            })->all();
    }

    public static function createGroup(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'members' => ['nullable', 'string', 'max:500'], // comma-separated usernames
        ]);

        $uid = self::uid();
        $groupId = DB::table('gm_groups')->insertGetId([
            'title' => $data['title'], 'creator_id' => $uid,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userIds = [$uid];
        foreach (array_filter(array_map('trim', explode(',', $data['members'] ?? ''))) as $name) {
            $u = DB::table('users')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if ($u) {
                $userIds[] = $u->id;
            }
        }
        foreach (array_unique($userIds) as $id) {
            DB::table('gm_group_user')->insert(['group_id' => $groupId, 'user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['id' => $groupId];
    }

    public static function messages(int $group): array
    {
        abort_unless(self::isMember($group), 403);

        return DB::table('gm_messages')
            ->join('users', 'users.id', '=', 'gm_messages.user_id')
            ->where('gm_messages.group_id', $group)
            ->orderBy('gm_messages.id')
            ->limit(200)
            ->get(['gm_messages.id', 'gm_messages.body', 'gm_messages.user_id', 'users.name', 'gm_messages.created_at'])
            ->map(fn ($m) => [
                'id' => $m->id, 'body' => $m->body, 'author' => $m->name,
                'mine' => (int) $m->user_id === self::uid(),
                'at' => \Illuminate\Support\Carbon::parse($m->created_at)->diffForHumans(),
            ])->all();
    }

    public static function postMessage(Request $request, int $group): array
    {
        abort_unless(self::isMember($group), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $id = DB::table('gm_messages')->insertGetId([
            'group_id' => $group, 'user_id' => self::uid(),
            'body' => $data['body'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gm_groups')->where('id', $group)->update(['last_message_at' => now()]);

        return ['id' => $id];
    }
}
