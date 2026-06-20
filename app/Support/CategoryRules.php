<?php

namespace App\Support;

use App\Models\Category;

/**
 * Per-category composer rules contributed by extensions. Right now this covers
 * one thing: categories where the post body is OPTIONAL because an extension
 * supplies the content itself (e.g. the TMDB extension renders a movie/TV card
 * as the first post, so the author needn't type anything).
 *
 * Extensions register a resolver in their service provider's boot():
 *   CategoryRules::bodyOptional(fn (int $catId) => self::categoryAllowed($catId));
 */
class CategoryRules
{
    /** @var array<int, callable(int):bool> */
    private static array $bodyOptional = [];

    public static function bodyOptional(callable $resolver): void
    {
        self::$bodyOptional[] = $resolver;
    }

    public static function isBodyOptional(?int $categoryId): bool
    {
        if ($categoryId === null) {
            return false;
        }
        foreach (self::$bodyOptional as $resolver) {
            if ($resolver($categoryId)) {
                return true;
            }
        }

        return false;
    }

    /** Ids of every category where the body is optional — for the composer. */
    public static function bodyOptionalIds(): array
    {
        if (empty(self::$bodyOptional)) {
            return [];
        }

        return Category::pluck('id')
            ->filter(fn ($id) => self::isBodyOptional((int) $id))
            ->map(fn ($id) => (int) $id)
            ->values()->all();
    }
}
