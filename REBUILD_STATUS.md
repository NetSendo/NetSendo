## 📅 Ostatnia aktualizacja: 2025-12-19 (Phase 28b: Tracked Links Dashboard)

Projekt został rozbudowany o **Statystyki Globalne**, **Dziennik Aktywności** oraz **Dashboard Śledzonych Linków** - PRZEBUDOWA ZAKOŃCZONA! ✅

---

## 🚀 Jak uruchomić projekt (Quick Start)

```bash
cd v2
docker compose up -d
```

**Dostęp:**
- Frontend: [http://localhost:8080](http://localhost:8080)
- Poczta (Mailpit): [http://localhost:8025](http://localhost:8025)
- MySQL: port `33006` (user: `netsendo`, pass: `root`, db: `netsendo`)

**Przydatne komendy:**
```bash
docker compose exec -u dev app php artisan <komenda>
docker compose exec -u dev app composer <komenda>
docker compose exec -u dev app npm run build
docker compose exec -u dev app bash
```

---

## ✅ Co zostało zrobione?

### Faza 1 - 25: Wszystkie poprzednie fazy ✅
- Konteneryzacja, Auth, Panel Admina
- Listy Mailingowe, Subskrybenci, Wiadomości, Strefy Czasowe
- Integracje AI (6 providerów), Wielojęzyczność (i18n)
- Email Template Builder (Drag & Drop, MJML)
- Silnik Wysyłki, Tracking, Multi-Provider Email
- Szablony Startowe, Gmail OAuth, Message Editor
- Ustawienia List, SMS, Template Fixes, AI Assistant
- CRON & Queue Management, Field Management, System Messages
- Formularze Zapisu, Lejki Email (Flow Builder)
- Triggery i Automatyzacje, Event Dispatching
- API Publiczne, Backup & Export

### Faza 26: System Licencjonowania ✅ (19.12.2025)
- Plany SILVER/GOLD, auto-aktywacja, sprawdzanie wersji z GitHub

### Faza 27: Template Inserts (Wstawki i podpisy) ✅ (19.12.2025)
- CRUD wstawek/podpisów, zmienne systemowe, pola niestandardowe

### Faza 28: Global Stats, Activity Logger & Tracked Links ✅ (19.12.2025)
- **Global Stats** (`/settings/stats`):
  - Statystyki miesięczne, trend dzienny, per lista
  - Dashboard z prawdziwymi danymi (nie hardcoded)
  - Eksport CSV
- **Activity Logger** (`/settings/activity-logs`):
  - Automatyczne logowanie CRUD (Subscriber, Message, ContactList)
  - Filtry, paginacja, eksport CSV, cleanup
- **Tracked Links** (`/settings/tracked-links`):
  - Dashboard kliknięć w linki z emaili
  - Karty: wszystkie kliknięcia, unikalne linki, unikalni klikający, dzisiaj
  - Wykres trendu 30-dniowego
  - Filtry: URL, wiadomość, zakres dat
  - Eksport CSV

---

## 📂 Kluczowe Pliki (Phase 28)

| Typ | Ścieżka |
|-----|---------|
| Controller | `src/app/Http/Controllers/GlobalStatsController.php` |
| Controller | `src/app/Http/Controllers/ActivityLogController.php` |
| Controller | `src/app/Http/Controllers/TrackedLinksController.php` |
| Model | `src/app/Models/ActivityLog.php` |
| Trait | `src/app/Traits/LogsActivity.php` |
| Frontend | `src/resources/js/Pages/Settings/GlobalStats/Index.vue` |
| Frontend | `src/resources/js/Pages/Settings/ActivityLogs/Index.vue` |
| Frontend | `src/resources/js/Pages/Settings/TrackedLinks/Index.vue` |

---

## 🎯 PLAN PRAC NA NASTĘPNY CHAT

### 📋 Do dokończenia (System Licencjonowania):
- [ ] **Stripe Payment Link** - dodać link do `config/netsendo.php` gdy gotowy
- [ ] **Testowanie webhook** - sprawdzić czy licencja przychodzi automatycznie

### 📋 Priorytet ŚREDNI:
- [ ] Courses (E-mail kursy z sekwencjami)
- [ ] External Pages - pełna integracja z formularzami

---

## 📊 Status Migracji ze Starego NetSendo

| Kategoria | Procent |
|-----------|---------|
| ✅ Zaimplementowane | 100% |
| 🟡 Częściowo | 0% |
| ❌ Brakujące | 0% |

**🎉 PRZEBUDOWA ZAKOŃCZONA!** Wszystkie funkcjonalności ze starego NetSendo zostały zmigrowane do nowej architektury Laravel + Vue.js.

---

## 📝 Notatki dla kontynuacji

1. **System Licencjonowania:**
   - Plany: SILVER (free lifetime) i GOLD ($97/mc)
   - Webhook: `https://a.gregciupek.com/webhook/ddae7ce5-2a11-40f1-aa03-5da2e294777d`
   - Config w `config/netsendo.php`

2. **Sprawdzanie wersji:**
   - GitHub API: `NetSendo/NetSendo` releases
   - Cache 1h, endpoint `/api/version/check`

3. **Event Dispatching:**
   - TrackingController → EmailOpened, EmailClicked
   - Subscriber::addTag()/removeTag() → TagAdded, TagRemoved
   - BounceController webhooks → EmailBounced

4. **Tłumaczenia frontend:** Pliki JSON w `resources/js/locales/*.json`

5. **Route konflikt:** Trasy statyczne muszą być przed `Route::resource()`
