<?php

// Messages of NetSendo Mail Infrastructure (NMI): IP pools, dedicated IPs and NMI on domains
return [
    'pool_created' => 'IP-Pool erfolgreich erstellt!',
    'pool_deleted' => 'IP-Pool gelöscht.',
    'pool_not_empty' => 'Ein Pool, der IP-Adressen enthält, kann nicht gelöscht werden.',
    'pool_max_ips_reached' => 'Dieser Pool hat die maximale Anzahl an IP-Adressen erreicht.',
    'warming_started' => 'IP-Warming gestartet!',
    'dkim_generated' => 'DKIM-Schlüssel erfolgreich generiert!',
    'dkim_verified' => 'DKIM erfolgreich verifiziert!',
    'dkim_not_verified' => 'DKIM-Eintrag nicht gefunden. Überprüfen Sie Ihre DNS-Konfiguration.',
    'ip_added' => 'IP-Adresse wurde erfolgreich hinzugefügt!',
    'ip_deleted' => 'IP-Adresse wurde gelöscht.',
    'ip_has_mailboxes' => 'IP-Adressen mit zugewiesenen Postfächern können nicht gelöscht werden.',
    'invalid_ip_address' => 'Ungültiges IP-Adressformat.',
    'provider_not_configured' => 'Dieser Anbieter ist nicht konfiguriert. Fügen Sie einen API-Schlüssel in den Anbieter-Einstellungen hinzu.',
    'provider_saved' => 'Anbieter-Einstellungen erfolgreich gespeichert!',
    'ip_provisioned' => 'IP-Adresse erfolgreich bestellt!',
    'blacklist_checked' => 'Blacklists geprüft.',
    'ip_assigned' => 'Die IP-Adresse sendet jetzt für :domain.',
    'ip_unassigned' => 'Die IP-Adresse ist keiner Domain mehr zugewiesen.',
    'ip_in_use' => 'Postfächer senden über diese IP-Adresse (:count). Stellen Sie sie zuerst auf eine andere IP oder einen anderen Anbieter um.',
    'domain_not_found' => 'Wählen Sie eine Ihrer Domains aus dem Deliverability Shield.',
    'domain_enabled' => 'NetSendo MTA ist für :domain eingeschaltet.',
    'domain_disabled' => 'NetSendo MTA ist für :domain ausgeschaltet.',
    'domain_not_verified' => 'Die Domain :domain ist noch nicht verifiziert. Fügen Sie zuerst ihren CNAME-Eintrag hinzu.',
    'domain_in_use' => 'Postfächer senden über NetSendo MTA dieser Domain (:count). Stellen Sie sie zuerst auf einen anderen Anbieter um.',
];
