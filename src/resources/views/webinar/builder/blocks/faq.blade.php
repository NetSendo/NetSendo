@php
    $items = array_values(array_filter($props['items'], fn ($i) => is_array($i) && trim((string) ($i['question'] ?? '')) !== ''));
@endphp
@if(count($items) > 0)
    <div>
        @if(trim($props['title']) !== '')
            <h3 class="wb-heading wb-heading--md wb-align-center" style="margin-bottom: 18px;">{{ $props['title'] }}</h3>
        @endif
        <div class="wb-faq">
            @foreach($items as $item)
                <details class="wb-faq__item">
                    <summary class="wb-faq__question">{{ $item['question'] }}</summary>
                    <p class="wb-faq__answer">{{ $item['answer'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </div>
@endif
