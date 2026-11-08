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
    'campaign' => [
        'invalid_lists' => 'Invalid recipient list.',
        'invalid_excluded_lists' => 'Invalid exclusion list.',
        'invalid_mailbox' => 'Invalid sending mailbox.',
        'scheduled' => 'The message has been scheduled.',
        'queued' => 'The message has been queued for sending.',
        'saved_draft' => 'The message has been saved as a draft.',
        'saved' => 'The message has been saved.',
        'updated' => 'The message has been updated.',
        'resend_broadcast_only' => 'Only one-off messages (broadcasts) can be sent again.',
        'resend_no_new_recipients' => 'No new recipients: every subscriber has already received this message.',
        'resend_scheduled' => 'The message has been scheduled. New recipients: :count.',
        'deleted' => 'The message has been deleted.',
        'queue_only_active' => 'Only queue messages have an active status.',
        'activated' => 'The message has been activated.',
        'deactivated' => 'The message has been deactivated.',
        'queue_only_feature' => 'This feature is only available for queue messages.',
        'must_be_active' => 'The message must be active to send to skipped subscribers.',
        'no_skipped' => 'No skipped subscribers to send to.',
        'skipped_scheduled' => 'Sending to :count skipped subscribers has been scheduled.',
        'automation_description' => 'Automation created from message #:id',
    ],
];
