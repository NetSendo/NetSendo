<?php

/**
 * German translations - Automations Module
 */

return [
    'title' => 'Automatisierungen',
    'create' => 'Neue Automatisierung',
    'edit' => 'Automatisierung bearbeiten',
    'delete' => 'Automatisierung löschen',
    'logs' => 'Ausführungsprotokolle',
    
    'no_rules' => 'Keine Automatisierungen',
    'no_rules_hint' => 'Erstellen Sie Ihre erste Automatisierung, um automatisch auf Ereignisse zu reagieren.',
    'create_first' => 'Erste Automatisierung erstellen',
    
    'basic_info' => 'Basis-Informationen',
    'name_placeholder' => 'z.B. Begrüßung neuer Abonnenten',
    'description_placeholder' => 'Beschreibung (optional)',
    
    'when' => 'WENN',
    'if' => 'FALLS',
    'then' => 'DANN',
    
    'trigger' => 'Trigger',
    'trigger_event' => 'Auslösendes Ereignis',
    'actions_count' => 'Aktionen',
    'executions' => 'Ausführungen',
    
    'filter_by_list' => 'Nach Liste filtern',
    'filter_by_message' => 'Nach Nachricht filtern',
    'filter_by_form' => 'Nach Formular filtern',
    'filter_by_tag' => 'Nach Tag filtern',
    
    'add_condition' => 'Bedingung hinzufügen',
    'no_conditions_hint' => 'Keine Bedingungen - Automatisierung wird bei jedem Ereignis ausgelöst.',
    'all_conditions' => 'Alle Bedingungen müssen erfüllt sein',
    'any_condition' => 'Mindestens eine Bedingung muss erfüllt sein',
    'value' => 'Wert',
    
    'add_action' => 'Aktion hinzufügen',
    'no_actions_hint' => 'Fügen Sie mindestens eine Aktion hinzu, die ausgeführt werden soll.',
    'select_tag' => 'Tag wählen',
    'select_list' => 'Liste wählen',
    'select_message' => 'Nachricht wählen',
    'select_funnel' => 'Lejek/Trichter wählen',
    'select_field' => 'Feld wählen',
    'webhook_url' => 'Webhook-URL',
    'admin_email' => 'Admin-E-Mail',
    'email_subject' => 'E-Mail-Betreff',
    'notification_message' => 'Benachrichtigungstext',
    'new_value' => 'Neuer Wert',
    
    'rate_limiting' => 'Ausführungsbegrenzung',
    'limit_per_subscriber' => 'Ausführungen pro Abonnent begrenzen',
    'max' => 'Maximal',
    'times' => 'Mal',
    'per_hour' => 'pro Stunde',
    'per_day' => 'pro Tag',
    'per_week' => 'pro Woche',
    'per_month' => 'pro Monat',
    'ever' => 'insgesamt',
    
    'activate_immediately' => 'Sofort aktivieren',
    
    'confirm_duplicate' => 'Möchten Sie diese Automatisierung duplizieren?',
    'confirm_delete' => 'Sind Sie sicher, dass Sie diese Automatisierung löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.',
    
    'triggers' => [
        'subscriber_signup' => 'Anmeldung zur Liste',
        'subscriber_activated' => 'Abonnent aktiviert',
        'email_opened' => 'E-Mail geöffnet',
        'email_clicked' => 'Link geklickt',
        'subscriber_unsubscribed' => 'Von Liste abgemeldet',
        'email_bounced' => 'E-Mail zurückgewiesen (Bounce)',
        'form_submitted' => 'Formular abgeschickt',
        'tag_added' => 'Tag hinzugefügt',
        'tag_removed' => 'Tag entfernt',
        'field_updated' => 'Feld aktualisiert',
    ],
    
    'actions' => [
        'send_email' => 'E-Mail senden',
        'add_tag' => 'Tag hinzufügen',
        'remove_tag' => 'Tag entfernt',
        'move_to_list' => 'In Liste verschieben',
        'copy_to_list' => 'In Liste kopieren',
        'unsubscribe' => 'Von Liste abmelden',
        'call_webhook' => 'Webhook aufrufen',
        'start_funnel' => 'Trichter starten',
        'update_field' => 'Feld aktualisieren',
        'notify_admin' => 'Admin benachrichtigen',
    ],
    
    'log_status' => [
        'success' => 'Erfolg',
        'partial' => 'Teilerfolg',
        'failed' => 'Fehlgeschlagen',
        'skipped' => 'Übersprungen',
    ],
];
