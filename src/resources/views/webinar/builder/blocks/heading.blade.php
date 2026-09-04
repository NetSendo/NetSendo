@php
    $text = \App\Services\Webinar\WebinarPageRenderer::replaceTokens($props['text'], $ctx['tokens'] ?? []);
    $tag = 'h' . max(1, min(4, (int) $props['level']));
@endphp
@if($text !== '')
    <{{ $tag }} class="wb-heading wb-heading--{{ $props['size'] }} wb-align-{{ $props['align'] }} wb-text--{{ $props['color'] }}">{{ $text }}</{{ $tag }}>
@endif
