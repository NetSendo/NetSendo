@php
    $items = array_values(array_filter(array_map(function ($item) {
        if (is_array($item)) {
            return ['title' => trim((string) ($item['title'] ?? '')), 'body' => trim((string) ($item['body'] ?? ''))];
        }
        return ['title' => trim((string) $item), 'body' => ''];
    }, $props['items']), fn ($i) => $i['title'] !== '' || $i['body'] !== ''));
@endphp
@if(count($items) > 0)
    <div>
        @if(trim($props['title']) !== '')
            <h3 class="wb-heading wb-heading--md" style="margin-bottom: 18px;">{{ $props['title'] }}</h3>
        @endif
        <div class="wb-steps">
            @foreach($items as $item)
                <div class="wb-steps__item">
                    <div class="wb-steps__number"></div>
                    <div>
                        @if($item['title'] !== '')<div style="font-weight: 700;">{{ $item['title'] }}</div>@endif
                        @if($item['body'] !== '')<div class="wb-text--muted" style="white-space: pre-line;">{{ $item['body'] }}</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
