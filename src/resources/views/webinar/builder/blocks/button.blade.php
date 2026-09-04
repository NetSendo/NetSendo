@php
    $tokens = $ctx['tokens'] ?? [];
    $label = \App\Services\Webinar\WebinarPageRenderer::replaceTokens($props['label'], $tokens);
    $url = trim(\App\Services\Webinar\WebinarPageRenderer::replaceTokens($props['url'], $tokens));
    $justify = match ($props['align']) { 'left' => 'flex-start', 'right' => 'flex-end', default => 'center' };
    $classes = 'wb-button wb-button--' . $props['size'] . ($props['full_width'] ? ' wb-button--block' : '');
@endphp
@if($label !== '')
    <div style="display: flex; justify-content: {{ $justify }};">
        <a class="{{ $classes }}" href="{{ $url !== '' ? $url : '#wb-form' }}" @if(str_starts_with($url, 'http')) rel="noopener" @endif>{{ $label }}</a>
    </div>
@endif
