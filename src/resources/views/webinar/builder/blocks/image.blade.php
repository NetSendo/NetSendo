@php
    $url = trim($props['url']);
    $justify = match ($props['align']) { 'left' => 'flex-start', 'right' => 'flex-end', default => 'center' };
    $radius = \App\Services\Webinar\WebinarPageRenderer::radius($props['radius'], 'lg');
    $width = max(10, min(100, (int) $props['width']));
@endphp
@if($url !== '')
    <div style="display: flex; justify-content: {{ $justify }};">
        @if(trim($props['link']) !== '')
            <a href="{{ $props['link'] }}" style="display: block; width: {{ $width }}%;">
                <img src="{{ $url }}" alt="{{ $props['alt'] }}" style="width: 100%; border-radius: {{ $radius }};">
            </a>
        @else
            <img src="{{ $url }}" alt="{{ $props['alt'] }}" style="width: {{ $width }}%; border-radius: {{ $radius }};">
        @endif
    </div>
@endif
