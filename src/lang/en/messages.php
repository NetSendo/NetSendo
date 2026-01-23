<?php

/**
 * English translations - Flash messages
 */

return [
    // Success messages
    'saved' => 'Saved successfully.',
    'created' => 'Created successfully.',
    'updated' => 'Updated successfully.',
    'deleted' => 'Deleted successfully.',
    'imported' => 'Imported successfully.',
    'exported' => 'Exported successfully.',

    // Error messages
    'error_occurred' => 'An error occurred. Please try again.',
    'not_found' => 'Resource not found.',
    'unauthorized' => 'You are not authorized to perform this action.',
    'validation_error' => 'Please check your input and try again.',

    // Confirmation messages
    'confirm_delete' => 'Are you sure you want to delete this?',
    'confirm_action' => 'Are you sure you want to continue?',
    'action_irreversible' => 'This action cannot be undone.',

    // Stats & Queue
    'stats' => [
        'queue' => [
            'no_failed_recipients' => 'No failed recipients found to resend.',
            'resend_scheduled' => 'Resend scheduled for :count failed recipients.',
            'resend_error' => 'An error occurred while rescheduling the message.',
        ],
    ],
];
