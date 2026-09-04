@php
    $embed = \App\Models\Webinar::videoEmbedUrl($props['url']);
@endphp
@if($embed)
    <div>
        <div class="wb-video">
            <iframe src="{{ $embed }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" title="{{ $props['caption'] ?: $webinar->name }}"></iframe>
        </div>
        @if(trim($props['caption']) !== '')
            <p class="wb-caption">{{ $props['caption'] }}</p>
        @endif
    </div>
@endif
