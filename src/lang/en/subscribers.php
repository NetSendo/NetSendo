<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscriber strings used server-side (flash messages, validation).
    |--------------------------------------------------------------------------
    */

    'crm_add_failed' => 'Failed to add subscriber to CRM.',
    'crm_added_success' => 'Subscriber has been added to CRM as a lead.',
    'crm_already_exists' => 'CRM contact already exists for this subscriber.',
    'crm_log_activity' => 'Subscriber added to CRM as a lead',

    'import' => [
        'result' => 'Import finished: :created added, :updated updated, :skipped skipped.',

        'errors' => [
            'empty_file' => 'The file is empty or contains no data.',
            'invalid_json' => 'The JSON file could not be read — check whether it is damaged.',
            'truncated' => 'The file was truncated to :limit rows. Import the remainder as a separate file.',
            'list_not_accessible' => 'You do not have access to the selected list.',
            'list_required' => 'Choose a target list, or enable restoring memberships from the "lists" column.',
        ],
    ],

];
