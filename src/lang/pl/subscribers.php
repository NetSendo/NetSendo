<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Teksty subskrybentów używane po stronie serwera (flash, walidacja).
    |--------------------------------------------------------------------------
    */

    'crm_add_failed' => 'Nie udało się dodać subskrybenta do CRM.',
    'crm_added_success' => 'Subskrybent został dodany do CRM jako lead.',
    'crm_already_exists' => 'Kontakt CRM już istnieje dla tego subskrybenta.',
    'crm_log_activity' => 'Subskrybent dodany do CRM jako lead',

    'import' => [
        'result' => 'Import zakończony: dodano :created, zaktualizowano :updated, pominięto :skipped.',

        'errors' => [
            'empty_file' => 'Plik jest pusty albo nie zawiera żadnych danych.',
            'invalid_json' => 'Nie udało się odczytać pliku JSON — sprawdź, czy nie został uszkodzony.',
            'truncated' => 'Plik został obcięty do :limit wierszy. Resztę zaimportuj w kolejnym pliku.',
            'list_not_accessible' => 'Nie masz dostępu do wybranej listy.',
            'list_required' => 'Wybierz listę docelową albo włącz odtwarzanie przypisań z kolumny „lists”.',
        ],
    ],

];
