{{-- Stand-alone pages a subscriber opens from an email: preferences, their confirmation, [[unsubscribe_now]] --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
            color: #1F2937;
        }
        .card {
            background: white;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 640px;
            width: 100%;
        }
        @media (max-width: 480px) {
            .card { padding: 32px 20px; }
        }
        .header { text-align: center; margin-bottom: 28px; }
        .header-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .header-icon svg { width: 36px; height: 36px; color: white; }
        h1 { font-size: 26px; font-weight: 700; color: #1F2937; margin-bottom: 8px; line-height: 1.3; }
        .subtitle { font-size: 16px; color: #6B7280; }
        .email-display {
            background: #F3F4F6;
            padding: 12px 16px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 24px;
            font-weight: 500;
            color: #374151;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-danger { background: #DC2626; }
        .btn-danger:hover { background: #B91C1C; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35); }
        .btn-block { display: block; width: 100%; }
        .btn-container { text-align: center; }
        .info-text { text-align: center; font-size: 14px; color: #6B7280; margin-top: 12px; }
        .error-message {
            background: #FEE2E2;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        @yield('styles')
    </style>
</head>
<body>
    <main class="card">
        @yield('content')
    </main>
    @yield('scripts')
</body>
</html>
