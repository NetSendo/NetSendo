<?php

/**
 * German translations - Flash messages
 */

return [
    // Success messages
    'saved' => 'Erfolgreich gespeichert.',
    'created' => 'Erfolgreich erstellt.',
    'updated' => 'Erfolgreich aktualisiert.',
    'deleted' => 'Erfolgreich gelöscht.',
    'imported' => 'Erfolgreich importiert.',
    'exported' => 'Erfolgreich exportiert.',

    // Error messages
    'error_occurred' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
    'not_found' => 'Ressource nicht gefunden.',
    'unauthorized' => 'Sie sind nicht berechtigt, diese Aktion auszuführen.',
    'validation_error' => 'Bitte überprüfen Sie Ihre Eingaben und versuchen Sie es erneut.',

    // Confirmation messages
    'confirm_delete' => 'Sind Sie sicher, dass Sie dies löschen möchten?',
    'confirm_action' => 'Sind Sie sicher, dass Sie fortfahren möchten?',
    'action_irreversible' => 'Diese Aktion kann nicht rückgängig gemacht werden.',
    'campaign' => [
        'invalid_lists' => 'Ungültige Empfängerliste.',
        'invalid_excluded_lists' => 'Ungültige Ausschlussliste.',
        'invalid_mailbox' => 'Ungültiges Absenderpostfach.',
        'scheduled' => 'Die Nachricht wurde geplant.',
        'queued' => 'Die Nachricht wurde in die Versandwarteschlange gestellt.',
        'saved_draft' => 'Die Nachricht wurde als Entwurf gespeichert.',
        'saved' => 'Die Nachricht wurde gespeichert.',
        'updated' => 'Die Nachricht wurde aktualisiert.',
        'resend_broadcast_only' => 'Nur einmalige Nachrichten (Broadcasts) können erneut gesendet werden.',
        'resend_no_new_recipients' => 'Keine neuen Empfänger: Alle Abonnenten haben diese Nachricht bereits erhalten.',
        'resend_scheduled' => 'Die Nachricht wurde geplant. Neue Empfänger: :count.',
        'deleted' => 'Die Nachricht wurde gelöscht.',
        'queue_only_active' => 'Nur Warteschlangen-Nachrichten haben einen Aktivstatus.',
        'activated' => 'Die Nachricht wurde aktiviert.',
        'deactivated' => 'Die Nachricht wurde deaktiviert.',
        'queue_only_feature' => 'Diese Funktion ist nur für Warteschlangen-Nachrichten verfügbar.',
        'must_be_active' => 'Die Nachricht muss aktiv sein, um an übersprungene Abonnenten zu senden.',
        'no_skipped' => 'Keine übersprungenen Abonnenten zum Senden.',
        'skipped_scheduled' => 'Der Versand an :count übersprungene Abonnenten wurde geplant.',
        'automation_description' => 'Automatisierung aus Nachricht #:id erstellt',
    ],
];
