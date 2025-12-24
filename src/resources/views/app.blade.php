<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="NetSendo - Professional Email Marketing Platform">
        <meta name="author" content="NetSendo">
        <meta name="keywords" content="email marketing, automation, newsletter, campaign, netsendo">

        <!-- Google Analytics -->
        @include('partials.google-analytics')

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="NetSendo">
        <meta property="og:description" content="NetSendo - Professional Email Marketing Platform">
        <meta property="og:image" content="https://gregciupek.com/wp-content/uploads/2025/12/netsendo.png">
        <meta property="og:site_name" content="NetSendo">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="NetSendo">
        <meta property="twitter:description" content="NetSendo - Professional Email Marketing Platform">
        <meta property="twitter:image" content="https://gregciupek.com/wp-content/uploads/2025/12/netsendo.png">

        <title inertia>NetSendo</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1.png">
        <link rel="apple-touch-icon" href="https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
