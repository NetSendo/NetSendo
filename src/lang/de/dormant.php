<?php

// Seiten einer schlafenden NetSendo-Cloud-Instanz (App\Http\Middleware\DormantMode)
return [
    'asleep' => [
        'title' => 'Diese NetSendo-Instanz schläft',
        'body' => 'Das Abonnement dieser Instanz ist beendet. Die Daten sind sicher gespeichert, aber Anmeldung und Versand sind pausiert.',
        'owner' => 'Du bist der Inhaber? Setze sie über den Link in der E-Mail von NetSendo fort. In wenigen Minuten ist sie unter derselben Adresse und mit allen Daten wieder da. Du kannst auch an contact@netsendo.com schreiben.',
        'unsubscribe' => 'Du möchtest keine Nachrichten mehr von diesem Absender? Der Abmeldelink in seiner E-Mail funktioniert weiterhin.',
    ],
    'unsubscribe' => [
        'title' => 'Abmelden',
        'body' => 'Bestätige, dass du keine Nachrichten von dieser Liste mehr erhalten möchtest.',
        'body_all' => 'Bestätige, dass du keine Nachrichten von diesem Absender mehr erhalten möchtest. Du wirst von allen seinen Listen abgemeldet.',
        'button' => 'Abmelden',
        'invalid_title' => 'Dieser Link ist ungültig',
        'invalid' => 'Verwende den Abmeldelink aus der Nachricht genau so, wie du ihn erhalten hast.',
    ],
];
