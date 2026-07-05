<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/** Shapes Eloquent models into the plain prop arrays the Vue frontend consumes. */
class Present
{
    public static function avatar(?User $user): array
    {
        if (! $user) {
            return ['name' => __('Unknown'), 'initials' => '?', 'color' => 1];
        }

        // Render-time safety net: even if a malformed name slipped into the DB
        // before validation existed, it can never surface as a bare diamond.
        $name = Username::display($user->name, $user->id);
        $handle = Str::slug($name, '.') ?: 'member-'.$user->id;

        return [
            'id' => $user->id,
            'name' => $name,
            'handle' => $handle,
            'initials' => Username::initials($user->name, $user->id),
            'color' => ($user->id % 6) + 1, // av-g1..6
            'avatar' => $user->avatar_path ?: null,
            'url' => '/u/'.$user->id,
            'online' => $user->last_seen_at !== null && $user->last_seen_at >= now()->subMinutes(5),
            'staff' => self::staffBadge($user),
            'fedi' => $user->is_federated ? ($user->federated_handle ?: null) : null,
            'fediUrl' => $user->is_federated ? $user->federated_actor : null,
        ];
    }

    /** Seeded system groups (admin/moderator), memoized per request. */
    private static ?array $systemGroups = null;

    private static function systemGroup(string $key): ?\App\Models\Group
    {
        self::$systemGroups ??= \App\Models\Group::whereNotNull('key')->get()->keyBy('key')->all();

        return self::$systemGroups[$key] ?? null;
    }

    /**
     * The staff badge to show under a user's avatar: {name, color}, or null.
     * Admins get the (editable) Admin group's color; otherwise the highest
     * `priority` staff group the user belongs to — only when `groups` is already
     * eager-loaded, so this never triggers an N+1 query per avatar.
     */
    public static function staffBadge(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        if ($user->is_admin) {
            $g = self::systemGroup('admin');

            return ['name' => $g->name ?? 'Admin', 'color' => $g->color ?? '#5b5bd6'];
        }

        if ($user->relationLoaded('groups')) {
            $staff = $user->groups->where('is_staff', true)->sortByDesc('priority')->first();
            if ($staff) {
                return ['name' => $staff->name, 'color' => $staff->color];
            }
        }

        return null;
    }

    /** Shape a profile wall post for the frontend. */
    public static function profilePost(\App\Models\ProfilePost $p, ?int $actorId = null): array
    {
        return [
            'id' => $p->id,
            'html' => $p->body_html,
            'author' => self::avatar($p->author),
            'createdAt' => optional($p->created_at)->diffForHumans(),
            'canDelete' => $actorId !== null
                && ($actorId === (int) $p->author_id || $actorId === (int) $p->profile_user_id),
        ];
    }

