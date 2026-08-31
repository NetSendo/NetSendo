<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Textos de suscriptores usados en el servidor (flash, validación).
    |--------------------------------------------------------------------------
    */

    'crm_add_failed' => 'No se pudo añadir el suscriptor al CRM.',
    'crm_added_success' => 'El suscriptor se ha añadido al CRM como lead.',
    'crm_already_exists' => 'Ya existe un contacto CRM para este suscriptor.',
    'crm_log_activity' => 'Suscriptor añadido al CRM como lead',

    'import' => [
        'result' => 'Importación finalizada: :created añadidos, :updated actualizados, :skipped omitidos.',

        'errors' => [
            'empty_file' => 'El archivo está vacío o no contiene datos.',
            'invalid_json' => 'No se pudo leer el archivo JSON — comprueba si está dañado.',
            'truncated' => 'El archivo se ha truncado a :limit filas. Importa el resto en un archivo aparte.',
            'list_not_accessible' => 'No tienes acceso a la lista seleccionada.',
            'list_required' => 'Elige una lista de destino o activa la restauración de pertenencias desde la columna «lists».',
        ],
    ],

];
