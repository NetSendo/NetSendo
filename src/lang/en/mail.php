<?php

return [
    'new_subscriber_notification_subject' => 'New subscriber on list :list',
    'errors' => [
        'no_mailbox_for_type' => 'No active mailbox is allowed to send ":type" messages. Allow this type in the mailbox settings (Settings > Mailboxes > Allowed types).',
        'no_mailbox' => 'No mailbox is set up. Set one up in the settings before sending messages.',
        'campaign_sender_type' => 'The campaign\'s mailbox ":mailbox" (:email) does not allow One-time (Broadcast) messages (Gmail mailboxes never do), and none of the campaign\'s lists has a broadcast mailbox, so this campaign could not be sent. It was not sent from another mailbox. Set a broadcast mailbox in the list settings (Sending): the list\'s campaigns go out from it. Or choose a mailbox that allows campaigns.',
        'individual_sender' => [
            'inactive' => 'This individual email was queued to go from the mailbox ":mailbox" (:email), which is now switched off or removed. It was not sent from another mailbox. Switch the mailbox on and send the email again.',
            'type' => 'The mailbox ":mailbox" (:email) allows neither broadcast nor autoresponder messages, so it cannot send this individual email. It was not sent from another mailbox. Allow one of these types in the mailbox settings (Settings > Mailboxes > Allowed types).',
            'limit' => 'The mailbox ":mailbox" (:email) has used up its daily sending limit. This individual email was not sent from another mailbox. Send it again after the limit resets, or raise the limit in the mailbox settings.',
        ],
    ],
];
