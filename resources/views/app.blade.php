<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Support\Settings::get('theme.mode', 'light') }}" data-post-style="{{ \App\Support\Settings::get('theme.post_style', 'card') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Apply the visitor's saved light/dark choice before paint (no flash).
             Reads a domain-scoped cookie so the choice carries across
             convoro.co and community.convoro.co; falls back to localStorage. --}}
        <script>(function(){try{var m=document.cookie.split('; ').find(function(c){return c.indexOf('convoro_theme=')===0;});var t=m?decodeURIComponent(m.split('=')[1]):localStorage.getItem('convoro_theme');if(t==='dark'||t==='light'){document.documentElement.dataset.theme=t;}}catch(e){}})();</script>

        @php($seo = $page['props']['seo'] ?? [])
        <title inertia>{{ $seo['title'] ?? config('app.name', 'Convoro') }}</title>

        {{-- SEO / social meta (server-rendered for crawlers; Inertia is client-side) --}}
        @if (!empty($seo))
            @if (!empty($seo['description']))
                <meta name="description" content="{{ $seo['description'] }}">
            @endif
            @if (!empty($seo['noindex']))
                <meta name="robots" content="noindex, nofollow">
            @endif
            <link rel="canonical" href="{{ $seo['url'] ?? url()->current() }}">

            <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
            <meta property="og:site_name" content="{{ $seo['siteName'] ?? config('app.name') }}">
            <meta property="og:title" content="{{ $seo['title'] ?? config('app.name') }}">
            <meta property="og:url" content="{{ $seo['url'] ?? url()->current() }}">
            @if (!empty($seo['description']))
                <meta property="og:description" content="{{ $seo['description'] }}">
            @endif
            @if (!empty($seo['image']))
                <meta property="og:image" content="{{ $seo['image'] }}">
                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:image" content="{{ $seo['image'] }}">
            @else
                <meta name="twitter:card" content="summary">
            @endif
            <meta name="twitter:title" content="{{ $seo['title'] ?? config('app.name') }}">
            @if (!empty($seo['description']))
                <meta name="twitter:description" content="{{ $seo['description'] }}">
            @endif

            {{-- Google structured data (JSON-LD) --}}
            @if (!empty($seo['jsonLd']))
                @foreach ($seo['jsonLd'] as $ld)
                    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
                @endforeach
            @endif
        @endif

        <!-- PWA -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#5b5bd6">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Convoro') }}">
        @php($iconRev = \App\Support\Settings::get('icons.rev', config('convoro.version', '1')))
        <link rel="icon" href="/favicon.ico?v={{ $iconRev }}" sizes="any">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v={{ $iconRev }}">
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v={{ $iconRev }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        {{-- Load font CSS non-render-blocking: fonts use display=swap, so text
             paints immediately in the fallback and swaps in when ready. Inter is
             the brand default (was a render-blocking @import in app.css). --}}
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" /></noscript>
        @if ($href = \App\Support\Theme::googleFontHref())
            <link href="{{ $href }}" rel="stylesheet" media="print" onload="this.media='all'" />
            <noscript><link href="{{ $href }}" rel="stylesheet" /></noscript>
        @endif
        @if ($hfHref = \App\Support\Theme::headingFontHref())
            <link href="{{ $hfHref }}" rel="stylesheet" media="print" onload="this.media='all'" />
            <noscript><link href="{{ $hfHref }}" rel="stylesheet" /></noscript>
        @endif
        @if ($favicon = \App\Support\Settings::get('site.favicon'))
            <link rel="icon" href="{{ $favicon }}" />
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])

        <!-- Live theme tokens (admin theme editor) -->
        <style id="convoro-theme">{!! \App\Support\Theme::css() !!}</style>
        @if ($customCss = \App\Support\Theme::customCss())
            <style id="convoro-custom-css">{!! $customCss !!}</style>
        @endif

        @if ($faKit = \App\Support\Settings::get('fa.kit_url'))
            <script src="{{ $faKit }}" crossorigin="anonymous" defer></script>
        @endif

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
