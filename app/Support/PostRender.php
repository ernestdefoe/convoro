<?php

namespace App\Support;

/**
 * The post-body render pipeline. Core runs mentions + media embeds; first-party
 * extensions register their own HTML transforms (e.g. spoilers, syntax
 * highlighting) via PostRender::extend() in their service provider's boot().
 *
 * Filters receive the already-rendered HTML and return transformed HTML; they
 * run in registration order, after mentions + embeds.
 */
class PostRender
{
    /** @var array<int, callable(string):string> */
    private static array $filters = [];

    /** Register a render filter. Call from an extension's boot(). */
    public static function extend(callable $filter): void
    {
        self::$filters[] = $filter;
    }

    /** Render a post body: mentions → embeds → registered extension filters. */
    public static function render(?string $html): string
    {
        $out = Embeds::render(Mentions::linkify((string) $html));
        foreach (self::$filters as $filter) {
            $out = (string) $filter($out);
        }

        return $out;
    }
}
