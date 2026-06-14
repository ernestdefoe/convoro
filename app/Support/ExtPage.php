<?php

namespace App\Support;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders a server-built extension page INSIDE the real forum shell (AppLayout),
 * via a thin Inertia "frame" page. This gives extension pages the exact same
 * header, footer and theme as the rest of the forum — no hand-rolled chrome to
 * drift out of sync.
 *
 * Extensions pass plain body HTML plus optional page-scoped CSS and JS (the JS is
 * injected and executed after the body mounts, so existing vanilla handlers keep
 * working). The csrf-token meta is already present in the shell.
 */
class ExtPage
{
    public static function render(string $title, string $bodyHtml, string $css = '', string $js = ''): Response
    {
        // Every page gets `csrf` + a ready-made `H` headers object, matching the
        // old standalone behaviour so extension JS doesn't repeat the boilerplate.
        if ($js !== '') {
            $prelude = "var csrf=(document.querySelector('meta[name=csrf-token]')||{}).content||'';"
                ."var H={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};";
            $js = $prelude.$js;
        }

        return Inertia::render('Ext/Frame', [
            'title' => $title,
            'bodyHtml' => $bodyHtml,
            'css' => $css,
            'js' => $js,
        ]);
    }
}
