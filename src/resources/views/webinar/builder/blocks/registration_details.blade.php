{{-- Post-registration summary: session time, personal watch link and calendar
     buttons. Renders nothing outside the post-registration screen. --}}
@php
    $registration = $ctx['registration'] ?? null;
    $timezone = $ctx['displayTimezone'] ?? $webinar->timezone ?? 'UTC';
    $start = $registration?->scheduledStart();
    $display = $start ? $start->copy()->setTimezone($timezone) : null;
@endphp
@if($registration)
    <div style="display: grid; gap: 18px;">
        @if($props['show_time'])
            <div class="wb-card wb-align-center">
                <p class="wb-text wb-text--sm wb-text--muted" style="margin-bottom: 8px;">{{ __('webinars.public.registered.starts_at') }}</p>
                @if($display)
                    <div class="wb-heading wb-heading--lg">{{ $display->format('d.m.Y') }} · {{ $display->format('H:i') }}</div>
                    <p class="wb-text wb-text--sm wb-text--muted" style="margin-top: 4px;">({{ $timezone }})</p>
                @else
                    <div class="wb-heading wb-heading--md">{{ __('webinars.public.registered.soon') }}</div>
                @endif
            </div>
        @endif

        @if($props['show_link'])
            <div class="wb-card">
                <p class="wb-text wb-text--sm" style="margin-bottom: 10px;">{{ __('webinars.public.registered.your_link') }}</p>
                <div class="wb-link-box">{{ $registration->watch_url }}</div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px;">
                    <a class="wb-button wb-button--sm" href="{{ $registration->watch_url }}">{{ __('webinars.public.registered.go_to_webinar') }}</a>
                    <button type="button" class="wb-chip" data-wb-copy="{{ $registration->watch_url }}">{{ __('webinars.public.registered.copy_link') }}</button>
                </div>
            </div>
        @endif

        @if($props['show_calendar'] && $registration->scheduledStart())
            <div class="wb-align-center">
                <p class="wb-text wb-text--sm wb-text--muted" style="margin-bottom: 10px;">{{ __('webinars.public.registered.add_to_calendar') }}</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                    <a class="wb-chip" href="{{ $registration->google_calendar_link }}" target="_blank" rel="noopener">Google Calendar</a>
                    <a class="wb-chip" href="{{ $registration->outlook_calendar_link }}" target="_blank" rel="noopener">Outlook</a>
                    <a class="wb-chip" href="{{ $registration->ics_url }}">{{ __('webinars.public.registered.apple_calendar') }}</a>
                </div>
            </div>
        @endif
    </div>
    <script>
        (function () {
            document.querySelectorAll('[data-wb-copy]:not([data-wb-ready])').forEach(function (button) {
                button.setAttribute('data-wb-ready', '1');
                button.addEventListener('click', function () {
                    navigator.clipboard.writeText(button.getAttribute('data-wb-copy'));
                });
            });
        })();
    </script>
@endif
