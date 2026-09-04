@php
    $start = $ctx['start_at'] ?? $webinar->scheduled_at;
    $timezone = $ctx['displayTimezone'] ?? $ctx['scheduleTimezone'] ?? $webinar->timezone ?? 'UTC';
    $display = $start ? $start->copy()->setTimezone($timezone) : null;
    $title = trim($props['title']) !== '' ? $props['title'] : __('webinars.public.register.starts_at');
@endphp
<div class="wb-card wb-align-center">
    <p class="wb-text wb-text--sm wb-text--muted" style="text-transform: uppercase; letter-spacing: .08em; margin-bottom: 8px;">{{ $title }}</p>
    @if($display)
        <div class="wb-heading wb-heading--lg">{{ $display->format('d.m.Y') }}</div>
        <div class="wb-heading wb-heading--md">{{ __('webinars.public.register.at_hour') }} {{ $display->format('H:i') }}</div>
        <p class="wb-text wb-text--sm wb-text--muted" style="margin-top: 6px;">({{ $timezone }})</p>
    @else
        <div class="wb-heading wb-heading--md">{{ __('webinars.public.registered.soon') }}</div>
    @endif
</div>
