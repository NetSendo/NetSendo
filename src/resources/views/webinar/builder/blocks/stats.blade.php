@php
    $items = array_values(array_filter($props['items'], fn ($i) => is_array($i) && trim((string) ($i['value'] ?? '')) !== ''));
@endphp
@if(count($items) > 0)
    <div class="wb-stats">
        @foreach($items as $item)
            <div>
                <div class="wb-stats__value">{{ $item['value'] }}</div>
                <div class="wb-stats__label">{{ $item['label'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
@endif
