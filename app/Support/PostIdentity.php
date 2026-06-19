<?php

namespace App\Support;

use App\Models\Post;

/**
 * Lets an extension override the *displayed* author identity of a forum post —
 * e.g. a role-play character instead of the posting account. Reusable + inert:
 * it does nothing until an extension registers a resolver in its boot(), exactly
 * like App\Support\PostRender.
 *
 * A resolver receives the Post and the default author shape (from
 * Present::avatar) and returns either an overriding author array (same shape) or
 * null to defer to the next resolver / the default.
 */
class PostIdentity
{
    /** @var array<callable(Post, array): ?array> */
    private static array $resolvers = [];

    /** Register a resolver (call from an extension ServiceProvider boot()). */
    public static function extend(callable $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    /**
     * Resolve the identity for a post. Returns the first non-null override, or
     * the unchanged default author when no resolver claims the post.
     *
     * @param  array  $author  default author shape from Present::avatar()
     * @return array
     */
    public static function resolve(Post $post, array $author): array
    {
        foreach (self::$resolvers as $resolver) {
            $override = $resolver($post, $author);
            if (is_array($override)) {
                return $override + $author; // keep any keys the override omits
            }
        }

        return $author;
    }

    public static function hasResolvers(): bool
    {
        return self::$resolvers !== [];
    }

    /** Test/teardown hook. */
    public static function reset(): void
    {
        self::$resolvers = [];
    }
}
