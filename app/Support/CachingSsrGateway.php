<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

/**
 * Caches the Inertia server-side render for pages that are identical for every
 * (guest) visitor — the marketing/brochure pages, which profiling showed render
 * in ~500–700ms because the Vue tree is large.
 *
 * Only the SSR render output is cached, NOT the HTTP response: PHP still runs in
 * full on every request, so sessions, the XSRF-TOKEN cookie and the embedded
 * page props are always fresh. The cache key is a hash of the entire Inertia
 * page object, so it self-invalidates the moment anything changes:
 *   - a deploy bumps the asset `version` → new key,
 *   - a content edit (e.g. the changelog array) changes `props` → new key,
 *   - a signed-in visitor's `auth` prop differs from a guest's → its own key.
 * Stale entries simply age out. A render is only cached when the SSR engine
 * actually returned one (a null — engine down — is never cached).
 */
class CachingSsrGateway implements Gateway
{
    /** Public pages whose render is the same for all guests. */
    private const CACHEABLE = [
        'Marketing/Home',
        'Marketing/Changelog',
        'Marketing/Roadmap',
        'Marketing/About',
        'Marketing/Compare',
        'Marketing/Security',
        'Marketing/Status',
    ];

    private const TTL = 1800; // 30 minutes

    public function __construct(private HttpGateway $inner) {}

    public function dispatch(array $page): ?Response
    {
        $component = $page['component'] ?? '';
        $json = in_array($component, self::CACHEABLE, true) ? json_encode($page) : false;

        // Not a cacheable page (or un-encodable) — render normally.
        if ($json === false) {
            return $this->inner->dispatch($page);
        }

        $key = 'ssr:'.sha1($json);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['head'], $cached['body'])) {
            return new Response($cached['head'], $cached['body']);
        }

        $response = $this->inner->dispatch($page);
        if ($response instanceof Response) {
            Cache::put($key, ['head' => $response->head, 'body' => $response->body], self::TTL);
        }

        return $response;
    }
}
