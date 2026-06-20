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

        // --- Generic link: a rich Open Graph preview card ---
        return self::linkCard($url, $host);
    }

    /**
     * A rich preview card for any other link, built from the page's Open Graph
     * tags. Fetched once and cached by URL (failures cached too, so a dead link
     * isn't re-fetched on every render). Inert until a member posts a link.
     */
    private static function linkCard(string $url, string $host): ?string
    {
        if (! Settings::get('embeds.link_previews', true)) {
            return null;
        }
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }
        if (self::blockedHost($host)) {
            return null;
        }

        $data = \Illuminate\Support\Facades\Cache::remember(
            'ogcard:'.sha1($url),
            now()->addDays(7),
            fn () => self::fetchOg($url)
        );
        if (empty($data) || empty($data['title'])) {
            return null; // no usable metadata → leave the plain link
        }

        $img = ! empty($data['image'])
            ? '<span class="embed-card-img" style="background-image:url(\''.self::e($data['image']).'\')"></span>'
            : '';
        $desc = ! empty($data['description'])
            ? '<span class="embed-card-desc">'.self::e($data['description']).'</span>'
            : '';

        return '<a class="embed-card'.($img ? ' has-img' : '').'" href="'.self::e($url).'" target="_blank" rel="noopener nofollow ugc">'
            .$img
            .'<span class="embed-card-body">'
            .'<span class="embed-card-site">'.self::e($data['site'] ?: $host).'</span>'
            .'<span class="embed-card-title">'.self::e($data['title']).'</span>'
            .$desc
            .'</span></a>';
    }

    /** Fetch + parse Open Graph metadata. Returns ['_fail'=>true] on any problem (cached, so no re-fetch). */
    private static function fetchOg(string $url): array
    {
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(4)
                ->connectTimeout(3)
                ->withHeaders(['User-Agent' => 'ConvoroBot/1.0 (+https://convoro.co; link preview)'])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
            if (! $res->ok() || ! str_contains(strtolower((string) $res->header('Content-Type')), 'text/html')) {
                return ['_fail' => true];
            }

            return self::parseOg(substr($res->body(), 0, 512 * 1024), $url);
        } catch (\Throwable) {
            return ['_fail' => true];
        }
    }

    /** Pull og:title/description/image/site_name (with <title>/meta-description fallbacks). */
    private static function parseOg(string $html, string $base): array
    {
        $meta = function (string $prop) use ($html): ?string {
            $p = preg_quote($prop, '#');
            if (preg_match('#<meta[^>]+(?:property|name)=["\']'.$p.'["\'][^>]*?content=["\']([^"\']*)["\']#i', $html, $m)) {
                return $m[1];
            }
            if (preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]*?(?:property|name)=["\']'.$p.'["\']#i', $html, $m)) {
                return $m[1];
            }

            return null;
        };
        $clean = fn (?string $v) => $v !== null ? trim(html_entity_decode($v, ENT_QUOTES | ENT_HTML5)) : null;

        $title = $clean($meta('og:title'))
            ?: (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m) ? $clean($m[1]) : null);
        $desc = $clean($meta('og:description')) ?: $clean($meta('description'));
        $image = $clean($meta('og:image') ?? $meta('og:image:url') ?? $meta('twitter:image'));

        return [
            'title' => $title ? mb_substr($title, 0, 160) : null,
            'description' => $desc ? mb_substr($desc, 0, 280) : null,
            'image' => $image ? self::absoluteUrl($image, $base) : null,
            'site' => $clean($meta('og:site_name')),
        ];
    }

    /** Resolve a possibly-relative og:image against the page URL. */
    private static function absoluteUrl(string $img, string $base): ?string
    {
        if (preg_match('#^https?://#i', $img)) {
            return $img;
        }
        $b = parse_url($base);
        if (empty($b['scheme']) || empty($b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($img, '//')) {
            return $b['scheme'].':'.$img;
        }

        return $origin.'/'.ltrim($img, '/');
    }

    /** Block loopback/internal hosts (basic SSRF guard) before fetching. */
    private static function blockedHost(string $host): bool
    {
        $h = strtolower($host);
        if ($h === 'localhost' || str_ends_with($h, '.localhost') || str_ends_with($h, '.local') || str_ends_with($h, '.internal')) {
            return true;
        }
        // Literal IP, or one the host resolves to, must be a public address.
        $ip = filter_var($h, FILTER_VALIDATE_IP) ? $h : @gethostbyname($h);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP) && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        return false;
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
