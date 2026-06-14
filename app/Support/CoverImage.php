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
            premium: ! $product->isFree(),
            slug: $product->slug,
            repo: $product->repo ?: ('ernestdefoe/'.$product->slug),
            icon: self::iconInner((string) $product->package),
        );

        $path = 'covers/'.$product->slug.'.svg';
        Storage::disk('public')->put($path, $svg, 'public');

        // Store a HOST-RELATIVE url so covers work on any domain (the absolute
        // url baked in whatever APP_URL happened to be at generation time).
        $url = '/storage/'.$path;
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

    /**
     * Inner SVG markup of an installed extension's icon (so the cover can show
     * a real glyph instead of an initials monogram). Returns null when the
     * extension ships no icon or isn't installed → caller falls back to the
     * monogram. currentColor in the icon is resolved to the cover accent.
     */
    private static function iconInner(string $package): ?string
    {
        if ($package === '') {
            return null;
        }
        $manifest = \App\Support\ExtensionManager::all()[$package] ?? null;
        $svg = $manifest ? \App\Support\ExtensionManager::iconSvgFor($manifest) : null;
        if (! $svg) {
            return null;
        }
        // Keep the icon's own fill/stroke (line-art vs filled differ) and its
        // 24x24 viewBox; just size it to 150px. The caller wraps it in a group
        // whose `color` resolves the icon's currentColor to the cover accent.
        $svg = (string) preg_replace('#\s(width|height)="[^"]*"#i', '', $svg);

        return (string) preg_replace('#<svg\b#i', '<svg width="150" height="150"', $svg, 1);
    }

    private static function svg(string $name, string $tagline, string $type, bool $premium, string $slug, string $repo, ?string $icon = null): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1);
        $mono = "'JetBrains Mono', 'SFMono-Regular', ui-monospace, Menlo, Consolas, monospace";

        // A distinct accent hue per slug across the whole wheel, so each cover
        // reads as its own colour (same dark, on-brand layout — different accent).
        $hue = (int) (crc32($slug) % 360);
        $accent = "hsl({$hue}, 82%, 68%)";
        $accent2 = 'hsl('.(($hue + 22) % 360).', 80%, 60%)';

        // Title: fit size to length, hard-cap to avoid overflow.
        $name = trim(mb_strimwidth($name, 0, 28, '…'));
        $len = mb_strlen($name);
        $titleSize = $len > 22 ? 58 : ($len > 14 ? 72 : 86);
        $title = $e($name);

        $eyebrow = $e(strtoupper('Convoro '.$type)).($premium ? '  ·  PREMIUM' : '');
        $cmd = $e('$ composer require '.$repo);
        $pascal = self::pascal($name);
        $mono2 = self::monogram($name);

        // Prefer the extension's real icon; fall back to an initials monogram.
        $glyph = $icon
            ? '<g transform="translate(890,225)" color="'.$accent.'">'.$icon.'</g>'
            : '<text x="965" y="300" text-anchor="middle" dominant-baseline="central" font-size="150" font-weight="800" font-family="Georgia, \'Times New Roman\', serif" fill="'.$accent.'">'.$mono2.'</text>';

        // Description, wrapped to ≤2 lines.
        $descSvg = '';
        $dy = 408;
        foreach (self::wrapLines($tagline, 52, 2) as $line) {
            $descSvg .= '<text x="64" y="'.$dy.'" font-size="27" font-family="'.$mono.'" fill="rgba(233,233,245,0.62)">'.$e($line).'</text>';
            $dy += 40;
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" font-family="Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#0a0a12"/>
      <stop offset="1" stop-color="#13101f"/>
    </linearGradient>
    <radialGradient id="rglow" cx="0.78" cy="0.42" r="0.5">
      <stop offset="0" stop-color="hsla({$hue}, 85%, 62%, 0.45)"/>
      <stop offset="1" stop-color="hsla({$hue}, 85%, 62%, 0)"/>
    </radialGradient>
    <linearGradient id="acc" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="{$accent}"/>
      <stop offset="1" stop-color="{$accent2}"/>
    </linearGradient>
  </defs>

  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect width="1200" height="630" fill="url(#rglow)"/>
  <rect x="24" y="24" width="1152" height="582" rx="26" fill="none" stroke="rgba(255,255,255,0.09)" stroke-width="1.5"/>

  <text x="64" y="112" font-size="26" font-family="{$mono}" fill="rgba(255,255,255,0.16)">{$cmd}</text>

  <text x="64" y="214" font-size="22" font-weight="700" letter-spacing="5" font-family="{$mono}" fill="{$accent}">{$eyebrow}</text>
  <text x="62" y="316" font-size="{$titleSize}" font-weight="800" fill="#ffffff">{$title}</text>
  <rect x="64" y="344" width="148" height="7" rx="3.5" fill="url(#acc)"/>

  {$descSvg}

  <text x="64" y="514" font-size="24" font-family="{$mono}" fill="rgba(255,255,255,0.14)">&lt;{$pascal} /&gt;</text>
  <text x="64" y="558" font-size="24" font-family="{$mono}" fill="rgba(255,255,255,0.16)">&lt;/convoro.co&gt;<tspan fill="{$accent}"> ▌</tspan></text>

  <circle cx="965" cy="300" r="150" fill="url(#rglow)"/>
  {$glyph}
  <rect x="888" y="392" width="154" height="11" rx="5.5" fill="{$accent}" opacity="0.85"/>
  <rect x="888" y="418" width="100" height="11" rx="5.5" fill="{$accent}" opacity="0.5"/>
</svg>
SVG;
    }

    /** "Google Fonts" → "GoogleFonts" (for the decorative &lt;Component /&gt; line). */
    private static function pascal(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = implode('', array_map('ucfirst', $words));

        return $out !== '' ? mb_strimwidth($out, 0, 24) : 'Extension';
    }

    /** A 1–2 char monogram: initials for multi-word names, else first+second letter ("Aa"). */
    private static function monogram(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) >= 2) {
            return strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }
        $w = $words[0] ?? 'C';

        return mb_strlen($w) >= 2
            ? strtoupper(mb_substr($w, 0, 1)).strtolower(mb_substr($w, 1, 1))
            : strtoupper(mb_substr($w, 0, 1));
    }

    /** Word-wrap text into up to $maxLines lines of ~$perLine chars, ellipsizing overflow. */
    private static function wrapLines(string $text, int $perLine, int $maxLines): array
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return [];
        }

        $lines = [];
        $cur = '';
        foreach (explode(' ', $text) as $word) {
            $try = $cur === '' ? $word : $cur.' '.$word;
            if (mb_strlen($try) <= $perLine) {
                $cur = $try;

                continue;
            }
            $lines[] = $cur;
            $cur = $word;
            if (count($lines) === $maxLines) {
                break;
            }
        }
        if ($cur !== '' && count($lines) < $maxLines) {
            $lines[] = $cur;
        }

        // If text didn't fully fit, ellipsize the last visible line.
        $used = implode(' ', $lines);
        if (mb_strlen($used) < mb_strlen($text)) {
            $last = count($lines) - 1;
            $lines[$last] = rtrim(mb_strimwidth($lines[$last], 0, $perLine - 1)).'…';
        }

        return $lines;
    }
}
