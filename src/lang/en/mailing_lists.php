<?php

/**
 * Mailing list flash and error messages (panel)
 */

return [
    'flash' => [
        'created' => 'The list has been created.',
        'updated' => 'The list has been updated.',
        'deleted' => 'The list has been deleted.',
        'no_edit_permission' => 'You do not have permission to edit this list.',
        'owner_only_delete' => 'Only the owner of the list can delete it.',
        'transfer_same_list' => 'Subscribers cannot be moved to the same list.',
        'has_subscribers' => 'This list has subscribers. Choose whether to move them or delete them.',
        'api_key_generated' => 'A new API key has been generated.',
        'webhook_test_sent' => 'The test webhook has been sent.',
        'webhook_test_failed' => 'The test webhook could not be sent. Check the URL and the logs.',
        'no_copy_permission' => 'You do not have permission to copy this list.',
        'copy_success' => 'List copied successfully!',
    ],
    'validation' => [
        'broadcast_mailbox_type' => 'The mailbox ":mailbox" (:email) does not allow One-time (Broadcast) messages (Gmail mailboxes never do), so it cannot send the list\'s campaigns. Choose a mailbox that allows them, usually an SMTP mailbox on your domain.',
    ],
];
