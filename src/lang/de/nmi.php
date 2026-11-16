<?php

return [
    'dkim_generated' => 'DKIM-Schlüssel erfolgreich generiert!',
    'dkim_not_verified' => 'DKIM-Eintrag nicht gefunden. Überprüfen Sie Ihre DNS-Konfiguration.',
    'dkim_verified' => 'DKIM erfolgreich verifiziert!',
    'invalid_ip_address' => 'Ungültiges IP-Adressformat.',
    'ip_added' => 'IP-Adresse wurde erfolgreich hinzugefügt!',
    'ip_deleted' => 'IP-Adresse wurde gelöscht.',
    'ip_has_mailboxes' => 'IP-Adressen mit zugewiesenen Postfächern können nicht gelöscht werden.',
    'ip_provisioned' => 'IP-Adresse erfolgreich bestellt!',
    'pool_created' => 'IP-Pool erfolgreich erstellt!',
    'pool_deleted' => 'IP-Pool gelöscht.',
    'pool_max_ips_reached' => 'Dieser Pool hat die maximale Anzahl an IP-Adressen erreicht.',
    'pool_not_empty' => 'Ein Pool, der IP-Adressen enthält, kann nicht gelöscht werden.',
    'provider_not_configured' => 'Dieser Anbieter ist nicht konfiguriert. Fügen Sie einen API-Schlüssel in den Anbieter-Einstellungen hinzu.',
    'provider_saved' => 'Anbieter-Einstellungen erfolgreich gespeichert!',
    'warming_started' => 'IP-Warming gestartet!',
    'blacklist_checked' => 'Blacklist-Prüfung abgeschlossen.',

    // IP providers (addresses ordered through a hosting company's API) and reverse DNS
    'ip_release_failed' => 'Der Anbieter hat die Adresse nicht zurückgenommen, sie würde weiter berechnet: :reason',
    'providers' => [
        'errors' => [
            'unreachable' => ':provider ist nicht erreichbar. Versuchen Sie es gleich noch einmal.',
            'unauthorized' => ':provider hat den API-Schlüssel nicht akzeptiert: :reason',
            'refused' => ':provider hat die Anfrage abgelehnt: :reason',
            'server_not_found' => 'Dieser Server ist nicht mehr in Ihrem :provider-Konto.',
            'digitalocean_unusable' => 'DigitalOcean kann keine dedizierten IPs für E-Mail bereitstellen: SMTP ist auf Reserved IPs gesperrt, und sie erhalten kein Reverse DNS.',
        ],
    ],
    'ptr' => [
        'verified' => 'Reverse DNS ist korrekt: Die Adresse nennt ihren Host, und der Host zeigt zurück.',
        'not_verified' => 'Reverse DNS ist noch nicht korrekt.',
        'set' => 'Reverse DNS wurde beim Anbieter gesetzt.',
        'not_supported' => 'Reverse DNS dieser Adresse lässt sich nicht aus NetSendo setzen. Setzen Sie es im Panel des Unternehmens, von dem die Adresse stammt.',
        'no_hostname' => 'Die Adresse hat keinen Hostnamen, auf den sie zurückzeigen könnte.',
    ],
    'provision' => [
        'hostname' => 'Hostname',
        'server' => 'Server',
    ],
];
