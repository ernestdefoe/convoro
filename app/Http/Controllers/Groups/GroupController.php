<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Models\Scopes\SocialGroupTopicScope;
use App\Models\SocialGroup;
use App\Models\SocialGroupJoinRequest;
use App\Models\SocialGroupMember;
use App\Models\Topic;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    /** Browse + search groups the actor is allowed to see. */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $q = trim((string) $request->query('q', ''));

        $query = SocialGroup::query()
            ->orderByDesc('is_featured')
            ->orderByDesc('member_count')
            ->orderByDesc('last_active_at');

        $this->scopeVisible($query, $actor);

        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        $groups = $query->paginate(24)->withQueryString();

        [$mine, $pending] = $this->membershipMaps($actor, collect($groups->items())->pluck('id')->all());

        return Inertia::render('Groups/Index', [
            'groups' => [
                'data' => collect($groups->items())->map(fn (SocialGroup $g) => $this->card($g, $mine, $pending))->all(),
                'next' => $groups->nextPageUrl(),
            ],
            'q' => $q,
            'canCreate' => (bool) ($actor && $actor->hasPermission('group.create')),
            'seo' => Seo::make(['title' => __('Groups')]),
        ]);
    }

    /** A single group: header, members, discussions, about. */
    public function show(Request $request, SocialGroup $group): Response
    {
        $actor = $request->user();
        abort_unless($group->isVisibleTo($actor), 404);

        $group->loadCount(['activeMembers as members_total']);
        $group->load(['owner', 'members' => fn ($q) => $q->whereNull('banned_at')->with('user')->orderByRaw("FIELD(role,'owner','moderator','member')")->orderBy('joined_at')->limit(60)]);

        $discussions = Topic::withoutGlobalScope(SocialGroupTopicScope::class)
            ->where('group_id', $group->id)->visible()
            ->with(['user', 'firstPost'])
            ->orderByDesc('is_pinned')->orderByDesc('last_post_at')
            ->paginate(20)->withQueryString();

        $role = $group->roleOf($actor);
        $pending = $actor ? SocialGroupJoinRequest::where('social_group_id', $group->id)->where('user_id', $actor->id)->where('status', 'pending')->exists() : false;

        return Inertia::render('Groups/Show', [
            'group' => $this->detail($group, $actor, $role, $pending),
            'members' => $group->members->map(fn (SocialGroupMember $m) => [
                'id' => $m->user_id,
                'role' => $m->role,
                'user' => Present::avatar($m->user),
            ])->all(),
            'discussions' => [
                'data' => collect($discussions->items())->map(fn (Topic $t) => $this->discussionCard($t))->all(),
                'next' => $discussions->nextPageUrl(),
            ],
            'pendingRequests' => $group->canModerate($actor) ? $this->pendingRequests($group) : [],
            'seo' => Seo::make([
                'title' => $group->name,
                'description' => Str::limit(strip_tags((string) $group->description), 160),
                'noindex' => $group->is_private,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->hasPermission('group.create'), 403);
        abort_if($actor->isBanned(), 403, __('Your account is suspended.'));

        $data = $this->validateGroup($request);

        $group = new SocialGroup;
        $group->user_id = $actor->id;
        $group->fill($this->groupAttributes($data));
        $group->slug = $this->uniqueSlug($group->name);
        $group->member_count = 1;
        $group->last_active_at = now();
        $group->save();

        $group->members()->create([
            'user_id' => $actor->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return redirect('/groups/'.$group->slug)->with('status', __('Group created.'));
    }

    public function update(Request $request, SocialGroup $group): RedirectResponse
    {
        abort_unless($group->canManage($request->user()), 403);
        $data = $this->validateGroup($request);
        $group->fill($this->groupAttributes($data))->save();

        return back()->with('status', __('Group updated.'));
    }

    public function destroy(Request $request, SocialGroup $group): RedirectResponse
    {
        abort_unless($group->canManage($request->user()), 403);
        // Topic/post/member rows cascade via FKs.
        $group->delete();

        return redirect('/groups')->with('status', __('Group deleted.'));
    }

    // -- helpers ------------------------------------------------------------

    /** Restrict a group query to what the actor may see (public + own private). */
    private function scopeVisible($query, $actor): void
    {
        if ($actor && ($actor->is_admin || $actor->hasPermission('group.moderate'))) {
            return;
        }
        $memberIds = $actor
            ? SocialGroupMember::where('user_id', $actor->id)->whereNull('banned_at')->pluck('social_group_id')->all()
            : [];
        $query->where(function ($w) use ($memberIds) {
            $w->where('is_private', false);
            if ($memberIds) {
                $w->orWhereIn('id', $memberIds);
            }
        });
    }

    /** @return array{0:int[],1:int[]} [memberOf, pendingOf] for the listed group ids */
    private function membershipMaps($actor, array $ids): array
    {
        if (! $actor || ! $ids) {
            return [[], []];
        }
        $mine = SocialGroupMember::where('user_id', $actor->id)->whereNull('banned_at')
            ->whereIn('social_group_id', $ids)->pluck('social_group_id')->all();
        $pending = SocialGroupJoinRequest::where('user_id', $actor->id)->where('status', 'pending')
            ->whereIn('social_group_id', $ids)->pluck('social_group_id')->all();

        return [$mine, $pending];
    }

    private function card(SocialGroup $g, array $mine, array $pending): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'slug' => $g->slug,
            'description' => Str::limit(strip_tags((string) $g->description), 140),
            'color' => $g->color,
            'icon' => $g->icon,
            'avatar' => $g->avatar_path,
            'cover' => $g->cover_path,
            'memberCount' => (int) $g->member_count,
            'isPrivate' => (bool) $g->is_private,
            'membershipType' => $g->membership_type,
            'isFeatured' => (bool) $g->is_featured,
            'isMember' => in_array($g->id, $mine, true),
            'isPending' => in_array($g->id, $pending, true),
        ];
    }

    private function detail(SocialGroup $g, $actor, ?string $role, bool $pending): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'slug' => $g->slug,
            'description' => $g->description,
            'color' => $g->color,
            'icon' => $g->icon,
            'avatar' => $g->avatar_path,
            'cover' => $g->cover_path,
            'memberCount' => (int) ($g->members_total ?? $g->member_count),
            'topicCount' => (int) $g->topic_count,
            'isPrivate' => (bool) $g->is_private,
            'membershipType' => $g->membership_type,
            'isFeatured' => (bool) $g->is_featured,
            'createdAt' => optional($g->created_at)->toIso8601String(),
            'owner' => $g->owner ? Present::avatar($g->owner) : null,
            'role' => $role,
            'isMember' => $role !== null,
            'isOwner' => $actor && (int) $actor->id === (int) $g->user_id,
            'isPending' => $pending,
            'canPost' => $g->canPost($actor),
            'canModerate' => $g->canModerate($actor),
            'canManage' => $g->canManage($actor),
        ];
    }

    private function discussionCard(Topic $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'isPinned' => (bool) $t->is_pinned,
            'isLocked' => (bool) $t->is_locked,
            'replyCount' => (int) $t->reply_count,
            'lastPostAt' => optional($t->last_post_at)->toIso8601String(),
            'excerpt' => Present::excerpt(optional($t->firstPost)->body_html ?? '', 140),
            'author' => $t->user ? Present::avatar($t->user) : null,
        ];
    }

    private function pendingRequests(SocialGroup $group): array
    {
        return SocialGroupJoinRequest::where('social_group_id', $group->id)
            ->where('status', 'pending')->with('user')->latest()->limit(50)->get()
            ->map(fn (SocialGroupJoinRequest $r) => [
                'id' => $r->id,
                'user' => Present::avatar($r->user),
                'message' => $r->message,
                'createdAt' => optional($r->created_at)->toIso8601String(),
            ])->all();
    }

    private function validateGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:60'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'is_private' => ['boolean'],
            'membership_type' => ['required', 'in:open,approval,invite'],
        ]);
    }

    private function groupAttributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#5B5BD6',
            'icon' => $data['icon'] ?? null,
            'avatar_path' => $data['avatar'] ?? null,
            'cover_path' => $data['cover'] ?? null,
            'is_private' => (bool) ($data['is_private'] ?? false),
            'membership_type' => $data['membership_type'],
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $i = 2;
        while (SocialGroup::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
