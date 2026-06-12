<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Declare an explicit color-scheme on the Horizon dashboard.
 *
 * Horizon ships its own light/dark theming, but its layout has no
 * <meta name="color-scheme">. Without it, Chrome's "Auto Dark Mode for Web
 * Contents" and dark-mode extensions (e.g. Dark Reader) force-darken the page
 * on top of Horizon's own styles â€” which renders dark-on-light and hard to
 * read (and worse when the OS is already dark). Declaring the scheme tells the
 * browser the page handles both modes itself, suppressing the auto-darkening.
 *
 * Attached via config/horizon.php `middleware`, so it only touches Horizon.
 */
class HorizonColorScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $content = $response->getContent();
        if (is_string($content) && str_contains($content, '<head>')) {
            $meta = '<meta name="color-scheme" content="light dark">'
                .'<meta name="darkreader-lock">';
            $response->setContent(str_replace('<head>', '<head>'.$meta, $content));
        }

        return $response;
    }
}
