<?php

namespace App\Support;

use App\Models\Post;

/**
 * Rich "card" content rendered ABOVE a post's body. First-party extensions
 * register a resolver via PostCard::extend() in their service provider's boot();
 * each resolver receives the Post and returns a block of trusted HTML to render
 * before the body (e.g. the TMDB extension's movie/TV info card on the first
 * post of a topic), or null to add nothing.
 *
 * Unlike PostRender (which transforms the body HTML and has no post context),
 * resolvers here get the full Post, so they can key off is_first / topic / etc.
 * The returned HTML is rendered server-side with the page (no extra request),
 * mirroring PostIdentity. The first resolver to return a string wins.
 */
class PostCard
{
    /** @var array<int, callable(Post):?string> */
    private static array $resolvers = [];

    /** Register a card resolver. Call from an extension's boot(). */
    public static function extend(callable $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    /** Resolve the card HTML for a post, or null if no extension provides one. */
    public static function render(Post $post): ?string
    {
        foreach (self::$resolvers as $resolver) {
            $html = $resolver($post);
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }

        return null;
    }
}
