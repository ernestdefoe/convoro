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
        if (! preg_match_all('/@([\p{L}0-9_.]{2,40})/u', $text, $m)) {
            return collect();
        }

        $handles = collect($m[1])->map(fn ($h) => mb_strtolower(trim($h, '.')))->filter()->unique();
        if ($handles->isEmpty()) {
            return collect();
        }

        return User::query()->get()->filter(function (User $u) use ($handles) {
            $slug = Str::slug($u->name, '.');
            $first = mb_strtolower((string) (preg_split('/\s+/', trim($u->name))[0] ?? ''));

            return $handles->contains($slug) || ($first !== '' && $handles->contains($first));
        })->values();
    }
}
