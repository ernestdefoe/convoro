<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Support\Settings::get('theme.mode', 'light') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#5b5bd6">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Convoro') }}">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        @if ($href = \App\Support\Theme::googleFontHref())
            <link href="{{ $href }}" rel="stylesheet" />
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])

        <!-- Live theme tokens (admin theme editor) -->
        <style id="convoro-theme">{!! \App\Support\Theme::css() !!}</style>

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
