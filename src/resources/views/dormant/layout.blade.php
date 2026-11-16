{{-- Stand-alone page of a sleeping instance (App\Http\Middleware\DormantMode): no session, no Vite assets --}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · NetSendo</title>
    <style>
        :root { color-scheme: light dark; --bg: #f6f4f8; --card: #ffffff; --text: #1f1530; --muted: #5d5470; --brand: #50215f; --rule: #c6f432; }
        @media (prefers-color-scheme: dark) { :root { --bg: #140d1a; --card: #1f1628; --text: #f3eef7; --muted: #b6abc2; --brand: #c9a3dc; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               background: var(--bg); color: var(--text); font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 560px; background: var(--card); border-radius: 16px; padding: 40px 32px;
               box-shadow: 0 10px 30px rgba(31, 21, 48, .08); border-top: 4px solid var(--rule); }
        .brand { font-weight: 800; font-size: 20px; color: var(--brand); letter-spacing: -.01em; margin: 0 0 28px; }
        h1 { font-size: 24px; line-height: 1.3; margin: 0 0 16px; }
        p { margin: 0 0 14px; color: var(--muted); }
        a { color: var(--brand); }
        button { margin-top: 12px; border: 0; border-radius: 10px; padding: 12px 22px; font: inherit; font-weight: 600;
                 background: var(--brand); color: #fff; cursor: pointer; }
        @media (prefers-color-scheme: dark) { button { color: #140d1a; } }
        .note { font-size: 14px; margin-top: 24px; }
    </style>
</head>
<body>
<main>
    <p class="brand">NetSendo</p>
    @yield('content')
</main>
</body>
</html>
