<?php

namespace App\Http\Controllers;

use App\Models\ProfilePost;
use App\Models\User;
use App\Notifications\ProfileWallNotification;
use App\Support\Content;
use App\Support\Notifier;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    /** Typeahead for @mentions in the editor. Returns handle + avatar by name. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->whereLike('name', "%{$q}%", caseSensitive: false))
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json($users->map(function (User $u) {
            $a = Present::avatar($u);

            return [
                'id' => $a['handle'],   // the handle inserted into the post
                'uid' => $u->id,        // numeric id (e.g. to start a DM)
                'name' => $a['name'],
                'avatar' => $u->avatar_path,
                'initials' => $a['initials'],
                'color' => $a['color'],
            ];
        }));
    }

    /** Public profile: header, stats, recent activity, and the wall. */
    public function show(Request $request, User $user): Response
    {
        $actorId = $request->user()?->id;

        $recentTopics = $user->topics()->visible()->latest()->limit(8)->get()
            ->map(fn ($t) => ['title' => $t->title, 'url' => '/t/'.$t->slug, 'when' => optional($t->created_at)->diffForHumans()]);

        $wall = $user->profilePosts()->with('author')->latest()->limit(30)->get()
            ->map(fn ($p) => Present::profilePost($p, $actorId));

        $a = Present::avatar($user);

        return Inertia::render('Profile/Show', [
            'profile' => [
                'id' => $user->id,
                'name' => $a['name'],
                'bio' => $user->bio,
                'avatar' => $user->avatar_path,
                'cover' => $user->cover_path,
                'initials' => $a['initials'],
                'color' => $a['color'],
                'staff' => $a['staff'],
                'joined' => optional($user->created_at)?->format('F Y'),
                'isAdmin' => (bool) $user->is_admin,
                'isSelf' => $actorId === (int) $user->id,
                // Trust badge — shown for every level on non-admin members
                // (admins show their Admin badge instead).
                'trust' => (\App\Support\TrustLevels::enabled() && ! $user->is_admin)
                    ? ['level' => (int) $user->trust_level, 'label' => \App\Support\TrustLevels::label((int) $user->trust_level)]
                    : null,
                'fediHandle' => \App\Support\Federation::enabled()
                    ? ($user->is_federated ? $user->federated_handle : \App\Support\Federation::userHandle($user))
                    : null,
                'fediUrl' => \App\Support\Federation::enabled()
                    ? ($user->is_federated ? $user->federated_actor : \App\Support\Federation::userActorUrl($user))
                    : null,
                // Primary social-group badge — only PUBLIC groups are surfaced, so a
                // private-group membership is never disclosed on a public profile.
                'primaryGroup' => (function () use ($user) {
                    $pg = optional($user->primarySocialGroup)->group;

                    return ($pg && ! $pg->is_private)
                        ? ['name' => $pg->name, 'slug' => $pg->slug, 'color' => $pg->color]
                        : null;
                })(),
            ],
            'stats' => [
                'topics' => $user->topics()->count(),
                'posts' => $user->posts()->count(),
            ],
            'recentTopics' => $recentTopics,
            'wall' => $wall,
        ]);
    }

    /** Update the current user's profile details (bio + already-uploaded image URLs). */
    public function updateDetails(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'cover' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->user()->update([
            'bio' => $data['bio'] ?? null,
            'avatar_path' => $data['avatar'] ?? null,
            'cover_path' => $data['cover'] ?? null,
        ]);

        return back();
    }

    /** Post a message on a user's wall. */
    public function storeWall(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['body_html' => ['required', 'string', 'max:20000']]);

        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, __('Empty post.'));

        $post = ProfilePost::create([
            'profile_user_id' => $user->id,
            'author_id' => $request->user()->id,
            'body_html' => $html,
        ]);

        // Notify the profile owner (unless posting on your own wall).
        if ((int) $user->id !== (int) $request->user()->id) {
            Notifier::send($user, new ProfileWallNotification($post));
        }

        return back();
    }

    /** Delete a wall post (the author or the profile owner). */
    public function destroyWall(Request $request, ProfilePost $profilePost): RedirectResponse
    {
        $uid = (int) $request->user()->id;
        abort_unless($uid === (int) $profilePost->author_id || $uid === (int) $profilePost->profile_user_id, 403);

        $profilePost->delete();

        return back();
    }
}
