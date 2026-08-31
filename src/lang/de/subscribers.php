<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Abonnenten-Texte, die serverseitig verwendet werden (Flash, Validierung).
    |--------------------------------------------------------------------------
    */

    'crm_add_failed' => 'Abonnent konnte nicht zum CRM hinzugefügt werden.',
    'crm_added_success' => 'Abonnent wurde als Lead zum CRM hinzugefügt.',
    'crm_already_exists' => 'Für diesen Abonnenten existiert bereits ein CRM-Kontakt.',
    'crm_log_activity' => 'Abonnent als Lead zum CRM hinzugefügt',

    'import' => [
        'result' => 'Import abgeschlossen: :created hinzugefügt, :updated aktualisiert, :skipped übersprungen.',

        'errors' => [
            'empty_file' => 'Die Datei ist leer oder enthält keine Daten.',
            'invalid_json' => 'Die JSON-Datei konnte nicht gelesen werden — prüfen Sie, ob sie beschädigt ist.',
            'truncated' => 'Die Datei wurde auf :limit Zeilen gekürzt. Importieren Sie den Rest als separate Datei.',
            'list_not_accessible' => 'Sie haben keinen Zugriff auf die ausgewählte Liste.',
            'list_required' => 'Wählen Sie eine Zielliste oder aktivieren Sie das Wiederherstellen der Zugehörigkeiten aus der Spalte „lists“.',
        ],
    ],

];
