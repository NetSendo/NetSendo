@php
    $items = array_values(array_filter(array_map(
        fn ($item) => is_array($item) ? trim((string) ($item['text'] ?? '')) : trim((string) $item),
        $props['items']
    )));
    $columns = ((int) $props['columns']) === 2 ? ' wb-list--2' : '';
@endphp
@if(count($items) > 0)
    <div>
        @if(trim($props['title']) !== '')
            <h3 class="wb-heading wb-heading--md" style="margin-bottom: 18px;">{{ $props['title'] }}</h3>
        @endif
        <ul class="wb-list{{ $columns }}">
            @foreach($items as $item)
                <li class="wb-list__item">
                    <svg class="wb-list__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        @if($props['icon'] === 'star')
                            <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9 4.8 17.6l1-5.8L1.5 7.7l5.9-.9z"/>
                        @elseif($props['icon'] === 'arrow')
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 6.3a1 1 0 000 1.4L11 10l-2.3 2.3a1 1 0 101.4 1.4l3-3a1 1 0 000-1.4l-3-3a1 1 0 00-1.4 0z" clip-rule="evenodd"/>
                        @else
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 10-1.4 1.4l2 2a1 1 0 001.4 0z" clip-rule="evenodd"/>
                        @endif
                    </svg>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
