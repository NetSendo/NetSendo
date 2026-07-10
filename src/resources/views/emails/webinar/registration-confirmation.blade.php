@component('mail::message')
# Dziękujemy za rejestrację!

Cześć {{ $registration->first_name ?? 'tam' }},

Twoja rejestracja na webinar **{{ $webinar->name }}** została potwierdzona.

@if($webinar->scheduled_at)
📅 **Data:** {{ $webinar->scheduled_at->format('d.m.Y') }}<br>
🕐 **Godzina:** {{ $webinar->scheduled_at->format('H:i') }}
@endif

@component('mail::button', ['url' => $watchUrl])
Dołącz do webinaru
@endcomponent

@if($registration->scheduledStart())
**Dodaj do kalendarza:** [Google Calendar]({{ $registration->google_calendar_link }}) &nbsp;•&nbsp; [Outlook]({{ $registration->outlook_calendar_link }}) &nbsp;•&nbsp; [Apple / .ics]({{ $registration->ics_url }})
@endif

Zachowaj ten email - zawiera Twój unikalny link do webinaru.

**Wskazówki:**
- Dołącz kilka minut przed rozpoczęciem
- Przygotuj pytania - podczas webinaru będzie możliwość zadawania pytań
- Upewnij się, że masz stabilne połączenie internetowe

Do zobaczenia!

Pozdrawiamy,<br>
{{ config('app.name') }}
@endcomponent
