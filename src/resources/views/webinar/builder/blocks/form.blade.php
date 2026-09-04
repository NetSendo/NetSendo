{{-- Registration form. Uses the same field names as the legacy template so the
     controller and any existing integrations keep working. --}}
@php
    $canRegister = $ctx['canRegister'] ?? true;
    $timezones = $ctx['timezones'] ?? [];
    $defaultTimezone = $ctx['defaultTimezone'] ?? 'UTC';
    $formTitle = trim($props['title']) !== ''
        ? $props['title']
        : $webinar->pageContent('register_form_title', __('webinars.public.register.title'));
    $buttonLabel = trim($props['button_label']) !== ''
        ? $props['button_label']
        : $webinar->pageContent('register_button', __('webinars.public.register.submit'));
    $consent = trim($props['consent']) !== '' ? $props['consent'] : __('webinars.public.register.consent');
@endphp
<div class="wb-panel" id="wb-form">
    @if($canRegister)
        @if($formTitle !== '')
            <h2 class="wb-heading wb-heading--md wb-align-center" style="color: var(--wb-card-text); margin-bottom: 20px;">{{ $formTitle }}</h2>
        @endif

        @if(session('error'))
            <div class="wb-form__error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="wb-form__error">{{ $errors->first() }}</div>
        @endif

        <form id="wb-registration-form" action="{{ route('webinar.register.submit', $webinar->slug) }}" method="POST">
            @csrf

            <div class="wb-form__field">
                <label class="wb-form__label" for="wb-email">{{ __('webinars.public.register.email') }} *</label>
                <input class="wb-form__control" type="email" name="email" id="wb-email" required value="{{ old('email') }}" placeholder="jan@example.com">
            </div>

            @if($props['show_first_name'] || $props['show_last_name'])
                <div class="wb-form__field {{ $props['show_first_name'] && $props['show_last_name'] ? 'wb-form__grid' : '' }}">
                    @if($props['show_first_name'])
                        <div>
                            <label class="wb-form__label" for="wb-first-name">{{ __('webinars.public.register.first_name') }}@if($props['require_name']) *@endif</label>
                            <input class="wb-form__control" type="text" name="first_name" id="wb-first-name" value="{{ old('first_name') }}" @if($props['require_name']) required @endif>
                        </div>
                    @endif
                    @if($props['show_last_name'])
                        <div>
                            <label class="wb-form__label" for="wb-last-name">{{ __('webinars.public.register.last_name') }}</label>
                            <input class="wb-form__control" type="text" name="last_name" id="wb-last-name" value="{{ old('last_name') }}">
                        </div>
                    @endif
                </div>
            @endif

            @if($props['show_phone'])
                <div class="wb-form__field">
                    <label class="wb-form__label" for="wb-phone">{{ __('webinars.public.register.phone') }}</label>
                    <input class="wb-form__control" type="tel" name="phone" id="wb-phone" value="{{ old('phone') }}">
                </div>
            @endif

            @if($props['show_timezone'] && count($timezones) > 0)
                <div class="wb-form__field">
                    <label class="wb-form__label" for="timezone">{{ __('webinars.public.register.timezone') }}</label>
                    <select class="wb-form__control" name="timezone" id="timezone">
                        @foreach($timezones as $tz => $label)
                            <option value="{{ $tz }}" {{ $tz === $defaultTimezone ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <input type="hidden" name="utm_source" value="{{ request('utm_source') }}">
            <input type="hidden" name="utm_medium" value="{{ request('utm_medium') }}">
            <input type="hidden" name="utm_campaign" value="{{ request('utm_campaign') }}">

            <button type="submit" class="wb-button wb-button--lg wb-button--block">{{ $buttonLabel }}</button>

            @if($consent !== '')
                <p class="wb-form__note">{{ $consent }}</p>
            @endif
        </form>

        <script>
            (function () {
                try {
                    var browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    var select = document.getElementById('timezone');
                    if (select && Array.from(select.options).some(function (o) { return o.value === browserTimezone; })) {
                        select.value = browserTimezone;
                    }
                } catch (e) {}
            })();
        </script>
    @else
        <p class="wb-align-center" style="color: var(--wb-card-text); margin: 0;">{{ __('webinars.public.register.closed') }}</p>
    @endif
</div>
