{{-- Builder-rendered public webinar page.
     Expects: $webinar, $definition (normalized), $ctx (page context array). --}}
@php
    use App\Services\Webinar\WebinarPageRenderer;

    $theme = $definition['theme'];
    $rows = $definition['rows'];
    $fontUrl = WebinarPageRenderer::fontUrl($theme);
    $tokens = $ctx['tokens'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ctx['title'] ?? $webinar->name }}</title>
    @if(!empty($ctx['noindex']))
        <meta name="robots" content="noindex">
    @endif
    @if($webinar->description)
        <meta name="description" content="{{ Str::limit(strip_tags($webinar->description), 160) }}">
    @endif
    <meta property="og:title" content="{{ $ctx['title'] ?? $webinar->name }}">
    @if($webinar->thumbnail_url)
        <meta property="og:image" content="{{ $webinar->thumbnail_url }}">
    @endif
    @if($fontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $fontUrl }}" rel="stylesheet">
    @endif
    <style>{!! WebinarPageRenderer::css($theme) !!}</style>
</head>
<body>
    <main class="wb-page">
        @include('webinar.builder.rows', ['rows' => $rows])
    </main>
</body>
</html>
