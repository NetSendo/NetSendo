@component('mail::message')
@if($reminderType === '15min')
# ⏰ Zaczynamy za 15 minut!
@elseif($reminderType === '1h')
# ⏰ Webinar za godzinę!
@else
# 📅 Jutro webinar!
@endif

Cześć {{ $registration->first_name ?? 'tam' }},

@if($reminderType === '15min')
Webinar **{{ $webinar->name }}** rozpoczyna się za **15 minut**!

Kliknij przycisk poniżej, aby dołączyć:
@elseif($reminderType === '1h')
Przypominamy, że już za godzinę rozpoczyna się webinar **{{ $webinar->name }}**.

Przygotuj się i dołącz punktualnie!
@else
Jutro o godzinie **{{ $registration->localStart()?->format('H:i') }}** ({{ $registration->displayTimezone() }}) rozpoczyna się webinar:

**{{ $webinar->name }}**

Nie zapomnij dodać wydarzenia do kalendarza!
@endif

@component('mail::button', ['url' => $watchUrl, 'color' => $reminderType === '15min' ? 'success' : 'primary'])
Dołącz do webinaru
@endcomponent

Do zobaczenia!<br>
{{ config('app.name') }}
@endcomponent
