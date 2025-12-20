<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Błąd</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-card {
            background: white;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
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
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 12px;
        }
        .message {
            font-size: 16px;
            color: #6B7280;
            line-height: 1.6;
        }
        .back-link {
            display: inline-block;
            margin-top: 24px;
            color: #6366F1;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <h1>Wystąpił błąd</h1>
        <p class="message">{{ $message }}</p>
        <a href="javascript:history.back()" class="back-link">← Wróć do formularza</a>
    </div>
</body>
</html>
