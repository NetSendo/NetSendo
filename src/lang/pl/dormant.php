<?php

// Strony uśpionej instancji NetSendo Cloud (App\Http\Middleware\DormantMode)
return [
    'asleep' => [
        'title' => 'Ta instancja NetSendo jest uśpiona',
        'body' => 'Subskrypcja tej instancji się zakończyła. Dane są bezpiecznie przechowywane, ale logowanie i wysyłki są wstrzymane.',
        'owner' => 'Jesteś właścicielem? Wznów ją linkiem z e-maila od NetSendo, a wróci w kilka minut, pod tym samym adresem i ze wszystkimi danymi. Możesz też napisać na contact@netsendo.com.',
        'unsubscribe' => 'Nie chcesz dostawać wiadomości od tego nadawcy? Link wypisu z jego wiadomości nadal działa.',
    ],
    'unsubscribe' => [
        'title' => 'Wypisanie z listy',
        'body' => 'Potwierdź, że nie chcesz już dostawać wiadomości z tej listy.',
        'body_all' => 'Potwierdź, że nie chcesz już dostawać żadnych wiadomości od tego nadawcy. Wypiszemy Cię ze wszystkich jego list.',
        'button' => 'Wypisz mnie',
        'invalid_title' => 'Ten link jest nieprawidłowy',
        'invalid' => 'Użyj linku wypisu z wiadomości dokładnie w takiej postaci, w jakiej go otrzymałeś.',
    ],
];
