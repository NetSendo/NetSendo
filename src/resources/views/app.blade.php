<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="NetSendo - Professional Email Marketing Platform">
        <meta name="author" content="NetSendo">
        <meta name="keywords" content="email marketing, automation, newsletter, campaign, netsendo">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="NetSendo">
        <meta property="og:description" content="NetSendo - Professional Email Marketing Platform">
        <meta property="og:image" content="{{ asset('images/brand/netsendo-og.png') }}">
        <meta property="og:site_name" content="NetSendo">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="NetSendo">
        <meta property="twitter:description" content="NetSendo - Professional Email Marketing Platform">
        <meta property="twitter:image" content="{{ asset('images/brand/netsendo-og.png') }}">

        <title data-inertia>NetSendo</title>

        <!-- Favicon (images and fonts ship with the app: the panel loads nothing from another host) -->
        <link rel="icon" type="image/png" href="{{ asset('images/brand/netsendo-icon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/brand/netsendo-icon.png') }}">

        @php
            // The texts of the page's language are a file of their own. Named here, the browser
            // fetches it together with the scripts instead of after they have started.
            $localeChunk = null;
            try {
                if (! \Illuminate\Support\Facades\Vite::isRunningHot()) {
                    $localeChunk = \Illuminate\Support\Facades\Vite::asset('resources/js/locales/'.app()->getLocale().'.json');
                }
            } catch (\Throwable $e) {
                // No build manifest (tests) or a language without a file: the page still loads it itself
            }
        @endphp
        @if ($localeChunk)
            <link rel="modulepreload" href="{{ $localeChunk }}">
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
