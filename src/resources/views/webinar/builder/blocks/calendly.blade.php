{{-- Calendly booking widget. Block props override the webinar-level settings,
     but the URL is always validated against calendly.com. --}}
@php
    $settings = $webinar->calendlySettings();
    $url = trim($props['url']) !== '' ? trim($props['url']) : ($settings['url'] ?? '');
    $valid = (bool) preg_match('#^https://([a-z0-9-]+\.)?calendly\.com/.+#i', $url);
    $registration = $ctx['registration'] ?? null;
    $prefill = [];
    if ($registration) {
        $fullName = trim(($registration->first_name ?? '') . ' ' . ($registration->last_name ?? ''));
        if ($fullName !== '') {
            $prefill['name'] = $fullName;
        }
        if (!empty($registration->email)) {
            $prefill['email'] = $registration->email;
        }
    }
    if ($valid && $prefill) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($prefill);
    }
    $title = trim($props['title']) !== '' ? $props['title'] : ($settings['title'] ?? '');
    $description = trim($props['description']) !== '' ? $props['description'] : ($settings['description'] ?? '');
@endphp
@if($valid)
    <div class="wb-card">
        <h3 class="wb-heading wb-heading--md wb-align-center">{{ $title !== '' ? $title : __('webinars.public.thankyou.calendly_title') }}</h3>
        @if($description !== '')
            <p class="wb-text wb-text--muted wb-align-center" style="margin: 8px 0 16px;">{{ $description }}</p>
        @endif
        <div class="calendly-inline-widget" data-url="{{ $url }}" style="min-width: 300px; height: 700px; border-radius: 14px; overflow: hidden; background: #fff;"></div>
        <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
    </div>
@endif
