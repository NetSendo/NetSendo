@component('mail::message')
# 🎬 Nagranie dostępne!

Cześć {{ $registration->first_name ?? 'tam' }},

Nagranie webinaru **{{ $webinar->name }}** jest już dostępne!

@if($registration->status !== 'attended')
Nie udało Ci się dołączyć na żywo? Nic straconego - możesz obejrzeć całe nagranie:
@else
Chcesz obejrzeć webinar jeszcze raz? Nagranie jest dostępne przez ograniczony czas:
@endif

@component('mail::button', ['url' => $replayUrl])
Obejrzyj nagranie
@endcomponent

Pozdrawiamy,<br>
{{ config('app.name') }}
@endcomponent
