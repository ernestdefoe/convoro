<?php

namespace App\Support;

/**
 * Turns a standalone media link in a post into a real embed — YouTube, Vimeo,
 * Spotify (safe responsive iframes, no external JS) and X/Twitter, Facebook,
 * Instagram (the provider's own markup, hydrated client-side by their SDK).
 *
 * Only a paragraph that is *just* an auto-linked URL becomes an embed, so inline
 * links inside a sentence are left untouched. Everything is whitelisted by host.
 */
class Embeds
{
    public static function render(?string $html): string
    {
        $html = (string) $html;
        if ($html === '' || ! Settings::get('embeds.enabled', true)) {
            return $html;
        }

        // A paragraph whose only content is a single link (the editor auto-links
        // pasted URLs). Replace it with an embed when the host is supported.
        return (string) preg_replace_callback(
            '#<p>\s*<a\b[^>]*href="([^"]+)"[^>]*>.*?</a>\s*</p>#is',
            function (array $m) {
                $embed = self::embedFor(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));

                return $embed ?? $m[0];
            },
            $html
        );
    }

    /** Whether a rendered body contains any JS-SDK embeds (so the page loads them). */
    public static function needsSdk(string $html): bool
    {
        return str_contains($html, 'twitter-tweet')
            || str_contains($html, 'fb-post')
            || str_contains($html, 'fb-video')
            || str_contains($html, 'instagram-media');
    }

    private static function embedFor(string $url): ?string
    {
        $u = parse_url($url);
        if (! $u || empty($u['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('#^www\.#', '', $u['host']));
        $path = $u['path'] ?? '';
        parse_str($u['query'] ?? '', $q);

        // --- YouTube ---
        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true) && ! empty($q['v'])) {
            return self::video('https://www.youtube-nocookie.com/embed/'.self::id($q['v']));
        }
        if ($host === 'youtu.be' && ($id = trim($path, '/')) !== '') {
            return self::video('https://www.youtube-nocookie.com/embed/'.self::id($id));
        }
        if ($host === 'youtube.com' && preg_match('#^/shorts/([\w-]+)#', $path, $mm)) {
            return self::video('https://www.youtube-nocookie.com/embed/'.self::id($mm[1]));
        }

        // --- Vimeo ---
        if ($host === 'vimeo.com' && preg_match('#^/(\d+)#', $path, $mm)) {
            return self::video('https://player.vimeo.com/video/'.$mm[1]);
        }

        // --- Spotify ---
        if (in_array($host, ['open.spotify.com', 'spotify.com'], true)
            && preg_match('#^/(track|album|playlist|episode|show)/([\w]+)#', $path, $mm)) {
            $h = $mm[1] === 'track' || $mm[1] === 'episode' ? 152 : 352;

            return '<div class="embed-spotify"><iframe loading="lazy" src="https://open.spotify.com/embed/'
                .$mm[1].'/'.$mm[2].'" width="100%" height="'.$h.'" frameborder="0" allow="encrypted-media" '
                .'style="border-radius:12px"></iframe></div>';
        }

        // --- X / Twitter (hydrated by widgets.js) ---
        if (in_array($host, ['twitter.com', 'x.com', 'mobile.twitter.com'], true)
            && preg_match('#^/[^/]+/status/\d+#', $path)) {
            return '<blockquote class="twitter-tweet" data-dnt="true"><a href="'.self::e($url).'"></a></blockquote>';
        }

        // --- Instagram (hydrated by instgrm) ---
        if (in_array($host, ['instagram.com'], true) && preg_match('#^/(p|reel|tv)/[\w-]+#', $path)) {
            $perma = 'https://www.instagram.com'.rtrim($path, '/').'/';

            return '<blockquote class="instagram-media" data-instgrm-permalink="'.self::e($perma).'" data-instgrm-version="14"></blockquote>';
        }

        // --- Facebook (hydrated by the FB SDK) ---
        if (in_array($host, ['facebook.com', 'fb.watch'], true)) {
            $kind = (str_contains($path, '/videos/') || $host === 'fb.watch') ? 'fb-video' : 'fb-post';

            return '<div class="'.$kind.'" data-href="'.self::e($url).'" data-width="500"></div>';
        }

        return null;
    }

    /** A 16:9 responsive iframe wrapper. */
    private static function video(string $src): string
    {
        return '<div class="embed-video"><iframe loading="lazy" src="'.self::e($src).'" '
            .'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            .'allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
    }

    private static function id(string $v): string
    {
        return preg_replace('/[^\w-]/', '', $v);
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
