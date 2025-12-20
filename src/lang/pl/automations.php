<?php

/**
 * Polish translations - Automations Module
 */

return [
    'title' => 'Automatyzacje',
    'create' => 'Nowa automatyzacja',
    'edit' => 'Edytuj automatyzację',
    'delete' => 'Usuń automatyzację',
    'logs' => 'Historia wykonań',
    
    // List page
    'no_rules' => 'Brak automatyzacji',
    'no_rules_hint' => 'Utwórz pierwszą automatyzację, aby zacząć automatycznie reagować na zdarzenia.',
    'create_first' => 'Utwórz pierwszą automatyzację',
    
    // Builder
    'basic_info' => 'Podstawowe informacje',
    'name_placeholder' => 'Np. Powitanie nowych subskrybentów',
    'description_placeholder' => 'Opis automatyzacji (opcjonalnie)',
    
    'when' => 'KIEDY',
    'if' => 'JEŚLI',
    'then' => 'WTEDY',
    
    'trigger' => 'Trigger',
    'trigger_event' => 'Zdarzenie wyzwalające',
    'actions_count' => 'Akcje',
    'executions' => 'Wykonania',
    
    'filter_by_list' => 'Filtruj po liście',
    'filter_by_message' => 'Filtruj po wiadomości',
    'filter_by_form' => 'Filtruj po formularzu',
    'filter_by_tag' => 'Filtruj po tagu',
    
    // Conditions
    'add_condition' => 'Dodaj warunek',
    'no_conditions_hint' => 'Brak warunków - automatyzacja uruchomi się dla każdego wystąpienia zdarzenia.',
    'all_conditions' => 'Wszystkie warunki muszą być spełnione',
    'any_condition' => 'Którykolwiek warunek musi być spełniony',
    'value' => 'Wartość',
    
    // Actions
    'add_action' => 'Dodaj akcję',
    'no_actions_hint' => 'Dodaj co najmniej jedną akcję, która zostanie wykonana.',
    'select_tag' => 'Wybierz tag',
    'select_list' => 'Wybierz listę',
    'select_message' => 'Wybierz wiadomość',
    'select_funnel' => 'Wybierz lejek',
    'select_field' => 'Wybierz pole',
    'webhook_url' => 'URL webhooka',
    'admin_email' => 'Email administratora',
    'email_subject' => 'Temat emaila',
    'notification_message' => 'Treść powiadomienia',
    'new_value' => 'Nowa wartość',
    
    // Rate limiting
    'rate_limiting' => 'Ograniczenie wykonań',
    'limit_per_subscriber' => 'Ogranicz liczbę wykonań dla każdego subskrybenta',
    'max' => 'Maksymalnie',
    'times' => 'razy',
    'per_hour' => 'na godzinę',
    'per_day' => 'dziennie',
    'per_week' => 'tygodniowo',
    'per_month' => 'miesięcznie',
    'ever' => 'w ogóle',
    
    // Status
    'activate_immediately' => 'Aktywuj od razu',
    
    // Confirmations
    'confirm_duplicate' => 'Czy chcesz zduplikować tę automatyzację?',
    'confirm_delete' => 'Czy na pewno chcesz usunąć tę automatyzację? Ta akcja jest nieodwracalna.',
    
    // Trigger events
    'triggers' => [
        'subscriber_signup' => 'Zapis na listę',
        'subscriber_activated' => 'Aktywacja subskrybenta',
        'email_opened' => 'Otwarcie emaila',
        'email_clicked' => 'Kliknięcie w link',
        'subscriber_unsubscribed' => 'Wypisanie z listy',
        'email_bounced' => 'Odbicie emaila',
        'form_submitted' => 'Wypełnienie formularza',
        'tag_added' => 'Dodanie taga',
        'tag_removed' => 'Usunięcie taga',
        'field_updated' => 'Zmiana pola',
    ],
    
    // Action types
    'actions' => [
        'send_email' => 'Wyślij email',
        'add_tag' => 'Dodaj tag',
        'remove_tag' => 'Usuń tag',
        'move_to_list' => 'Przenieś do listy',
        'copy_to_list' => 'Skopiuj do listy',
        'unsubscribe' => 'Wypisz z listy',
        'call_webhook' => 'Wywołaj webhook',
        'start_funnel' => 'Uruchom lejek',
        'update_field' => 'Zaktualizuj pole',
        'notify_admin' => 'Powiadom administratora',
    ],
    
    // Logs
    'log_status' => [
        'success' => 'Sukces',
        'partial' => 'Częściowy sukces',
        'failed' => 'Błąd',
        'skipped' => 'Pominięto',
    ],
];
