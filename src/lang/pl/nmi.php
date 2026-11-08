<?php

// Messages of NetSendo Mail Infrastructure (NMI): IP pools, dedicated IPs and NMI on domains
return [
    'pool_created' => 'Pula IP została utworzona!',
    'pool_deleted' => 'Pula IP została usunięta.',
    'pool_not_empty' => 'Nie można usunąć puli zawierającej adresy IP.',
    'pool_max_ips_reached' => 'Ta pula osiągnęła maksymalną liczbę adresów IP.',
    'warming_started' => 'Ogrzewanie IP zostało rozpoczęte!',
    'dkim_generated' => 'Klucze DKIM zostały wygenerowane!',
    'dkim_verified' => 'DKIM zweryfikowany pomyślnie!',
    'dkim_not_verified' => 'Rekord DKIM nie został znaleziony. Sprawdź konfigurację DNS.',
    'ip_added' => 'Adres IP został dodany pomyślnie!',
    'ip_deleted' => 'Adres IP został usunięty.',
    'ip_has_mailboxes' => 'Nie można usunąć adresu IP z przypisanymi skrzynkami.',
    'invalid_ip_address' => 'Nieprawidłowy format adresu IP.',
    'provider_not_configured' => 'Ten dostawca nie jest skonfigurowany. Dodaj klucz API w ustawieniach dostawców.',
    'provider_saved' => 'Ustawienia dostawcy zapisane pomyślnie!',
    'ip_provisioned' => 'Adres IP zamówiony pomyślnie!',
    'blacklist_checked' => 'Czarne listy zostały sprawdzone.',
    'ip_assigned' => 'Adres IP wysyła teraz dla domeny :domain.',
    'ip_unassigned' => 'Adres IP nie jest już przypisany do domeny.',
    'ip_in_use' => 'Przez ten adres IP wysyłają skrzynki (:count). Najpierw przełącz je na inny adres IP lub innego dostawcę.',
    'domain_not_found' => 'Wybierz jedną ze swoich domen z Deliverability Shield.',
    'domain_enabled' => 'NetSendo MTA jest włączone dla domeny :domain.',
    'domain_disabled' => 'NetSendo MTA jest wyłączone dla domeny :domain.',
    'domain_not_verified' => 'Domena :domain nie jest jeszcze zweryfikowana. Najpierw dodaj jej rekord CNAME.',
    'domain_in_use' => 'Przez NetSendo MTA tej domeny wysyłają skrzynki (:count). Najpierw przełącz je na innego dostawcę.',
];
