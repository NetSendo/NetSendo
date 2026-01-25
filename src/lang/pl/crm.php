<?php

return [
    'reminders' => [
        'email_subject' => 'Przypomnienie: :title',
        'title' => '⏰ Przypomnienie o zadaniu',
        'subtitle' => 'Masz zaplanowane zadanie do wykonania',
        'type' => 'Typ',
        'due_date' => 'Termin',
        'priority' => 'Priorytet',
        'contact' => 'Kontakt',
        'view_in_crm' => 'Zobacz zadania w CRM',
        'footer_auto' => 'Ta wiadomość została wysłana automatycznie przez system :appName.',
        'footer_settings' => 'Możesz zmienić ustawienia powiadomień w swoim profilu.',
        'deal' => 'Deal: :name',
        'types' => [
            'call' => '📞 Telefon',
            'email' => '✉️ Email',
            'meeting' => '📅 Spotkanie',
            'follow_up' => '🔄 Follow-up',
            'other' => '📋 Inne',
        ],
        'priorities' => [
            'high' => 'Wysoki',
            'medium' => 'Średni',
            'low' => 'Niski',
        ]
    ],
    'calendar' => [
        'sync_label' => 'Synchronizuj z Google Calendar',
        'select_calendar' => 'Wybierz kalendarz',
        'google_calendar' => 'Google Calendar',
        'sync_all' => 'Synchronizuj wszystkie zadania',
        'syncing' => 'Synchronizowanie...',
        'bulk_sync_description' => 'Zsynchronizuj wszystkie oczekujące zadania CRM z kalendarzem Google jednym kliknięciem.',
        'sync_success' => 'Zsynchronizowano :count zadań',
        'synced' => 'Zsynchronizowane',
    ],
    'recurrence' => [
        'is_recurring' => 'Zadanie cykliczne',
        'helper_text' => 'Powtarza się automatycznie',
        'frequency' => [
            'label' => 'Powtarzaj co',
            'daily' => '{1} dzień|{n} dni',
            'weekly' => '{1} tydzień|{n} tygodnie',
            'monthly' => '{1} miesiąc|{n} miesiące',
            'yearly' => '{1} rok|{n} lata',
        ],
        'days_of_week' => 'Dni tygodnia',
        'days' => [
            'mon' => 'Pn',
            'tue' => 'Wt',
            'wed' => 'Śr',
            'thu' => 'Cz',
            'fri' => 'Pt',
            'sat' => 'Sb',
            'sun' => 'Nd',
        ],
        'end_condition' => [
            'label' => 'Zakończenie',
            'never' => 'Nigdy',
            'date' => 'Do daty:',
            'count' => 'Po',
            'occurrences' => '{1} wystąpieniu|{n} wystąpieniach',
        ],
    ],
    'conflicts' => [
        'title' => 'Wykryto konflikt synchronizacji',
        'description' => 'Zadanie zostało zmienione zarówno lokalnie, jak i w Google Calendar',
        'detected_at' => 'Konflikt wykryty: :date',
        'local_version' => 'Wersja lokalna (NetSendo)',
        'remote_version' => 'Wersja zdalna (Google Calendar)',
        'use_local' => 'Użyj wersji lokalnej',
        'use_remote' => 'Użyj wersji zdalnej',
        'cancel' => 'Anuluj',
        'no_conflict' => 'Zadanie nie ma konfliktu do rozwiązania.',
        'resolved_local' => 'Konflikt rozwiązany - użyto wersji lokalnej.',
        'resolved_remote' => 'Konflikt rozwiązany - użyto wersji zdalnej.',
    ],
    'sequences' => [
        'title' => 'Sekwencje',
        'banner' => [
            'title' => 'Automatyczne sekwencje kontaktu',
            'description' => 'Twórz sekwencje zadań, które automatycznie przypomną Ci o kolejnych krokach kontaktu z klientem. Ustaw "oddzwoń za 3 dni", "follow-up po mailu" lub całe kampanie nurturingu.',
        ],
        'status' => [
            'active' => 'Aktywna',
            'inactive' => 'Nieaktywna',
        ],
        'triggers' => [
            'manual' => 'Ręczny',
            'on_contact_created' => 'Po utworzeniu kontaktu',
            'on_deal_created' => 'Po utworzeniu dealu',
            'on_task_completed' => 'Po ukończeniu zadania',
            'on_deal_stage_changed' => 'Po zmianie etapu dealu',
        ],
        'actions' => [
            'edit' => 'Edytuj',
            'report' => 'Raport',
            'duplicate' => 'Duplikuj',
            'delete' => 'Usuń',
            'delete_confirm' => 'Czy na pewno chcesz usunąć tę sekwencję? Wszystkie kroki zostaną usunięte.',
        ],
        'empty' => [
            'title' => 'Brak sekwencji',
            'description' => 'Utwórz pierwszą sekwencję follow-up, aby automatyzować kontakt z klientami.',
            'button' => 'Utwórz pierwszą sekwencję',
        ],
        'steps_count' => ':count kroków',
        'active_enrollments' => ':count aktywnych',
    ],
    'task' => [
        'title' => [
            'new' => 'Nowe zadanie',
            'edit' => 'Edytuj zadanie',
        ],
        'fields' => [
            'title' => 'Tytuł zadania *',
            'title_placeholder' => 'np. Zadzwoń do klienta w sprawie oferty',
            'type' => 'Typ zadania',
            'priority' => 'Priorytet',
            'due_date' => 'Termin wykonania',
            'contact' => 'Przypisz do kontaktu',
            'contact_placeholder' => '-- Bez kontaktu --',
            'owner' => 'Przypisz do handlowca',
            'owner_auto' => '-- Automatycznie (ja) --',
            'description' => 'Opis (opcjonalnie)',
            'description_placeholder' => 'Dodatkowe szczegóły dotyczące zadania...',
        ],
        'calendar' => [
            'sync' => 'Synchronizuj z kalendarzem Google',
            'select_calendar' => 'Wybierz kalendarz',
            'default' => 'Domyślny',
            'primary' => 'Główny',
            'synced' => 'Zsynchronizowane z kalendarzem',
        ],
        'actions' => [
            'cancel' => 'Anuluj',
            'save' => 'Zapisz zmiany',
            'create' => 'Utwórz zadanie',
        ],
        'empty' => [
            'title' => 'Brak zadań',
            'description' => 'Nie masz jeszcze żadnych zadań w tej kategorii.',
            'button' => 'Dodaj pierwsze zadanie',
        ],
    ],
    'tasks' => [
        'list_view' => 'Lista',
        'calendar_view' => 'Kalendarz',
        'month_view' => 'Miesiąc',
        'week_view' => 'Tydzień',
        'today' => 'Dziś',
        'prev_month' => 'Poprzedni miesiąc',
        'next_month' => 'Następny miesiąc',
        'prev_week' => 'Poprzedni tydzień',
        'next_week' => 'Następny tydzień',
        'google_event' => 'Zdarzenie Google',
        'untitled_event' => 'Bez tytułu',
        'more_events' => '+:count więcej',
        'filter_overdue' => 'Zaległe',
        'filter_today' => 'Na dziś',
        'filter_upcoming' => 'Nadchodzące',
        'filter_completed' => 'Zakończone',
    ],
    'contacts' => [
        'search_or_email' => 'Wyszukaj lub wpisz email',
        'search_placeholder' => 'Wpisz email lub nazwę...',
        'search_hint' => 'Wpisz min. 2 znaki aby wyszukać istniejącego subskrybenta lub wprowadź nowy email',
        'existing_subscriber' => 'Istniejący subskrybent',
        'found_subscribers' => 'Znalezieni subskrybenci',
    ],
    'defaults' => [
        'badge' => 'Domyślna',
        'badge_modified' => 'Własna',
        'restore_button' => 'Przywróć domyślne',
        'restore_modal' => [
            'title' => 'Przywróć domyślne sekwencje',
            'warning' => 'Ta operacja usunie wszystkie obecne sekwencje i utworzy nowe domyślne sekwencje. Tej operacji nie można cofnąć.',
            'confirm_checkbox' => 'Rozumiem, że wszystkie moje obecne sekwencje zostaną usunięte',
            'cancel' => 'Anuluj',
            'confirm' => 'Przywróć domyślne',
        ],
        'restored_success' => 'Domyślne sekwencje zostały przywrócone.',
        'no_sequences' => 'Nie masz jeszcze żadnych sekwencji.',
    ],
    'default_sequences' => [
        'new_lead_nurture' => [
            'name' => 'Nurturing nowego leada',
            'description' => 'Automatyczna sekwencja powitalna dla nowych kontaktów. Buduje relację i prowadzi do pierwszej sprzedaży.',
            'steps' => [
                0 => [
                    'title' => 'Telefon powitalny',
                    'description' => 'Zadzwoń do nowego kontaktu, przywitaj się i zapytaj o potrzeby. Ustal kolejne kroki współpracy.',
                ],
                1 => [
                    'title' => 'Follow-up email z ofertą',
                    'description' => 'Wyślij email podsumowujący rozmowę z propozycją wartości i linkiem do materiałów.',
                ],
                2 => [
                    'title' => 'Sprawdź zainteresowanie',
                    'description' => 'Zadzwoń i zapytaj o przeczytanie materiałów. Odpowiedz na pytania i ustal termin prezentacji.',
                ],
                3 => [
                    'title' => 'Oferta końcowa',
                    'description' => 'Wyślij finalną ofertę z terminem ważności. To ostatnia szansa na zamknięcie sprzedaży.',
                ],
            ],
        ],
        'contact_recovery' => [
            'name' => 'Odzyskanie kontaktu',
            'description' => 'Reaktywacja nieaktywnych kontaktów. Idealne dla klientów, którzy przestali odpowiadać.',
            'steps' => [
                0 => [
                    'title' => 'Pierwsza próba kontaktu',
                    'description' => 'Zadzwoń i zapytaj czy wszystko w porządku. Przypomnij o ofercie i wartości współpracy.',
                ],
                1 => [
                    'title' => 'Email przypominający',
                    'description' => 'Wyślij email z nową propozycją wartości lub specjalną ofertą reaktywacyjną.',
                ],
                2 => [
                    'title' => 'Ostatnia próba kontaktu',
                    'description' => 'Finalna próba nawiązania kontaktu. Zaproponuj spotkanie lub rozmowę w dogodnym terminie.',
                ],
            ],
        ],
        'after_meeting' => [
            'name' => 'Follow-up po spotkaniu',
            'description' => 'Sekwencja po zakończonym spotkaniu. Utrzymuje momentum i prowadzi do decyzji.',
            'steps' => [
                0 => [
                    'title' => 'Podsumowanie spotkania',
                    'description' => 'Wyślij email z podsumowaniem spotkania, ustalonymi punktami i kolejnymi krokami.',
                ],
                1 => [
                    'title' => 'Telefon kontrolny',
                    'description' => 'Zadzwoń i sprawdź czy materiały dotarły. Odpowiedz na ewentualne pytania.',
                ],
                2 => [
                    'title' => 'Zapytaj o decyzję',
                    'description' => 'Zadzwoń i zapytaj o decyzję. Jeśli potrzebują więcej czasu - ustal konkretny termin.',
                ],
            ],
        ],
        'sales_closing' => [
            'name' => 'Finalizacja sprzedaży',
            'description' => 'Sekwencja zamykania sprzedaży. Dla kontaktów gotowych do podjęcia decyzji.',
            'steps' => [
                0 => [
                    'title' => 'Wyślij ofertę',
                    'description' => 'Przygotuj i wyślij formalną ofertę z terminem ważności i warunkami współpracy.',
                ],
                1 => [
                    'title' => 'Telefon potwierdzający',
                    'description' => 'Zadzwoń i potwierdź otrzymanie oferty. Odpowiedz na pytania dotyczące warunków.',
                ],
                2 => [
                    'title' => 'Follow-up decyzja',
                    'description' => 'Zadzwoń i delikatnie zapytaj o stan decyzji. Zaproponuj pomoc w wyjaśnieniu wątpliwości.',
                ],
                3 => [
                    'title' => 'Ostatnia szansa',
                    'description' => 'Finalne przypomnienie o kończącej się ofercie. Zaproponuj bonus za szybką decyzję.',
                ],
            ],
        ],
    ],
];
