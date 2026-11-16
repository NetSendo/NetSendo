<?php

// Pages of a sleeping NetSendo Cloud instance (App\Http\Middleware\DormantMode)
return [
    'asleep' => [
        'title' => 'This NetSendo instance is asleep',
        'body' => 'The subscription for this instance has ended. Its data is kept safe, but sign-in and sending are paused.',
        'owner' => 'Are you the owner? Resume it with the link in the email from NetSendo and it will be back in a few minutes, at the same address and with all its data. You can also write to contact@netsendo.com.',
        'unsubscribe' => 'Want to stop receiving messages from this sender? The unsubscribe link in their email still works.',
    ],
    'unsubscribe' => [
        'title' => 'Unsubscribe',
        'body' => 'Confirm that you no longer want to receive messages from this list.',
        'body_all' => 'Confirm that you no longer want to receive any messages from this sender. You will be unsubscribed from all their lists.',
        'button' => 'Unsubscribe me',
        'invalid_title' => 'This link is not valid',
        'invalid' => 'Use the unsubscribe link from the message exactly as you received it.',
    ],
];
