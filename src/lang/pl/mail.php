<?php

return [
    'new_subscriber_notification_subject' => 'Nowy subskrybent na liście :list',
    'errors' => [
        'no_mailbox_for_type' => 'Żadna aktywna skrzynka pocztowa nie ma włączonego typu wysyłki ":type". Włącz ten typ w ustawieniach skrzynki (Ustawienia > Skrzynki pocztowe > Dozwolone typy).',
        'no_mailbox' => 'Brak skonfigurowanej skrzynki pocztowej. Skonfiguruj skrzynkę w ustawieniach przed wysyłką wiadomości.',
        'campaign_sender_type' => 'Skrzynka kampanii ":mailbox" (:email) nie ma włączonego typu Jednorazowe (Broadcast) (skrzynki Gmail nigdy go nie mają), a żadna lista tej kampanii nie ma skrzynki do wysyłki masowej, więc kampania nie mogła wyjść. Nie została wysłana z innej skrzynki. Ustaw skrzynkę do wysyłki masowej w ustawieniach listy (Wysyłka): kampanie do listy wychodzą z niej. Albo wybierz skrzynkę, która może wysyłać kampanie.',
        'individual_sender' => [
            'inactive' => 'Ten e-mail indywidualny miał wyjść ze skrzynki ":mailbox" (:email), która jest teraz wyłączona albo usunięta. Nie został wysłany z innej skrzynki. Włącz skrzynkę i wyślij wiadomość ponownie.',
            'type' => 'Skrzynka ":mailbox" (:email) nie ma włączonego typu Jednorazowe (Broadcast) ani Autoresponder, więc nie może wysłać tego e-maila indywidualnego. Nie został wysłany z innej skrzynki. Włącz jeden z tych typów w ustawieniach skrzynki (Ustawienia > Skrzynki pocztowe > Dozwolone typy).',
            'limit' => 'Skrzynka ":mailbox" (:email) wyczerpała dzienny limit wysyłki. Ten e-mail indywidualny nie został wysłany z innej skrzynki. Wyślij go ponownie po odnowieniu limitu albo podnieś limit w ustawieniach skrzynki.',
        ],
    ],
];
