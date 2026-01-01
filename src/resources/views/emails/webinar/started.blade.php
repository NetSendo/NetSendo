@component('mail::message')
# 🔴 NA ŻYWO!

Cześć {{ $registration->first_name ?? 'tam' }},

Webinar **{{ $webinar->name }}** właśnie się rozpoczął!

Dołącz teraz, aby niczego nie przegapić:

@component('mail::button', ['url' => $watchUrl, 'color' => 'success'])
Dołącz teraz
@endcomponent

Do zobaczenia na żywo!<br>
{{ config('app.name') }}
@endcomponent
