<?php

/**
 * Polish translations - Flash messages
 */

return [
    // Success messages
    'saved' => 'Zapisano pomyślnie.',
    'created' => 'Utworzono pomyślnie.',
    'updated' => 'Zaktualizowano pomyślnie.',
    'deleted' => 'Usunięto pomyślnie.',
    'imported' => 'Zaimportowano pomyślnie.',
    'exported' => 'Wyeksportowano pomyślnie.',

    // Error messages
    'error_occurred' => 'Wystąpił błąd. Spróbuj ponownie.',
    'not_found' => 'Zasób nie został znaleziony.',
    'unauthorized' => 'Nie masz uprawnień do wykonania tej akcji.',
    'validation_error' => 'Sprawdź wprowadzone dane i spróbuj ponownie.',

    // Confirmation messages
    'confirm_delete' => 'Czy na pewno chcesz to usunąć?',
    'confirm_action' => 'Czy na pewno chcesz kontynuować?',
    'action_irreversible' => 'Ta akcja jest nieodwracalna.',

    // Stats & Queue
    'stats' => [
        'queue' => [
            'no_failed_recipients' => 'Nie znaleziono odbiorców z błędami do ponownej wysyłki.',
            'resend_scheduled' => 'Zaplanowano ponowną wysyłkę dla :count odbiorców z błędami.',
            'resend_error' => 'Wystąpił błąd podczas planowania ponownej wysyłki.',
        ],
    ],
    'campaign' => [
        'invalid_lists' => 'Nieprawidłowa lista odbiorców.',
        'invalid_excluded_lists' => 'Nieprawidłowa lista wykluczeń.',
        'invalid_mailbox' => 'Nieprawidłowa skrzynka nadawcza.',
        'scheduled' => 'Wiadomość została zaplanowana.',
        'queued' => 'Wiadomość została wysłana do kolejki.',
        'saved_draft' => 'Wiadomość została zapisana jako szkic.',
        'saved' => 'Wiadomość została zapisana.',
        'updated' => 'Wiadomość została zaktualizowana.',
        'resend_broadcast_only' => 'Tylko wiadomości jednorazowe (broadcast) mogą być ponownie wysłane.',
        'resend_no_new_recipients' => 'Brak nowych odbiorców do wysłania. Wszyscy subskrybenci już otrzymali tę wiadomość.',
        'resend_scheduled' => 'Wiadomość została zaplanowana do wysłania. Nowych odbiorców: :count.',
        'deleted' => 'Wiadomość została usunięta.',
        'queue_only_active' => 'Tylko wiadomości typu Kolejka mogą mieć status aktywności.',
        'activated' => 'Wiadomość została aktywowana.',
        'deactivated' => 'Wiadomość została dezaktywowana.',
        'queue_only_feature' => 'Ta funkcja jest dostępna tylko dla wiadomości typu Kolejka.',
        'must_be_active' => 'Wiadomość musi być aktywna, aby wysyłać do pominiętych subskrybentów.',
        'no_skipped' => 'Brak pominiętych subskrybentów do wysłania.',
        'skipped_scheduled' => 'Zaplanowano wysyłkę do :count pominiętych subskrybentów.',
        'automation_description' => 'Automatyzacja utworzona z wiadomości #:id',
    ],
];
