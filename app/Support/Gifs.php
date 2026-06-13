<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * GIF search proxy for the composer's GIF picker. The admin picks a provider
 * (Tenor or Giphy) and supplies an API key in Admin → Settings; the key never
 * reaches the browser. Returns a normalized list of { url, preview, alt }.
 */
class Gifs
{
    public static function provider(): string
    {
        $p = (string) Settings::get('composer.gif_provider', 'none');

        return in_array($p, ['tenor', 'giphy'], true) ? $p : 'none';
    }

    public static function enabled(): bool
    {
        return self::provider() !== 'none' && trim((string) Settings::get('composer.gif_key', '')) !== '';
    }

    /** @return array<int, array{url:string, preview:string, alt:string}> */
    public static function search(string $query, int $limit = 24): array
    {
        if (! self::enabled()) {
            return [];
        }
        $key = trim((string) Settings::get('composer.gif_key', ''));
        $query = trim($query);

        try {
            return self::provider() === 'tenor'
                ? self::tenor($key, $query, $limit)
                : self::giphy($key, $query, $limit);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<int, array{url:string, preview:string, alt:string}> */
    private static function tenor(string $key, string $query, int $limit): array
    {
        $base = 'https://tenor.googleapis.com/v2/';
        $params = [
            'key' => $key,
            'client_key' => 'convoro',
            'limit' => $limit,
            'media_filter' => 'gif,tinygif',
            'contentfilter' => 'medium',
        ];
        if ($query === '') {
            $url = $base.'featured';
        } else {
            $url = $base.'search';
            $params['q'] = $query;
        }
        $res = Http::timeout(8)->get($url, $params)->json();
        $out = [];
        foreach ($res['results'] ?? [] as $r) {
            $full = $r['media_formats']['gif']['url'] ?? null;
            $prev = $r['media_formats']['tinygif']['url'] ?? $full;
            if ($full) {
                $out[] = ['url' => $full, 'preview' => $prev, 'alt' => (string) ($r['content_description'] ?? 'GIF')];
            }
        }

        return $out;
    }

    /** @return array<int, array{url:string, preview:string, alt:string}> */
    private static function giphy(string $key, string $query, int $limit): array
    {
        $base = 'https://api.giphy.com/v1/gifs/';
        $params = ['api_key' => $key, 'limit' => $limit, 'rating' => 'pg-13'];
        if ($query === '') {
            $url = $base.'trending';
        } else {
            $url = $base.'search';
            $params['q'] = $query;
        }
        $res = Http::timeout(8)->get($url, $params)->json();
        $out = [];
        foreach ($res['data'] ?? [] as $r) {
            $full = $r['images']['original']['url'] ?? null;
            $prev = $r['images']['fixed_width']['url'] ?? ($r['images']['preview_gif']['url'] ?? $full);
            if ($full) {
                // Strip Giphy's tracking query string for a clean stored URL.
                $full = strtok($full, '?');
                $out[] = ['url' => $full, 'preview' => $prev, 'alt' => (string) ($r['title'] ?? 'GIF')];
            }
        }

        return $out;
    }
}
