<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Builds the SEO payload shared to the Inertia root view, which renders it as
 * real server-side <head> tags (Inertia is client-rendered, so crawler-visible
 * meta must come from the Blade root — see app.blade.php).
 */
class Seo
{
    /**
     * Merge per-page overrides over site-wide defaults.
     *
     * @param array{title?:string,description?:string,image?:string,type?:string,url?:string,noindex?:bool} $o
     */
    public static function make(array $o = []): array
    {
        $siteName = (string) Settings::get('site.name', 'Convoro');
        $tagline = (string) Settings::get('site.tagline', '');
        $defaultDesc = (string) (Settings::get('seo.description') ?: $tagline);
        $defaultImage = (string) (Settings::get('seo.image') ?: Settings::get('site.logo', ''));

        $title = trim((string) ($o['title'] ?? ''));
        $fullTitle = $title !== '' && $title !== $siteName ? "{$title} · {$siteName}" : $siteName;

        $desc = self::clean($o['description'] ?? $defaultDesc, 200);
        $image = $o['image'] ?? $defaultImage;
        if ($image && ! Str::startsWith($image, ['http://', 'https://'])) {
            $image = url($image);
        }

        $noindex = (bool) ($o['noindex'] ?? false);

        return [
            'title' => $fullTitle,
            'siteName' => $siteName,
            'description' => $desc,
            'image' => $image ?: null,
            'url' => $o['url'] ?? url()->current(),
            'type' => $o['type'] ?? 'website',
            'noindex' => $noindex,
            // Google structured data (JSON-LD). Site-wide WebSite + Organization,
            // plus any per-page schema (e.g. a topic's DiscussionForumPosting).
            'jsonLd' => $noindex ? [] : self::structuredData($siteName, $image, $o['jsonLd'] ?? null),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function structuredData(string $siteName, ?string $image, ?array $page): array
    {
        $siteUrl = url('/');
        $graph = [];
        if (is_array($page)) {
            $graph[] = $page;
        }
        $graph[] = [
            '@context' => 'https://schema.org', '@type' => 'WebSite',
            'name' => $siteName, 'url' => $siteUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $siteUrl.'search?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
        $org = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $siteName, 'url' => $siteUrl];
        if ($image) {
            $org['logo'] = $image;
        }
        $graph[] = $org;

        return $graph;
    }

    /**
     * Build a DiscussionForumPosting schema for a topic, the structured-data
     * type Google recommends for forum threads.
     *
     * @param  array<int,array<string,mixed>>  $comments  [{text, author, url, date}]
     */
    public static function forumPosting(array $o): array
    {
        $siteName = (string) Settings::get('site.name', 'Convoro');
        $siteUrl = url('/');
        // Site fallback image so a posting/comment always satisfies Google's
        // "text, image, or video should be specified" requirement.
        $fallbackImage = self::absUrl((string) (Settings::get('seo.image') ?: Settings::get('site.logo', '')));

        // Author is REQUIRED by Google and must be a named Person with a url —
        // never omit it, even for deleted/anonymous authors.
        $person = function ($name, $url) use ($siteUrl) {
            return [
                '@type' => 'Person',
                'name' => trim((string) $name) !== '' ? (string) $name : 'Member',
                'url' => $url ?: $siteUrl,
            ];
        };

        $url = $o['url'] ?? url()->current();
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => self::clean($o['title'] ?? '', 110) ?: $siteName,
            'url' => $url,
            'author' => $person($o['author']['name'] ?? null, $o['author']['url'] ?? null),
            'datePublished' => $o['datePublished'] ?? now()->toAtomString(),
        ];
        if (! empty($o['dateModified'])) {
            $ld['dateModified'] = $o['dateModified'];
        }

        // text / image / video — at least one is required. Always provide both a
        // text fallback (the headline) and an image fallback so it can't fail.
        $text = self::clean($o['text'] ?? '', 5000);
        $ld['text'] = $text !== '' ? $text : $ld['headline'];
        $image = self::absUrl($o['image'] ?? '') ?: $fallbackImage;
        if ($image) {
            $ld['image'] = $image;
        }

        $stats = [];
        if (isset($o['replyCount'])) {
            $stats[] = ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/CommentAction', 'userInteractionCount' => (int) $o['replyCount']];
        }
        if (isset($o['viewCount'])) {
            $stats[] = ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/ViewAction', 'userInteractionCount' => (int) $o['viewCount']];
        }
        if ($stats) {
            $ld['interactionStatistic'] = $stats;
        }

        $comments = [];
        foreach (array_slice($o['comments'] ?? [], 0, 10) as $c) {
            $ctext = self::clean($c['text'] ?? '', 2000);
            $cimage = self::absUrl($c['image'] ?? '');
            // Skip content-less (image/emoji-only with no extractable text and no
            // image) replies — they'd trip "text, image, or video should be specified".
            if ($ctext === '' && ! $cimage) {
                continue;
            }
            $comment = [
                '@type' => 'Comment',
                'author' => $person($c['author'] ?? null, $c['authorUrl'] ?? null),
                'datePublished' => $c['date'] ?? now()->toAtomString(),
                'url' => $c['url'] ?? $url,
                'text' => $ctext !== '' ? $ctext : '🖼',
            ];
            if ($cimage) {
                $comment['image'] = $cimage;
            }
            $comments[] = $comment;
        }
        if ($comments) {
            $ld['comment'] = $comments;
        }

        return $ld;
    }

    /** First <img> src in some HTML (absolute), or null — for structured-data images. */
    public static function firstImage(?string $html): ?string
    {
        if ($html && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::absUrl($m[1]) ?: null;
        }

        return null;
    }

    /** Make a URL absolute (Google requires absolute URLs in structured data). */
    private static function absUrl(?string $u): string
    {
        $u = trim((string) $u);
        if ($u === '') {
            return '';
        }

        return Str::startsWith($u, ['http://', 'https://']) ? $u : url($u);
    }

    /** Strip HTML + collapse whitespace, truncate for a meta description. */
    public static function clean(?string $text, int $limit = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        return Str::limit($text, $limit);
    }
}
