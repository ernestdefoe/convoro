<?php

namespace App\Support;

/**
 * The permission catalog. Permissions are granted to groups; a user's effective
 * permissions = BASELINE (every authenticated user) ∪ their groups' permissions.
 * Admins (users.is_admin) bypass all checks.
 *
 * Category scoping (Flarum-style) reuses the same group permission array: a
 * per-category grant is just a namespaced key `category.{id}.{perm}` living next
 * to the global `{perm}`. When at least one group carries such a key for a
 * (category, perm) pair, that perm becomes *restricted* in that category — only
 * admins + groups explicitly granted it there have it, overriding the global
 * grant. Otherwise the global permission applies. See User::hasPermissionIn().
 */
class Permissions
{
    /** key => [label, category]. */
    public const CATALOG = [
        // Participation
        'category.view' => ['View & read the category', 'Participation'],
        'topic.create' => ['Start topics', 'Participation'],
        'post.reply' => ['Reply to topics', 'Participation'],
        'post.react' => ['React to posts', 'Participation'],
        'post.edit_own' => ['Edit own posts', 'Participation'],
        'post.delete_own' => ['Delete own posts', 'Participation'],
        // Moderation
        'post.edit_any' => ['Edit any post', 'Moderation'],
        'post.delete_any' => ['Delete any post', 'Moderation'],
        'post.move' => ['Move posts between topics', 'Moderation'],
        'post.approve' => ['Approve held posts', 'Moderation'],
        'topic.rename_any' => ['Rename any topic', 'Moderation'],
        'topic.move' => ['Move topics between categories', 'Moderation'],
        'topic.delete_any' => ['Delete any topic', 'Moderation'],
        'topic.lock' => ['Lock / unlock topics', 'Moderation'],
        'topic.pin' => ['Pin / unpin topics', 'Moderation'],
        'user.ban' => ['Ban / suspend members', 'Moderation'],
        // Groups
        'group.create' => ['Create social groups', 'Groups'],
        'group.moderate' => ['Moderate any social group', 'Groups'],
    ];

    /** Granted to every authenticated user, regardless of group. */
    public const BASELINE = ['category.view', 'topic.create', 'post.reply', 'post.react', 'post.edit_own', 'post.delete_own', 'group.create'];

    /**
     * Permissions that can *additionally* be pinned to a single category (via a
     * `category.{id}.{perm}` grant). These are the ones where restricting access
     * per category is meaningful.
     */
    public const SCOPABLE = ['category.view', 'topic.create', 'post.reply'];

    public static function keys(): array
    {
        $keys = array_keys(self::CATALOG);
        foreach (ExtensionManager::permissionAdditions() as $p) {
            $keys[] = $p['key'];
        }

        return array_values(array_unique($keys));
    }

    /** Permission keys granted to every authenticated user (core + extensions). */
    public static function baseline(): array
    {
        $base = self::BASELINE;
        foreach (ExtensionManager::permissionAdditions() as $p) {
            if (! empty($p['baseline'])) {
                $base[] = $p['key'];
            }
        }

        return array_values(array_unique($base));
    }

    /** Whether a permission may be pinned per-category. */
    public static function scopable(string $key): bool
    {
        return in_array($key, self::SCOPABLE, true);
    }

    /**
     * Moderation permission keys — holding any of these makes a non-admin a
     * "staff" member with access to the moderation panel (see EnsureStaff).
     *
     * @return array<string>
     */
    public static function moderationKeys(): array
    {
        return array_keys(array_filter(self::CATALOG, fn ($c) => $c[1] === 'Moderation'));
    }

    /** The per-category namespaced key for a base permission. */
    public static function categoryKey(int $categoryId, string $perm): string
    {
        return "category.{$categoryId}.{$perm}";
    }

    /** Catalog shaped for the admin group editor (core + enabled extensions). */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => [$label, $category]) {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'category' => $category,
                'baseline' => in_array($key, self::BASELINE, true),
                'scopable' => self::scopable($key),
            ];
        }
        foreach (ExtensionManager::permissionAdditions() as $p) {
            $out[] = $p + ['scopable' => false];
        }

        return $out;
    }

    /**
     * Every category-scoped restriction currently in force, as a set of
     * "category.{id}.{perm}" keys drawn from all groups. Memoized per request so
     * hasPermissionIn() stays cheap. A perm is "restricted" in a category iff its
     * namespaced key appears here.
     *
     * @return array<string,bool>
     */
    private static ?array $restricted = null;

    public static function restrictedSet(): array
    {
        if (self::$restricted === null) {
            self::$restricted = [];
            foreach (\App\Models\Group::query()->pluck('permissions') as $perms) {
                foreach ((array) $perms as $p) {
                    if (is_string($p) && str_starts_with($p, 'category.')) {
                        self::$restricted[$p] = true;
                    }
                }
            }
        }

        return self::$restricted;
    }

    /** Drop the memoized restriction set (call after group permissions change). */
    public static function flush(): void
    {
        self::$restricted = null;
    }
}
