<?php

namespace App\Support;

/**
 * The permission catalog. Permissions are granted to groups; a user's effective
 * permissions = BASELINE (every authenticated user) ∪ their groups' permissions.
 * Admins (users.is_admin) bypass all checks.
 */
class Permissions
{
    /** key => [label, category]. */
    public const CATALOG = [
        'topic.create' => ['Start topics', 'Participation'],
        'post.reply' => ['Reply to topics', 'Participation'],
        'post.react' => ['React to posts', 'Participation'],
        'post.edit_own' => ['Edit own posts', 'Participation'],
        'post.delete_own' => ['Delete own posts', 'Participation'],
        'post.edit_any' => ['Edit any post', 'Moderation'],
        'post.delete_any' => ['Delete any post', 'Moderation'],
        'topic.delete_any' => ['Delete any topic', 'Moderation'],
        'topic.lock' => ['Lock / unlock topics', 'Moderation'],
    ];

    /** Granted to every authenticated user, regardless of group. */
    public const BASELINE = ['topic.create', 'post.reply', 'post.react', 'post.edit_own', 'post.delete_own'];

    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** Catalog shaped for the admin group editor. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => [$label, $category]) {
            $out[] = ['key' => $key, 'label' => $label, 'category' => $category, 'baseline' => in_array($key, self::BASELINE, true)];
        }

        return $out;
    }
}
