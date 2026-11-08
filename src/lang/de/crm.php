<?php

return [
    'reminders' => [
        'email_subject' => 'Erinnerung: :title',
        'title' => '⏰ Aufgabenerinnerung',
        'subtitle' => 'Sie haben eine geplante Aufgabe zu erledigen',
        'type' => 'Typ',
        'due_date' => 'Fälligkeitsdatum',
        'priority' => 'Priorität',
        'contact' => 'Kontakt',
        'view_in_crm' => 'Im CRM anzeigen',
        'footer_auto' => 'Diese Nachricht wurde automatisch von :appName gesendet.',
        'footer_settings' => 'Sie können die Benachrichtigungseinstellungen in Ihrem Profil ändern.',
        'deal' => 'Deal: :name',
        'types' => [
            'call' => '📞 Anruf',
            'email' => '✉️ E-Mail',
            'meeting' => '📅 Treffen',
            'follow_up' => '🔄 Follow-up',
            'other' => '📋 Sonstiges',
        ],
        'priorities' => [
            'high' => 'Hoch',
            'medium' => 'Mittel',
            'low' => 'Niedrig',
        ]
    ],
    'calendar' => [
        'sync_label' => 'Mit Google Kalender synchronisieren',
        'select_calendar' => 'Kalender auswählen',
        'google_calendar' => 'Google Kalender',
        'sync_all' => 'Alle Aufgaben synchronisieren',
        'syncing' => 'Synchronisierung...',
        'bulk_sync_description' => 'Alle ausstehenden CRM-Aufgaben mit einem Klick mit Google Kalender synchronisieren.',
        'sync_success' => ':count Aufgaben synchronisiert',
        'synced' => 'Synchronisiert',
        'sync_error' => 'Ein Fehler ist bei der Synchronisierung aufgetreten.',
    ],
    'recurrence' => [
        'is_recurring' => 'Wiederkehrende Aufgabe',
        'helper_text' => 'Wiederholt sich automatisch',
        'frequency' => [
            'label' => 'Wiederholen alle',
            'daily' => '{1} Tag|{n} Tage',
            'weekly' => '{1} Woche|{n} Wochen',
            'monthly' => '{1} Monat|{n} Monate',
            'yearly' => '{1} Jahr|{n} Jahre',
        ],
        'days_of_week' => 'Wochentage',
        'days' => [
            'mon' => 'Mo',
            'tue' => 'Di',
            'wed' => 'Mi',
            'thu' => 'Do',
            'fri' => 'Fr',
            'sat' => 'Sa',
            'sun' => 'So',
        ],
        'end_condition' => [
            'label' => 'Endet',
            'never' => 'Niemals',
            'date' => 'Am Datum:',
            'count' => 'Nach',
            'occurrences' => '{1} Vorkommen|{n} Vorkommen',
        ],
    ],
    'conflicts' => [
        'title' => 'Synchronisierungskonflikt erkannt',
        'description' => 'Diese Aufgabe wurde sowohl lokal als auch im Google Kalender geändert',
        'detected_at' => 'Konflikt erkannt: :date',
        'local_version' => 'Lokale Version (NetSendo)',
        'remote_version' => 'Remote-Version (Google Kalender)',
        'use_local' => 'Lokale Version verwenden',
        'use_remote' => 'Remote-Version verwenden',
        'cancel' => 'Abbrechen',
        'no_conflict' => 'Aufgabe hat keinen Konflikt zu lösen.',
        'resolved_local' => 'Konflikt gelöst - lokale Version verwendet.',
        'resolved_remote' => 'Konflikt gelöst - Remote-Version verwendet.',
    ],
    'task' => [
        'title' => [
            'new' => 'Neue Aufgabe',
            'edit' => 'Aufgabe bearbeiten',
        ],
        'fields' => [
            'title' => 'Aufgabentitel *',
            'title_placeholder' => 'z.B. Kunden wegen Angebot anrufen',
            'type' => 'Aufgabentyp',
            'priority' => 'Priorität',
            'due_date' => 'Fälligkeitsdatum',
            'contact' => 'Kontakt zuweisen',
            'contact_placeholder' => '-- Kein Kontakt --',
            'owner' => 'Besitzer zuweisen',
            'owner_auto' => '-- Automatisch (ich) --',
            'description' => 'Beschreibung (optional)',
            'description_placeholder' => 'Zusätzliche Details zur Aufgabe...',
        ],
        'calendar' => [
            'sync' => 'Mit Google Kalender synchronisieren',
            'select_calendar' => 'Kalender auswählen',
            'default' => 'Standard',
            'primary' => 'Primär',
            'synced' => 'Mit Kalender synchronisiert',
        ],
        'meet' => [
            'include_meet' => 'Google Meet Link hinzufügen',
            'include_meet_desc' => 'Automatisch Videokonferenz-Link erstellen',
            'attendees' => 'Teilnehmer',
            'add_email' => 'E-Mail hinzufügen und Enter drücken',
            'attendees_hint' => 'Enter drücken zum Hinzufügen. Kontakt-E-Mail wird automatisch hinzugefügt.',
            'meeting_ready' => 'Meeting-Link bereit',
            'join' => 'Am Meeting teilnehmen',
            'status' => [
                'accepted' => 'Akzeptiert',
                'declined' => 'Abgelehnt',
                'tentative' => 'Vielleicht',
                'needsAction' => 'Wartet auf Antwort',
            ],
            'add_guest' => 'Hinzufügen',
            'not_connected' => 'Verbinden Sie Ihr Google-Konto in den Einstellungen, um zu aktivieren',
        ],
        'zoom' => [
            'include_zoom' => 'Zoom Meeting Link hinzufügen',
            'include_zoom_desc' => 'Automatisch Zoom Videokonferenz erstellen',
            'meeting_ready' => 'Zoom Meeting bereit',
            'join' => 'Zoom beitreten',
            'attendees' => 'Eingeladene Gäste',
            'add_email' => 'Gast-E-Mail eingeben...',
            'add_guest' => 'Hinzufügen',
            'attendees_hint' => 'Gäste erhalten eine Einladung mit Meeting-Link',
            'not_connected' => 'Verbinden Sie Ihr Zoom-Konto in den Einstellungen, um zu aktivieren',
        ],
        'actions' => [
            'cancel' => 'Abbrechen',
            'save' => 'Änderungen speichern',
            'create' => 'Aufgabe erstellen',
        ],
        'empty' => [
            'title' => 'Keine Aufgaben',
            'description' => 'Sie haben noch keine Aufgaben in dieser Kategorie.',
            'button' => 'Erste Aufgabe hinzufügen',
        ],
    ],
    'contacts' => [
        'search_or_email' => 'Suchen oder E-Mail eingeben',
        'search_placeholder' => 'E-Mail oder Namen eingeben...',
        'search_hint' => 'Geben Sie mind. 2 Zeichen ein, um nach einem bestehenden Abonnenten zu suchen oder eine neue E-Mail einzugeben',
        'existing_subscriber' => 'Bestehender Abonnent',
        'found_subscribers' => 'Gefundene Abonnenten',
        'title' => [
            'edit' => 'Kontakt bearbeiten',
        ],
        'edit_success' => 'Kontakt wurde aktualisiert',
        'actions' => [
            'edit' => 'Bearbeiten',
        ],
    ],

    // Default lead scoring rules (LeadScoringRule::getDefaultRules())
    'scoring' => [
        'defaults' => [
            'email_opened' => [
                'name' => 'E-Mail geöffnet',
                'description' => 'Punkte für das Öffnen einer E-Mail',
            ],
            'email_clicked' => [
                'name' => 'Link angeklickt',
                'description' => 'Punkte für das Klicken auf einen Link in einer E-Mail',
            ],
            'email_replied' => [
                'name' => 'E-Mail beantwortet',
                'description' => 'Bonus für das Antworten auf eine Nachricht',
            ],
            'contact_created' => [
                'name' => 'Kontakt erstellt',
                'description' => 'Startpunkte bei Erstellung eines CRM-Kontakts',
            ],
            'form_submitted' => [
                'name' => 'Formular gesendet',
                'description' => 'Punkte für das Absenden eines beliebigen Formulars',
            ],
            'page_visited' => [
                'name' => 'Seite besucht',
                'description' => 'Punkte für den Besuch einer Seite',
            ],
            'page_visited_pricing' => [
                'name' => 'Preisseite besucht',
                'description' => 'Bonus für den Besuch der Preisseite',
            ],
            'product_viewed' => [
                'name' => 'Produkt angesehen',
                'description' => 'Punkte für das Ansehen einer Produktseite',
            ],
            'add_to_cart' => [
                'name' => 'In den Warenkorb',
                'description' => 'Punkte für das Hinzufügen eines Produkts zum Warenkorb',
            ],
            'checkout_started' => [
                'name' => 'Kasse gestartet',
                'description' => 'Bonus für den Start des Kaufvorgangs',
            ],
            'tag_added_hot' => [
                'name' => 'Tag "hot"',
                'description' => 'Bonus für den Erhalt des Tags \'hot\'',
            ],
            'tag_added_vip' => [
                'name' => 'Tag "vip"',
                'description' => 'Bonus für den Erhalt des Tags \'vip\'',
            ],
            'decay_7_days' => [
                'name' => 'Inaktivität 7 Tage',
                'description' => 'Automatischer Punkteverfall nach 7 Tagen Inaktivität',
            ],
            'decay_30_days' => [
                'name' => 'Inaktivität 30 Tage',
                'description' => 'Großer Punkteverfall nach 30 Tagen Inaktivität',
            ],
        ],
    ],
];
