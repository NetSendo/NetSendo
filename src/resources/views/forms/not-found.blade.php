<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formularz nie znaleziony</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F3F4F6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .not-found-card {
            background: white;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        p {
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="not-found-card">
        <h1>Formularz nie znaleziony</h1>
        <p>Ten formularz nie istnieje lub został wyłączony.</p>
    </div>
</body>
</html>
