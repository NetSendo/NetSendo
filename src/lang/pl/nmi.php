<?php

return [
    'dkim_generated' => 'Klucze DKIM zostały wygenerowane!',
    'dkim_not_verified' => 'Rekord DKIM nie został znaleziony. Sprawdź konfigurację DNS.',
    'dkim_verified' => 'DKIM zweryfikowany pomyślnie!',
    'invalid_ip_address' => 'Nieprawidłowy format adresu IP.',
    'ip_added' => 'Adres IP został dodany pomyślnie!',
    'ip_deleted' => 'Adres IP został usunięty.',
    'ip_has_mailboxes' => 'Nie można usunąć adresu IP z przypisanymi skrzynkami.',
    'ip_provisioned' => 'Adres IP zamówiony pomyślnie!',
    'pool_created' => 'Pula IP została utworzona!',
    'pool_deleted' => 'Pula IP została usunięta.',
    'pool_max_ips_reached' => 'Ta pula osiągnęła maksymalną liczbę adresów IP.',
    'pool_not_empty' => 'Nie można usunąć puli zawierającej adresy IP.',
    'provider_not_configured' => 'Ten dostawca nie jest skonfigurowany. Dodaj klucz API w ustawieniach dostawców.',
    'provider_saved' => 'Ustawienia dostawcy zapisane pomyślnie!',
    'warming_started' => 'Ogrzewanie IP zostało rozpoczęte!',
    'blacklist_checked' => 'Sprawdzono czarne listy.',

    // IP providers (addresses ordered through a hosting company's API) and reverse DNS
    'ip_release_failed' => 'Dostawca nie przyjął adresu z powrotem, więc nadal byłby naliczany: :reason',
    'providers' => [
        'errors' => [
            'unreachable' => 'Nie udało się połączyć z :provider. Spróbuj ponownie za chwilę.',
            'unauthorized' => ':provider nie przyjął klucza API: :reason',
            'refused' => ':provider odrzucił żądanie: :reason',
            'server_not_found' => 'Tego serwera nie ma już na Twoim koncie :provider.',
            'digitalocean_unusable' => 'DigitalOcean nie nadaje się na dedykowane adresy IP do poczty: blokuje SMTP na adresach Reserved IP i nie daje dla nich rekordu PTR.',
        ],
    ],
    'ptr' => [
        'verified' => 'Rekord PTR jest poprawny: adres wskazuje na swój host, a host z powrotem na adres.',
        'not_verified' => 'Rekord PTR nie jest jeszcze poprawny.',
        'set' => 'Rekord PTR został ustawiony u dostawcy.',
        'not_supported' => 'Rekordu PTR tego adresu nie da się ustawić z NetSendo. Ustaw go w panelu firmy, od której pochodzi adres.',
        'no_hostname' => 'Adres nie ma nazwy hosta, na którą miałby wskazywać.',
    ],
    'provision' => [
        'hostname' => 'nazwa hosta',
        'server' => 'serwer',
    ],
];
