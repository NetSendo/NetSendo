{{-- Embedded Calendly booking widget for the thank-you pages.
     Expects $webinar; optional $registration prefills the invitee's name/email. --}}
@php($calendly = $webinar->calendlySettings())
@if($calendly)
    @php
        $prefill = [];
        if (isset($registration)) {
            $fullName = trim(($registration->first_name ?? '') . ' ' . ($registration->last_name ?? ''));
            if ($fullName !== '') {
                $prefill['name'] = $fullName;
            }
            if (!empty($registration->email)) {
                $prefill['email'] = $registration->email;
            }
        }
        $calendlyUrl = $calendly['url'];
        if ($prefill) {
            $calendlyUrl .= (str_contains($calendlyUrl, '?') ? '&' : '?') . http_build_query($prefill);
        }
    @endphp
    <div class="mt-12 bg-white/10 backdrop-blur rounded-xl p-6">
        <h2 class="text-2xl font-bold mb-2 text-center">{{ $calendly['title'] !== '' ? $calendly['title'] : __('webinars.public.thankyou.calendly_title') }}</h2>
        @if($calendly['description'] !== '')
            <p class="opacity-80 text-center mb-4 whitespace-pre-line">{{ $calendly['description'] }}</p>
        @endif
        <div class="calendly-inline-widget rounded-xl overflow-hidden bg-white" data-url="{{ $calendlyUrl }}" style="min-width: 320px; height: 700px;"></div>
        <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
    </div>
@endif
