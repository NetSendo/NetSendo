{{-- Session picker for auto-webinars. The radios live outside the <form> and
     are bound to it with the form attribute. --}}
@php
    $sessions = $ctx['sessions'] ?? [];
    $scheduleTimezone = $ctx['scheduleTimezone'] ?? 'UTC';
    $title = trim($props['title']) !== '' ? $props['title'] : __('webinars.public.register.choose_date');
    $subtitle = trim($props['subtitle']) !== ''
        ? $props['subtitle']
        : __('webinars.public.register.times_in_timezone', ['timezone' => $scheduleTimezone]);
@endphp
@if(count($sessions) > 0)
    <div class="wb-card">
        <h3 class="wb-heading wb-heading--sm wb-align-center">{{ $title }}</h3>
        <p class="wb-text wb-text--sm wb-text--muted wb-align-center" style="margin: 6px 0 16px;">{{ $subtitle }}</p>
        <div class="wb-sessions">
            @foreach($sessions as $session)
                <label class="wb-sessions__option">
                    <input type="radio" name="session_time" value="{{ $session->format('Y-m-d H:i:s') }}" form="wb-registration-form" @if($loop->first) checked @endif>
                    <span class="wb-sessions__box">
                        <span class="wb-sessions__day">{{ $session->format('d.m') }}</span><br>
                        <span class="wb-sessions__hour">{{ $session->format('H:i') }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endif
