@component('mail::message')
# Cześć {{ $invitation->name }}!

Zostałeś zaproszony do dołączenia do zespołu w aplikacji NetSendo przez {{ $invitation->admin->name }}.

Kliknij w poniższy przycisk, aby zaakceptować zaproszenie i ustawić swoje hasło:

@component('mail::button', ['url' => route('invitation.accept', $invitation->token)])
Zaakceptuj zaproszenie
@endcomponent

Jeśli nie spodziewałeś się tego zaproszenia, możesz zignorować tę wiadomość.

Pozdrawiamy,<br>
Zespół {{ config('app.name') }}
@endcomponent
