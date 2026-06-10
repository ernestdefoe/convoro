<?php

namespace App\Support;

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
            return ['name' => 'Unknown', 'initials' => '?', 'color' => 1];
        }
        $parts = preg_split('/\s+/', trim($user->name)) ?: [];
        $initials = strtoupper(Str::substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? Str::substr(end($parts), 0, 1) : ''));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => $initials,
            'color' => ($user->id % 6) + 1, // av-g1..6
            'avatar' => $user->avatar_path ?: null,
            'url' => '/u/'.$user->id,
        ];
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

    public static function topicCard(Topic $t, ?int $actorId = null): array
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
            'isLive' => $t->is_live,
            'reactions' => $react['summary'],
            'reactionTotal' => $react['total'],
            'lastActivity' => optional($t->last_post_at)->diffForHumans(),
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

    public static function post(Post $p, ?int $actorId = null): array
    {
        $react = self::reactions($p, $actorId);

        return [
            'id' => $p->id,
            'html' => $p->body_html,
            'author' => self::avatar($p->user),
            'isFirst' => $p->is_first,
            'createdAt' => optional($p->created_at)->diffForHumans(),
            'editedAt' => optional($p->edited_at)->diffForHumans(),
            'reactions' => $react['summary'],
            'reactionTotal' => $react['total'],
        ];
    }
}
