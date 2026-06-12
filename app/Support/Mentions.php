<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Parses @handles out of post text and resolves them to users.
 * A handle matches either the dotted name slug ("riley.west") or the lowercased
 * first name ("riley"). First-name matches may resolve to several users — that is
 * intentional for now (no distinct @username field yet; revisit when handles land).
 */
class Mentions
{
    /** @return Collection<int, User> */
    public static function parse(string $text): Collection
    {
        $handles = self::handles($text);
        if ($handles->isEmpty()) {
            return collect();
        }

        return User::query()->get(['id', 'name'])->filter(function (User $u) use ($handles) {
            $slug = Str::slug($u->name, '.');
            $first = mb_strtolower((string) (preg_split('/\s+/', trim($u->name))[0] ?? ''));

            return $handles->contains($slug) || ($first !== '' && $handles->contains($first));
        })->values();
    }

    /** Lowercased, de-duped handle strings found in the text. */
    private static function handles(string $text): Collection
    {
        if (! preg_match_all('/@([\p{L}0-9_.]{2,40})/u', $text, $m)) {
            return collect();
        }

        return collect($m[1])->map(fn ($h) => mb_strtolower(trim($h, '.')))->filter()->unique()->values();
    }

    /**
     * Turn plain-text @handles in rendered post HTML into styled profile links.
     * Storage keeps the plain "@handle" (so the editor's typeahead round-trips);
     * this runs at display time only. Tag-aware: never rewrites inside existing
     * links, code or pre blocks.
     */
    public static function linkify(string $html): string
    {
        if ($html === '' || ! str_contains($html, '@')) {
            return $html;
        }

        // Resolve only the handles actually present, then index by slug + first name.
        $mentioned = self::parse(strip_tags($html));
        if ($mentioned->isEmpty()) {
            return $html;
        }

        $map = [];
        foreach ($mentioned as $u) {
            $slug = Str::slug($u->name, '.');
            $first = mb_strtolower((string) (preg_split('/\s+/', trim($u->name))[0] ?? ''));
            $map[$slug] = $u;
            if ($first !== '' && ! isset($map[$first])) {
                $map[$first] = $u;
            }
        }

        // Walk the HTML splitting tags from text; only rewrite text outside a/code/pre.
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $skipDepth = 0;
        $out = '';

        foreach ($parts as $part) {
            if ($part !== '' && $part[0] === '<') {
                if (preg_match('/^<\s*(a|code|pre)[\s>\/]/i', $part)) {
                    $skipDepth++;
                } elseif (preg_match('/^<\s*\/\s*(a|code|pre)\s*>/i', $part)) {
                    $skipDepth = max(0, $skipDepth - 1);
                }
                $out .= $part;

                continue;
            }

            $out .= $skipDepth > 0 ? $part : self::replaceHandles($part, $map);
        }

        return $out;
    }

    /** @param array<string,User> $map */
    private static function replaceHandles(string $text, array $map): string
    {
        if (! str_contains($text, '@')) {
            return $text;
        }

        return preg_replace_callback('/@([\p{L}0-9_.]{2,40})/u', function ($m) use ($map) {
            $key = mb_strtolower(trim($m[1], '.'));
            $user = $map[$key] ?? null;

            return $user
                ? '<a class="mention" href="/u/'.$user->id.'">@'.e($user->name).'</a>'
                : $m[0];
        }, $text) ?? $text;
    }
}
