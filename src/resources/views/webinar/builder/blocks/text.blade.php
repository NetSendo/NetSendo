@php
    $body = \App\Services\Webinar\WebinarPageRenderer::replaceTokens($props['body'], $ctx['tokens'] ?? []);
@endphp
@if($body !== '')
    <p class="wb-text wb-text--{{ $props['size'] }} wb-text--{{ $props['color'] }} wb-align-{{ $props['align'] }}">{{ $body }}</p>
@endif
