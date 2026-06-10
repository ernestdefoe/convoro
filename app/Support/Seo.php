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

        return [
            'title' => $fullTitle,
            'siteName' => $siteName,
            'description' => $desc,
            'image' => $image ?: null,
            'url' => $o['url'] ?? url()->current(),
            'type' => $o['type'] ?? 'website',
            'noindex' => (bool) ($o['noindex'] ?? false),
        ];
    }

    /** Strip HTML + collapse whitespace, truncate for a meta description. */
    public static function clean(?string $text, int $limit = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        return Str::limit($text, $limit);
    }
}
