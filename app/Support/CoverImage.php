<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Auto-generates a branded SVG cover image for a store/directory listing.
 *
 * Colours are derived deterministically from the slug, and (for GitHub-linked
 * products) real repo metadata — language + stars — is pulled in. SVG keeps the
 * covers crisp at any size with no GD/font dependencies.
 */
class CoverImage
{
    /** Build + store the cover, set $product->image, and persist. Returns the URL. */
    public static function generate(Product $product): ?string
    {
        $meta = self::githubMeta($product);

        $svg = self::svg(
            name: $product->name,
            tagline: (string) ($product->tagline ?: ($meta['description'] ?? '')),
            type: $product->type ?: 'extension',
            language: $meta['language'] ?? null,
            stars: $meta['stars'] ?? null,
            premium: ! $product->isFree(),
            slug: $product->slug,
        );

        $path = 'covers/'.$product->slug.'.svg';
        Storage::disk('public')->put($path, $svg, 'public');

        $url = Storage::disk('public')->url($path);
        $product->forceFill(['image' => $url])->save();

        return $url;
    }

    /** Best-effort GitHub repo metadata (language, stars, description). */
    private static function githubMeta(Product $product): array
    {
        if ($product->source !== 'github' || ! $product->repo) {
            return [];
        }
        try {
            $token = (string) (Settings::get('github.token') ?: config('services.github.token'));
            $req = \Illuminate\Support\Facades\Http::acceptJson()->timeout(8);
            if ($token !== '') {
                $req = $req->withToken($token);
            }
            $res = $req->get('https://api.github.com/repos/'.$product->repo);
            if (! $res->successful()) {
                return [];
            }

            return [
                'language' => $res->json('language'),
                'stars' => (int) $res->json('stargazers_count'),
                'description' => $res->json('description'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function svg(string $name, string $tagline, string $type, ?string $language, ?int $stars, bool $premium, string $slug): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1);

        // Deterministic hue from the slug → two harmonious gradient stops.
        $hue = crc32($slug) % 360;
        $c1 = "hsl({$hue}, 72%, 56%)";
        $c2 = 'hsl('.(($hue + 38) % 360).', 70%, 44%)';
        $glyph = $type === 'theme' ? '🎨' : '🧩';

        // Fit the title size to its length.
        $len = mb_strlen($name);
        $titleSize = $len > 26 ? 52 : ($len > 18 ? 64 : 76);

        $tag = $e(mb_strimwidth($tagline, 0, 96, '…'));
        $title = $e($name);

        // Bottom meta row.
        $badges = [];
        $badges[] = strtoupper($e($type));
        if ($language) {
            $badges[] = $e($language);
        }
        if ($stars) {
            $badges[] = '★ '.number_format($stars);
        }
        $meta = implode('  ·  ', $badges);
        $tier = $premium ? 'PREMIUM' : 'FREE';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="600" viewBox="0 0 1200 600" font-family="Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$c1}"/>
      <stop offset="1" stop-color="{$c2}"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.8" cy="0.15" r="0.9">
      <stop offset="0" stop-color="rgba(255,255,255,0.22)"/>
      <stop offset="1" stop-color="rgba(255,255,255,0)"/>
    </radialGradient>
  </defs>
  <rect width="1200" height="600" fill="url(#g)"/>
  <rect width="1200" height="600" fill="url(#glow)"/>
  <circle cx="1030" cy="120" r="220" fill="rgba(255,255,255,0.06)"/>
  <circle cx="120" cy="540" r="160" fill="rgba(0,0,0,0.08)"/>

  <g transform="translate(80,90)">
    <rect width="96" height="96" rx="22" fill="rgba(255,255,255,0.16)"/>
    <text x="48" y="64" font-size="50" text-anchor="middle">{$glyph}</text>
  </g>
  <text x="824" y="132" text-anchor="end" font-size="22" font-weight="800" letter-spacing="3" fill="rgba(255,255,255,0.85)">{$tier}</text>

  <text x="80" y="330" font-size="{$titleSize}" font-weight="800" fill="#ffffff">{$title}</text>
  <text x="82" y="392" font-size="30" fill="rgba(255,255,255,0.92)">{$tag}</text>

  <text x="80" y="516" font-size="24" font-weight="700" letter-spacing="1" fill="rgba(255,255,255,0.92)">{$meta}</text>
  <text x="1120" y="540" text-anchor="end" font-size="26" font-weight="800" letter-spacing="1" fill="rgba(255,255,255,0.9)">Convoro</text>
</svg>
SVG;
    }
}
