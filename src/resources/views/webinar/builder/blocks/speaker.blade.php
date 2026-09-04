@if(trim($props['name']) !== '' || trim($props['bio']) !== '')
    <div class="wb-speaker {{ $props['layout'] === 'stacked' ? 'wb-speaker--stacked' : '' }}">
        @if(trim($props['avatar']) !== '')
            <img class="wb-speaker__avatar" src="{{ $props['avatar'] }}" alt="{{ $props['name'] }}">
        @endif
        <div>
            @if(trim($props['name']) !== '')<div class="wb-speaker__name">{{ $props['name'] }}</div>@endif
            @if(trim($props['role']) !== '')<div class="wb-speaker__role">{{ $props['role'] }}</div>@endif
            @if(trim($props['bio']) !== '')<div class="wb-text wb-text--muted">{{ $props['bio'] }}</div>@endif
        </div>
    </div>
@endif
