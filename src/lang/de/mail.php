<?php

return [
    'new_subscriber_notification_subject' => 'Neuer Abonnent auf Liste :list',
    'errors' => [
        'no_mailbox_for_type' => 'Kein aktives Postfach darf Nachrichten vom Typ „:type" senden. Erlauben Sie diesen Typ in den Postfach-Einstellungen (Einstellungen > Postfächer > Erlaubte Typen).',
        'no_mailbox' => 'Es ist kein Postfach eingerichtet. Richten Sie in den Einstellungen ein Postfach ein, bevor Sie Nachrichten senden.',
        'campaign_sender_type' => 'Das Postfach „:mailbox“ (:email) dieser Kampagne erlaubt keine Einmalig-Nachrichten (Broadcast) (Gmail-Postfächer nie), und keine Liste der Kampagne hat ein Massenversand-Postfach, daher konnte die Kampagne nicht gesendet werden. Sie wurde nicht über ein anderes Postfach gesendet. Legen Sie in den Listeneinstellungen (Versand) ein Massenversand-Postfach fest: Die Kampagnen der Liste gehen von dort aus. Oder wählen Sie ein Postfach, das Kampagnen senden darf.',
        'individual_sender' => [
            'inactive' => 'Diese Einzel-E-Mail sollte über das Postfach „:mailbox“ (:email) gehen, das jetzt ausgeschaltet oder gelöscht ist. Sie wurde nicht über ein anderes Postfach gesendet. Schalten Sie das Postfach ein und senden Sie die E-Mail erneut.',
            'type' => 'Das Postfach „:mailbox“ (:email) erlaubt weder Einmalig (Broadcast) noch Autoresponder und kann diese Einzel-E-Mail daher nicht senden. Sie wurde nicht über ein anderes Postfach gesendet. Erlauben Sie einen dieser Typen in den Postfach-Einstellungen (Einstellungen > Postfächer > Erlaubte Typen).',
            'limit' => 'Das Postfach „:mailbox“ (:email) hat sein tägliches Sendelimit ausgeschöpft. Diese Einzel-E-Mail wurde nicht über ein anderes Postfach gesendet. Senden Sie sie erneut, sobald das Limit zurückgesetzt ist, oder erhöhen Sie das Limit in den Postfach-Einstellungen.',
        ],
    ],
];
