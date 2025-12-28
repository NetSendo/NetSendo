<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NetSendo' }}</title>
    <style>
        /* Base styling for system pages - can be overridden by content CSS */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .system-page-content {
            background: white;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 640px;
            width: 100%;
            text-align: center;
        }
        /* Success icon */
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        /* Error icon */
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .error-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        /* Warning icon */
        .warning-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .warning-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        /* Info icon */
        .info-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .info-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        /* Typography defaults */
        .system-page-content h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
        }
        .system-page-content h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 12px;
        }
        .system-page-content p {
            font-size: 16px;
            color: #6B7280;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .system-page-content a {
            color: #667eea;
            text-decoration: none;
        }
        .system-page-content a:hover {
            text-decoration: underline;
        }
        /* Button styles */
        .system-page-content .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .system-page-content .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="system-page-content">
        @if($icon ?? false)
            @if($icon === 'success')
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            @elseif($icon === 'error')
            <div class="error-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            @elseif($icon === 'warning')
            <div class="warning-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            @elseif($icon === 'info')
            <div class="info-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            @endif
        @endif

        {!! $content !!}
    </div>
</body>
</html>