    public static function excerpt(?string $html, int $len = 160): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES));

        return Str::limit($text, $len);
    }

    /** Group a post's reactions into [{emoji,count,mine}] + total. */
    public static function reactions($post, ?int $actorId): array
    {
        if (! $post || ! $post->relationLoaded('reactions')) {
            return ['summary' => [], 'total' => 0];
        }
        $groups = $post->reactions->groupBy('emoji')->map(fn ($r, $emoji) => [
            'emoji' => $emoji,
            'count' => $r->count(),
            'mine' => $actorId ? $r->contains('user_id', $actorId) : false,
        ])->sortByDesc('count')->values();

        return ['summary' => $groups->all(), 'total' => $post->reactions->count()];
    }

    public static function topicCard(Topic $t, ?int $actorId = null, bool $isNew = false, ?bool $isLive = null): array
    {
        $first = $t->relationLoaded('firstPost') ? $t->firstPost : null;
        $react = self::reactions($first, $actorId);

        return [
            'id' => $t->id,
            'title' => $t->title,
            'slug' => $t->slug,
            'excerpt' => self::excerpt($first?->body_html),
            'cover' => $t->cover_image,
            'author' => self::avatar($t->user),
            'category' => $t->category ? [
                'name' => $t->category->name, 'slug' => $t->category->slug,
                'color' => $t->category->color, 'icon' => $t->category->icon,
            ] : null,
            'tags' => $t->relationLoaded('tags') ? $t->tags->map(fn ($tag) => [
                'name' => $tag->name, 'slug' => $tag->slug, 'color' => $tag->color,
            ])->all() : [],
            'replyCount' => $t->reply_count,
            'viewCount' => $t->view_count,
            'isPinned' => $t->is_pinned,
            'isLive' => $isLive ?? (bool) $t->is_live,
            'isNew' => $isNew,
            'reactions' => $react['summary'],
            'reactionTotal' => $react['total'],
            'lastActivity' => optional($t->last_post_at)->diffForHumans(),
        ];
    }

    /**
     * Given a list of topics and the current user, return the set of topic ids
     * that have unread activity (a post newer than the user last opened them, or
     * — for never-opened topics — newer than when the user joined). Guests: none.
     *
     * @param  iterable<\App\Models\Topic>  $topics
     * @return array<int>
     */
    /** Build the poll payload for a topic: options, counts, percentages, my votes. */
    public static function poll(\App\Models\Poll $poll, ?int $userId): array
    {
        $poll->loadMissing('options');
        $counts = \Illuminate\Support\Facades\DB::table('poll_votes')->where('poll_id', $poll->id)
            ->selectRaw('option_id, count(*) as c')->groupBy('option_id')->pluck('c', 'option_id');
        $total = (int) $counts->sum();
        $myVotes = $userId
            ? \Illuminate\Support\Facades\DB::table('poll_votes')->where('poll_id', $poll->id)->where('user_id', $userId)->pluck('option_id')->map(fn ($i) => (int) $i)->all()
            : [];
        $voters = (int) \Illuminate\Support\Facades\DB::table('poll_votes')->where('poll_id', $poll->id)->distinct('user_id')->count('user_id');

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'multiple' => $poll->max_choices > 1,
            'maxChoices' => (int) $poll->max_choices,
            'closesAt' => optional($poll->closes_at)->toIso8601String(),
            'closesAtLabel' => $poll->closes_at ? $poll->closes_at->diffForHumans() : null,
            'closed' => $poll->isClosed(),
            'totalVotes' => $total,
            'voterCount' => $voters,
            'myVotes' => $myVotes,
            'hasVoted' => $myVotes !== [],
            'options' => $poll->options->map(fn ($o) => [
                'id' => $o->id,
                'text' => $o->text,
                'votes' => (int) ($counts[$o->id] ?? 0),
                'percent' => $total > 0 ? (int) round(((int) ($counts[$o->id] ?? 0)) / $total * 100) : 0,
            ])->all(),
        ];
    }

    public static function unreadTopicIds(iterable $topics, $user): array
    {
        if (! $user) {
            return [];
        }
        $topics = collect($topics);
        $ids = $topics->pluck('id')->all();
        if (! $ids) {
            return [];
        }
        $reads = \Illuminate\Support\Facades\DB::table('topic_reads')
            ->where('user_id', $user->id)->whereIn('topic_id', $ids)
            ->pluck('last_read_at', 'topic_id');
        $joined = $user->created_at;
        // For never-opened topics, only flag genuinely recent activity — this also
        // avoids marking the whole forum "new" the first time the feature ships.
        $recentCutoff = now()->subDays(30);
        $freshBaseline = $joined && $joined->gt($recentCutoff) ? $joined : $recentCutoff;

        $new = [];
        foreach ($topics as $t) {
            $last = $t->last_post_at;
            if (! $last) {
                continue;
            }
            $last = $last instanceof \Carbon\CarbonInterface ? $last : \Illuminate\Support\Carbon::parse($last);
            $baseline = isset($reads[$t->id]) ? \Illuminate\Support\Carbon::parse($reads[$t->id]) : $freshBaseline;
            if ($last->gt($baseline)) {
                $new[] = (int) $t->id;
            }
        }

        return $new;
    }

    /** A single direct message. */
    public static function message(Message $m, int $actorId): array
    {
        return [
            'id' => $m->id,
            'html' => Embeds::render(Mentions::linkify($m->body_html)),
            'detectedLocale' => $m->detected_locale,
            'author' => self::avatar($m->user),
            'createdAt' => optional($m->created_at)->diffForHumans(),
            'mine' => (int) $m->user_id === $actorId,
        ];
    }

    /** A conversation row for the inbox (1:1 or group). Assumes participants + lastMessage loaded. */
    public static function conversation(Conversation $c, int $actorId): array
    {
        $others = $c->participants->where('id', '!=', $actorId)->values();
        $last = $c->relationLoaded('lastMessage') ? $c->lastMessage : null;
        $me = $c->participants->firstWhere('id', $actorId);
        $lastRead = $me?->pivot?->last_read_at;
        $unread = $last
            && (int) $last->user_id !== $actorId
            && (! $lastRead || $last->created_at->gt($lastRead));

        return [
            'id' => $c->id,
            'title' => $c->is_group ? ($c->title ?: $others->pluck('name')->join(', ')) : ($others->first()?->name ?? 'Conversation'),
            'avatar' => self::avatar($others->first()),
            'excerpt' => $last ? self::excerpt($last->body_html, 80) : 'No messages yet',
            'time' => optional($c->last_message_at)->diffForHumans(),
            'unread' => $unread,
        ];
    }

    /** Shape a stored DatabaseNotification for the frontend (merges its data payload). */
    public static function notification(DatabaseNotification $n): array
    {
        $data = is_array($n->data) ? $n->data : (array) json_decode((string) $n->data, true);

        return array_merge([
            'id' => $n->id,
            'read' => $n->read_at !== null,
            'time' => optional($n->created_at)->diffForHumans(),
        ], $data);
    }

    public static function post(Post $p, ?int $actorId = null, ?User $actor = null): array
    {
        $react = self::reactions($p, $actorId);

        $own = $actor && (int) $p->user_id === (int) $actor->id;
        $canEdit = $actor && ($own ? $actor->hasPermission('post.edit_own') : $actor->hasPermission('post.edit_any'));
        $canDelete = $actor && ($own ? $actor->hasPermission('post.delete_own') : $actor->hasPermission('post.delete_any'));

        return [
            'id' => $p->id,
            'number' => $p->number !== null ? (int) $p->number : null,
            'html' => PostRender::render($p->body_html),
            'cardHtml' => PostCard::render($p),
            'detectedLocale' => $p->detected_locale,
            'held' => (bool) ($p->hidden ?? false),
            'author' => PostIdentity::resolve($p, self::avatar($p->user)),
            'isFirst' => $p->is_first,
            'isAi' => (bool) ($p->is_ai ?? false),
            'createdAt' => optional($p->created_at)->diffForHumans(),
            'editedAt' => optional($p->edited_at)->diffForHumans(),
            'reactions' => $react['summary'],
            'reactionTotal' => $react['total'],
            'canEdit' => (bool) $canEdit,
            'canDelete' => (bool) $canDelete,
            'canMove' => (bool) ($actor && $actor->is_admin && ! $p->is_first),
        ];
    }
}
