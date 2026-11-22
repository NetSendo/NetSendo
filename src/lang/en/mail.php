<?php

return [
    'new_subscriber_notification_subject' => 'New subscriber on list :list',
    'errors' => [
        'no_mailbox_for_type' => 'No active mailbox is allowed to send ":type" messages. Allow this type in the mailbox settings (Settings > Mailboxes > Allowed types).',
        'no_mailbox' => 'No mailbox is set up. Set one up in the settings before sending messages.',
        'campaign_sender_type' => 'The campaign\'s mailbox ":mailbox" (:email) does not allow One-time (Broadcast) messages (Gmail mailboxes never do), so it cannot send this campaign. It was not sent from another mailbox. Choose a mailbox that allows campaigns, or send to one person as an individual email or a test.',
        'individual_sender' => [
            'inactive' => 'This individual email was queued to go from the mailbox ":mailbox" (:email), which is now switched off or removed. It was not sent from another mailbox. Switch the mailbox on and send the email again.',
            'type' => 'The mailbox ":mailbox" (:email) allows neither broadcast nor autoresponder messages, so it cannot send this individual email. It was not sent from another mailbox. Allow one of these types in the mailbox settings (Settings > Mailboxes > Allowed types).',
            'limit' => 'The mailbox ":mailbox" (:email) has used up its daily sending limit. This individual email was not sent from another mailbox. Send it again after the limit resets, or raise the limit in the mailbox settings.',
        ],
    ],
];
