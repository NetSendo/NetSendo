<?php

/**
 * Mailing list flash and error messages (panel)
 */

return [
    'flash' => [
        'created' => 'Lista adresowa została utworzona.',
        'updated' => 'Lista adresowa została zaktualizowana.',
        'deleted' => 'Lista adresowa została usunięta.',
        'no_edit_permission' => 'Brak uprawnień do edycji tej listy.',
        'owner_only_delete' => 'Tylko właściciel listy może ją usunąć.',
        'transfer_same_list' => 'Nie można przenieść subskrybentów do tej samej listy.',
        'has_subscribers' => 'Ta lista zawiera subskrybentów. Wybierz opcję przeniesienia lub usunięcia ich.',
        'api_key_generated' => 'Nowy klucz API został wygenerowany.',
        'webhook_test_sent' => 'Testowy webhook został wysłany pomyślnie!',
        'webhook_test_failed' => 'Nie udało się wysłać testowego webhooka. Sprawdź URL i logi.',
        'no_copy_permission' => 'Brak uprawnień do kopiowania tej listy.',
        'copy_success' => 'Lista została skopiowana!',
    ],
    'validation' => [
        'broadcast_mailbox_type' => 'Skrzynka ":mailbox" (:email) nie ma włączonego typu Jednorazowe (Broadcast) (skrzynki Gmail nigdy go nie mają), więc nie wyśle kampanii tej listy. Wybierz skrzynkę, która może je wysyłać, zwykle skrzynkę SMTP na Twojej domenie.',
    ],
];
