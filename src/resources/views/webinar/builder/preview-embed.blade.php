{{-- Preview for pages that keep their own player chrome (waiting room, replay):
     shows a stand-in for the player with the builder blocks underneath, exactly
     where they appear on the live page. --}}
@php
    $theme = $definition['theme'];
    $rows = $definition['rows'];
    $fontUrl = \App\Services\Webinar\WebinarPageRenderer::fontUrl($theme);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $webinar->name }}</title>
    @if($fontUrl)
        <link href="{{ $fontUrl }}" rel="stylesheet">
    @endif
    <style>
        body { margin: 0; background: #111827; color: #e5e7eb; font-family: system-ui, sans-serif; }
        .player-stub { max-width: 900px; margin: 0 auto; padding: 24px 16px 0; }
        .player-stub__frame {
            position: relative; padding-top: 52%;
            background: repeating-linear-gradient(45deg, #1f2937, #1f2937 12px, #111827 12px, #111827 24px);
            border-radius: 14px; border: 1px solid #374151;
        }
        .player-stub__label {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            font-size: 14px; letter-spacing: .04em; text-transform: uppercase; color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="player-stub">
        <div class="player-stub__frame">
            <div class="player-stub__label">{{ __('webinars.builder.player_placeholder') }}</div>
        </div>
    </div>

    @include('webinar.builder.embed', ['builderPage' => ['theme' => $theme, 'rows' => $rows, 'ctx' => $ctx]])
</body>
</html>
