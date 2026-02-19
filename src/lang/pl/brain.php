<?php

return [
    // === AgentOrchestrator ===
    'token_limit_reached' => '⚠️ Osiągnięto dzienny limit tokenów AI. Spróbuj ponownie jutro lub zwiększ limit w ustawieniach.',
    'processing_error' => '❌ Przepraszam, wystąpił błąd podczas przetwarzania Twojej wiadomości. Spróbuj ponownie.',
    'plan_failed' => '🤔 Nie udało mi się stworzyć planu dla tej akcji. Możesz sprecyzować, co dokładnie chcesz zrobić?',
    'agent_not_found' => '❌ Agent \':agent\' nie jest dostępny.',
    'plan_executed' => '✅ Plan wykonany pomyślnie.',
    'plan_execution_error' => '❌ Błąd wykonania planu: :error',
    'no_ai_integration' => '⚠️ Brak skonfigurowanej integracji AI. Przejdź do Ustawienia → AI aby skonfigurować dostawcę AI.',
    'user_wants' => 'Użytkownik chce: :intent',

    // Plan approval
    'plan_header' => '📋 **Plan: :title**',
    'steps_to_execute' => '**Kroki do wykonania:**',
    'mode_label' => '🔄 Tryb: :mode',
    'approve_reject' => '✅ Zaakceptuj | ❌ Odrzuć',

    // === CRM Agent ===
    'crm' => [
        'label' => '👥 CRM Agent',
        'plan_title' => 'Plan CRM',
        'step_error' => '❌ Błąd w kroku :step (:title): :error',
        'plan_completed' => "✅ **Plan CRM wykonany!**\n\nWykonano :completed/:total kroków pomyślnie.",
        'contacts_found' => 'Znaleziono :count kontaktów:',
        'email_missing' => 'Brak adresu email',
        'subscriber_not_found' => 'Subskrybent :email nie istnieje w systemie',
        'contact_exists' => 'Kontakt CRM dla :email już istnieje (ID: :id)',
        'contact_created' => 'Kontakt CRM ":name" utworzony (ID: :id)',
        'missing_contact_status' => 'Brak contact_id lub new_status',
        'status_changed_log' => 'Status zmieniony z :old na :new (przez AI Brain)',
        'status_changed' => 'Status kontaktu :name zmieniony: :old → :new',
        'no_pipeline' => 'Brak pipeline — stwórz pipeline w panelu CRM',
        'no_stages' => 'Pipeline nie ma zdefiniowanych etapów',
        'deal_default_name' => 'Nowy deal',
        'deal_created' => 'Deal ":name" (wartość: :value) utworzony w pipeline ":pipeline", etap: ":stage"',
        'missing_deal_stage' => 'Brak deal_id lub stage_name',
        'stage_not_found' => 'Nie znaleziono etapu ":stage" w pipeline',
        'deal_moved' => 'Deal ":name" przeniesiony na etap ":stage"',
        'task_default_title' => 'Nowe zadanie',
        'task_created' => 'Zadanie ":title" (priorytet: :priority) utworzone, termin: :due_date',
        'score_header' => '📊 **Analiza Scoring CRM**',
        'score_total' => 'Total kontaktów: :count',
        'score_avg' => 'Średni score: :avg',
        'score_hot' => 'Hot leads (50+): :count',
        'score_top5' => '🏆 **Top 5 kontaktów:**',
        'pipeline_header' => '📋 **Pipeline: :name**',
        'no_pipeline_display' => 'Brak pipeline do wyświetlenia',
        'pipeline_total_open' => '💰 **Razem otwarte**: :count deals, wartość: :value PLN',
        'company_default_name' => 'Nowa firma',
        'company_created' => 'Firma ":name" utworzona (ID: :id)',
    ],

    // === Analytics Agent ===
    'analytics' => [
        'label' => '📊 Analytics Agent',
        'plan_title' => 'Raport',
        'analysis_done' => '✅ Analiza zakończona.',
        'campaign_header' => '📧 **Kampanie** (:days d)',
        'campaign_sent' => '✉️ Wysłane: :count',
        'campaign_opens' => '👁️ Otwarcia: :count',
        'campaign_clicks' => '🖱️ Kliknięcia: :count',
        'campaign_rates' => '📈 OR: :open_rate% | CTOR: :click_rate%',
        'subscriber_header' => '👥 **Subskrybenci** (:days d)',
        'subscriber_total' => '📊 Łącznie: :total | ✅ Aktywni: :active',
        'subscriber_new' => '🆕 Nowi: :new | 🚪 Wypisani: :unsubs',
        'subscriber_bounced' => '⛔ Bounced: :bounced | 📈 Growth: :growth%',
        'no_campaigns' => '📭 Brak kampanii do porównania.',
        'compare_header' => '📊 **Porównanie kampanii**',
        'trends_header' => '📊 **Trendy** (:days d)',
        'trends_opens' => 'Otwarcia: :recent vs :previous (:pct%)',
        'trends_subs' => 'Nowi sub: :recent vs :previous (:pct%)',
        'ai_usage_header' => '🧠 **AI Brain** (:days d)',
        'ai_usage_exec' => '🔄 Exec: :total | ✅:success ❌:errors',
        'ai_usage_tokens' => '🎯 Tokeny: :tokens | ⏱️ Avg: :avg_ms ms',
        'quick_stats' => 'Subskrybenci: :subs (aktywni: :active), Listy: :lists, Wysłane: :sent',
    ],

    // === Segmentation Agent ===
    'segmentation' => [
        'label' => '🎯 Segmentation Agent',
        'plan_title' => 'Plan segmentacji',
        'done' => '✅ Segmentacja zakończona.',
        'no_tags' => '🏷️ Brak tagów w systemie.',
        'tag_distribution' => '🏷️ **Rozkład tagów** (top :limit)',
        'score_segments' => '📊 **Segmenty scoring** (:total kontaktów)',
        'cold' => '🥶 Zimny',
        'warm' => '🌡️ Ciepły',
        'hot' => '🔥 Gorący',
        'super_hot' => '🚀 Super Hot',
        'tag_name_missing' => 'Brak nazwy taga',
        'tag_exists' => 'Tag ":name" już istnieje (ID: :id)',
        'tag_created' => '🏷️ Tag ":name" utworzony (ID: :id)',
        'tag_applied' => '🏷️ Tag ":name" przypisany do :count subskrybentów',
        'automation_header' => '⚙️ **Automatyzacje** (:days d)',
        'automation_rules' => '📋 Reguły: :active aktywnych / :total łącznie',
        'automation_execs' => '🔄 Wykonania: :count',
        'automation_success' => '✅ Success rate: :rate%',
    ],

    // === Campaign Agent ===
    'campaign' => [
        'label' => '📧 Campaign Agent',
        'plan_title' => 'Plan kampanii',
        'step_error' => '❌ Błąd w kroku :step (:title): :error',
        'plan_completed' => "✅ **Kampania przygotowana!**\n\nWykonano :completed/:total kroków pomyślnie.",
        'audience_selected' => 'Wybrano :count list z :subscribers subskrybentami',
        'message_created' => 'Wiadomość ":subject" utworzona jako szkic (ID: :id)',
        'default_message' => 'Nowa wiadomość',
        'schedule_ready' => '📋 Kampania gotowa do wysyłki. Przejdź do panelu aby zaplanować wysyłkę.',
    ],

    // === List Agent ===
    'list' => [
        'label' => '📋 List Agent',
        'plan_title' => 'Zarządzanie listą',
        'management_done' => '📋 **Zarządzanie listami zakończone**',
        'default_name' => 'Nowa lista',
        'list_created' => '📋 Lista ":name" utworzona (ID: :id)',
        'cleaned' => '🧹 Wyczyszczono :count bounced/complained subskrybentów',
        'tagged' => '🏷️ Otagowano :count subskrybentów tagiem ":tag"',
        'stats_list' => '📊 :name: :count subskrybentów',
        'stats_total' => '📊 :lists list, :subscribers subskrybentów łącznie',
        'no_lists' => 'Użytkownik nie ma jeszcze żadnych list kontaktów.',
    ],

    // === Message Agent ===
    'message' => [
        'label' => '✉️ Message Agent',
        'plan_title' => 'Tworzenie treści',
        'content_ready' => '✉️ **Treść przygotowana!**',
        'default_message' => 'Nowa wiadomość',
        'subjects_generated' => '📝 Wygenerowano :count wariantów tematu:',
        'body_generated' => '✍️ Treść :type wygenerowana',
        'message_saved' => '💾 Wiadomość ":subject" zapisana jako szkic (ID: :id)',
        'ab_variants' => '🔬 Warianty A/B:',
        'no_message_id' => '⚠️ Brak ID wiadomości do poprawienia',
        'message_not_found' => '⚠️ Wiadomość ID :id nie znaleziona',
        'message_improved' => '✨ Wiadomość poprawiona: :changes',
    ],

    // === Monitor — Token & Cost ===
    'monitor' => [
        'tokens_input' => 'Input',
        'tokens_output' => 'Output',
        'estimated_cost' => 'Szacunkowy koszt',
        'cost_by_model' => 'Koszty wg modelu',
        'suggested_tasks' => 'Sugerowane zadania',
        'suggested_tasks_desc' => 'Zadania zaproponowane na podstawie analizy Twojego CRM, list kontaktów i historii kampanii',
        'ai_generated' => 'Wygenerowane przez AI',
        'no_suggestions' => 'Brak sugestii — dodaj kontakty i listy aby otrzymać rekomendacje',
        'execute' => 'Wykonaj',
        'executed_plans' => 'Wykonane plany',
        'priority_high' => 'Wysoki',
        'priority_medium' => 'Średni',
        'priority_low' => 'Niski',
    ],

    // === Research Agent ===
    'research' => [
        'agent_label' => '🔍 Research Agent',
        'plan_title' => 'Plan badań',
        'done' => '✅ Badanie zakończone.',
        'query_missing' => '⚠️ Nie podano zapytania wyszukiwania.',
        'company_missing' => '⚠️ Nie podano nazwy firmy.',
        'topic_missing' => '⚠️ Nie podano tematu.',
        'no_results' => '🔍 Brak wyników dla ":query".',
        'no_data' => 'Brak danych z badań.',
        'default_kb_title' => 'Wyniki badań',
        'nothing_to_save' => 'Brak danych do zapisania.',
        'saved_to_kb' => 'Badanie ":title" zapisane w bazie wiedzy (ID: :id)',
        'save_failed' => '❌ Nie udało się zapisać badania: :error',
        'task_enrich_companies' => 'Zbadaj :count firm bez danych o stronie/branży',
        'task_research_leads' => 'Pogłębione badanie :count gorących leadów',
        'task_trends' => 'Analiza aktualnych trendów rynkowych w Twojej branży',
    ],

    // === AI Prompt system instructions (used inside prompts — NOT user-facing) ===
    // These remain in Polish as they instruct the AI model behavior.
    // If multilingual AI prompts are needed later, they can be added here.

    // === Voice Messages ===
    'voice' => [
        'recording' => 'Nagrywanie...',
        'transcribing' => 'Transkrypcja...',
        'mic_permission_denied' => 'Brak dostępu do mikrofonu. Sprawdź ustawienia przeglądarki.',
        'transcription_failed' => 'Nie udało się transkrybować wiadomości głosowej.',
        'record_voice' => 'Nagraj wiadomość głosową',
        'stop_recording' => 'Zatrzymaj nagrywanie',
        'no_openai' => 'Transkrypcja wymaga integracji OpenAI.',
    ],
];
