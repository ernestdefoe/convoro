<?php

namespace App\Support;

use App\Models\Category;
use App\Models\User;

/**
 * Resolves which categories a user may not see ("private categories"). A category
 * is private when some group carries a `category.{id}.category.view` grant — that
 * restricts viewing to the permitted groups (plus admins), overriding the global
 * baseline (see User::hasPermissionIn / Permissions). Topic listings and category
 * navigation call this to filter hidden content out.
 *
 * Explicit (not a global Eloquent scope) so background jobs, the admin panel and
 * moderation queries — which pass no actor — keep seeing everything.
 */
class CategoryVisibility
{
    /** @var array<int,array<int>> memo: user id (0 = guest) => hidden category ids */
    private static array $memo = [];

    /**
     * Category ids the user may NOT view. Admins and anyone holding the per-category
     * view grant see everything; guests and outsiders can't see restricted ones.
     *
     * @return array<int>
     */
    public static function hiddenFor(?User $user): array
    {
        $memoKey = $user?->id ?? 0;
        if (array_key_exists($memoKey, self::$memo)) {
            return self::$memo[$memoKey];
        }
        if ($user && $user->is_admin) {
            return self::$memo[$memoKey] = [];
        }

        $restricted = self::restrictedIds();
        if (! $restricted) {
            return self::$memo[$memoKey] = [];
        }

        $hidden = [];
        foreach ($restricted as $catId) {
            $cat = Category::find($catId);
            if ($cat && ! ($user && $user->hasPermissionIn($cat, 'category.view'))) {
                $hidden[] = $catId;
            }
        }

        return self::$memo[$memoKey] = $hidden;
    }

    /**
     * Every category id that restricts viewing for *someone* (a group carries a
     * `category.{id}.category.view` grant), regardless of the current user. Used
     * to keep actor-independent, cached surfaces (featured/trending spotlights,
     * the sitemap, public feeds) clear of any private category.
     *
     * @return array<int>
     */
    public static function restrictedIds(): array
    {
        $ids = [];
        foreach (array_keys(Permissions::restrictedSet()) as $key) {
            if (preg_match('/^category\.(\d+)\.category\.view$/', $key, $m)) {
                $ids[] = (int) $m[1];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Whether the user may view a specific category. */
    public static function canView(?User $user, ?Category $category): bool
    {
        return ! $category || ! in_array((int) $category->id, self::hiddenFor($user), true);
    }

    /**
     * Constrain a topics query to categories the user may see. Topics with no
     * category are always visible. `$column` should be table-qualified when the
     * query joins other tables that also have a `category_id`.
     */
    public static function filterTopics($query, ?User $user, string $column = 'category_id')
    {
        $hidden = self::hiddenFor($user);
        if ($hidden) {
            $query->where(function ($q) use ($column, $hidden) {
                $q->whereNull($column)->orWhereNotIn($column, $hidden);
            });
        }

        return $query;
    }

    /**
     * Drop categories the user can't view from an already-fetched collection
     * (nav lists, pickers, filter dropdowns). Each item must expose an `id`.
     */
    public static function visibleCategories($categories, ?User $user)
    {
        $hidden = self::hiddenFor($user);

        return $hidden
            ? collect($categories)->reject(fn ($c) => in_array((int) ($c['id'] ?? $c->id), $hidden, true))->values()
            : collect($categories);
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
