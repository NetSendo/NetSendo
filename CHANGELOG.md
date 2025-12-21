# Changelog

Wszystkie istotne zmiany w projekcie NetSendo są udokumentowane w tym pliku.

Format bazuje na [Keep a Changelog](https://keepachangelog.com/),
a numery wersji przestrzegają [Semantic Versioning](https://semver.org/).

## [1.0.3] - 2025-12-21

### Ulepszono
- **Dashboard - rzeczywiste dane:**
  - Sekcja "Ostatnie kampanie" pobiera teraz 4 ostatnie wiadomości z bazy danych
  - Wykres aktywności pokazuje rzeczywiste statystyki z ostatnich 7 dni (emaile wysłane, nowi subskrybenci, otwarcia)
  - Usunięto dane demo/sample z komponentów Dashboard

- **Dashboard - UX improvements:**
  - Dodano stany puste (empty states) z CTA gdy brak danych
  - Dodano stany ładowania (skeleton loading) podczas pobierania danych

### Poprawiono
- **Linki na Dashboard:**
  - Zmieniono "Zobacz wszystkie →" z hardcoded `/messages` na `route('messages.index')`
  - Quick Actions: wszystkie hardcoded ścieżki zamienione na dynamiczne `route()`:
    - `/messages/add` → `route('messages.create')`
    - `/subscribers/add` → `route('subscribers.create')`
    - `/subscribers/import` → `route('subscribers.import')`
    - `/forms/add` → `route('forms.create')`

### Backend
- Rozszerzono API `getDashboardStats()` w `GlobalStatsController` o:
  - `recent_campaigns` - 4 ostatnie wiadomości z bazą
  - `activity_chart` - dane aktywności z 7 dni

### Tłumaczenia
- PL/EN: dodano klucze dla empty states:
  - `dashboard.recent_campaigns.empty_title`
  - `dashboard.recent_campaigns.empty_description`
  - `dashboard.recent_campaigns.create_first`
  - `dashboard.activity.empty_title`
  - `dashboard.activity.empty_description`

---

## [1.0.2] - 2025-12-19

### Dodano
- Global Stats - statystyki miesięczne z eksportem CSV
- Activity Logger - dziennik aktywności z automatycznym logowaniem CRUD
- Tracked Links Dashboard - dashboard śledzonych linków z emaili

---

## [1.0.1] - 2025-12-19

### Dodano
- System licencjonowania (SILVER/GOLD)
- Template Inserts (wstawki i podpisy)

---

## [1.0.0] - 2025-12-18

### Wydanie inicjalne
- Pełna migracja NetSendo do Laravel 11 + Vue.js 3 + Inertia.js
- Email Template Builder (MJML, Drag & Drop)
- Integracje AI (6 providerów)
- Multi-provider email (SMTP, Gmail OAuth, SendGrid, Postmark, Mailgun)
- Formularze zapisu, lejki email
- Triggery i automatyzacje
- API publiczne
- Backup & Export
