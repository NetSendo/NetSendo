@php
    $items = array_values(array_filter($props['items'], fn ($i) => is_array($i) && trim((string) ($i['quote'] ?? '')) !== ''));
    $columns = max(1, min(3, (int) $props['columns']));
@endphp
@if(count($items) > 0)
    <div>
        @if(trim($props['title']) !== '')
            <h3 class="wb-heading wb-heading--md wb-align-center" style="margin-bottom: 18px;">{{ $props['title'] }}</h3>
        @endif
        <div class="wb-grid wb-grid--{{ $columns }}">
            @foreach($items as $item)
                <figure class="wb-card wb-quote">
                    <p class="wb-quote__text">„{{ $item['quote'] }}”</p>
                    <figcaption class="wb-quote__author">
                        @if(trim($item['avatar'] ?? '') !== '')
                            <img class="wb-quote__avatar" src="{{ $item['avatar'] }}" alt="{{ $item['author'] ?? '' }}">
                        @endif
                        <span>
                            <strong>{{ $item['author'] ?? '' }}</strong>
                            @if(trim($item['role'] ?? '') !== '')
                                <span class="wb-quote__role"> · {{ $item['role'] }}</span>
                            @endif
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
@endif
