@php
    $space = \App\Services\Webinar\WebinarPageRenderer::spacing($props['size'], 'md');
@endphp
@if($props['style'] === 'line')
    <hr class="wb-divider" style="margin: {{ $space }} 0;">
@else
    <div style="height: {{ $space }};"></div>
@endif
