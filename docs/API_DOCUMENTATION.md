# NetSendo API Documentation v1

> **Wersja:** 1.0.0  
> **Base URL:** `https://your-domain.com/api/v1`  
> **Uwierzytelnianie:** Bearer Token (API Key)

---

## 🔐 Uwierzytelnianie

Wszystkie żądania API wymagają klucza API przekazywanego w nagłówku `Authorization`:

```
Authorization: Bearer ns_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Generowanie klucza API

1. Zaloguj się do panelu NetSendo
2. Przejdź do **Ustawienia → Klucze API**
3. Kliknij **Utwórz nowy klucz**
4. Skopiuj klucz (wyświetlany tylko raz!)

### Uprawnienia

| Uprawnienie         | Opis                                    |
| ------------------- | --------------------------------------- |
| `subscribers:read`  | Odczyt subskrybentów                    |
| `subscribers:write` | Tworzenie/edycja/usuwanie subskrybentów |
| `lists:read`        | Odczyt list kontaktów                   |
| `lists:write`       | Tworzenie/edycja/usuwanie list, import, czyszczenie, operacje na członkostwie |
| `tags:read`         | Odczyt tagów                            |
| `notifications:write` | Wysyłanie powiadomień do właściciela konta |
| `webhooks:read`     | Odczyt webhooków                        |
| `webhooks:write`    | Tworzenie/edycja/usuwanie webhooków     |
| `email:read`        | Odczyt statusu email i mailboxów        |
| `email:write`       | Wysyłanie email                         |
| `sms:read`          | Odczyt statusu SMS i providerów         |
| `sms:write`         | Wysyłanie SMS                           |

> **Uwaga:** Uprawnienie `subscribers:write` automatycznie obejmuje `subscribers:read`.

> **Migracja:** `lists:write` i `notifications:write` doszły wraz z API zarządzania
> listami. Klucze utworzone wcześniej dostają je automatycznie, o ile miały już
> `subscribers:write` — klucze celowo wąskie zachowują swój zakres i trzeba je
> rozszerzyć ręcznie w panelu (**Klucze API**).

---

## ⚡ Rate Limiting

- **Limit:** 3000 żądań na minutę na klucz API
- **Nagłówki odpowiedzi:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- **Status przy przekroczeniu:** `429 Too Many Requests`

---

## 📋 Kody odpowiedzi

| Kod   | Opis                     |
| ----- | ------------------------ |
| `200` | Sukces                   |
| `201` | Zasób utworzony          |
| `202` | Żądanie przyjęte (async) |
| `400` | Błąd walidacji           |
| `401` | Brak autoryzacji         |
| `403` | Brak uprawnień           |
| `404` | Nie znaleziono           |
| `409` | Konflikt (duplikat)      |
| `422` | Niewalidowane dane       |
| `429` | Przekroczony limit       |
| `500` | Błąd serwera             |

---

## 🏷️ Wstawki (Placeholders)

Wstawki (placeholders) pozwalają na personalizację treści wiadomości Email i SMS. Używaj składni `[[nazwa_pola]]`.

### Dostępne zmienne

#### Dane subskrybenta

| Placeholder  | Opis                | Przykład          |
| ------------ | ------------------- | ----------------- |
| `[[email]]`  | Adres email         | `jan@example.com` |
| `[[fname]]`  | Imię                | `Jan`             |
| `[[!fname]]` | Imię w wołaczu (PL) | `Janie`           |
| `[[lname]]`  | Nazwisko            | `Kowalski`        |
| `[[phone]]`  | Numer telefonu      | `+48123456789`    |
| `[[sex]]`    | Płeć                | `male` / `female` |

#### Linki systemowe

| Placeholder       | Opis                           |
| ----------------- | ------------------------------ |
| `[[unsubscribe]]` | Link wypisania z listy         |
| `[[manage]]`      | Link zarządzania preferencjami |

#### Pola niestandardowe

Każde zdefiniowane pole niestandardowe jest dostępne jako `[[nazwa_pola]]`:

```
[[city]]        → Warszawa
[[company]]     → Firma Sp. z o.o.
```

### Przykład użycia w Email

```json
{
  "email": "jan@example.com",
  "subject": "Witaj [[fname]]!",
  "content": "<h1>Cześć [[fname]] [[lname]]!</h1><p>Twoja firma: [[company]]</p>",
  "subscriber_id": 123
}
```

### Przykład użycia w SMS

```json
{
  "phone": "+48123456789",
  "message": "Cześć [[fname]]! Mamy dla Ciebie ofertę. Szczegóły: example.com",
  "subscriber_id": 123
}
```

> **Uwaga:** Przy batch wysyłce (do listy lub tagów) personalizacja następuje automatycznie dla każdego odbiorcy.

---

## 📧 Subskrybenci (Subscribers)

### Lista subskrybentów

```http
GET /api/v1/subscribers
```

**Parametry query:**

| Parametr          | Typ     | Opis                                            |
| ----------------- | ------- | ----------------------------------------------- |
| `contact_list_id` | integer | Filtruj po ID listy                             |
| `status`          | string  | `active`, `inactive`, `unsubscribed`, `bounced` |
| `email`           | string  | Szukaj po fragmencie email                      |
| `tag_id`          | integer | Filtruj po tagu                                 |
| `per_page`        | integer | Wyników na stronę (max 100)                     |
| `sort_by`         | string  | Pole sortowania: `created_at`, `email`          |
| `sort_order`      | string  | `asc` lub `desc`                                |

**Przykład cURL:**

```bash
curl -X GET "https://example.com/api/v1/subscribers?status=active&per_page=25" \
  -H "Authorization: Bearer ns_live_xxxxxxxx"
```

**Odpowiedź:**

```json
{
  "data": [
    {
      "id": 1,
      "email": "jan@example.com",
      "first_name": "Jan",
      "last_name": "Kowalski",
      "phone": "+48123456789",
      "status": "active",
      "contact_list_id": 5,
      "source": "api",
      "tags": [
        {"id": 1, "name": "VIP"}
      ],
      "custom_fields": {
        "city": "Warszawa"
      },
      "subscribed_at": "2025-01-15T10:30:00.000000Z",
      "created_at": "2025-01-15T10:30:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150
  }
}
```

---

### Pobierz subskrybenta

```http
GET /api/v1/subscribers/{id}
```

**Przykład:**

```bash
curl -X GET "https://example.com/api/v1/subscribers/123" \
  -H "Authorization: Bearer ns_live_xxxxxxxx"
```

---

### Znajdź po email

```http
GET /api/v1/subscribers/by-email/{email}
```

**Przykład:**

```bash
curl -X GET "https://example.com/api/v1/subscribers/by-email/jan@example.com" \
  -H "Authorization: Bearer ns_live_xxxxxxxx"
```

---

### Utwórz subskrybenta

```http
POST /api/v1/subscribers
```

**Wymagane uprawnienie:** `subscribers:write`

**Body (JSON):**

```json
{
  "email": "maria@example.com",
  "contact_list_id": 5,
  "first_name": "Maria",
  "last_name": "Nowak",
  "phone": "+48987654321",
  "status": "active",
  "source": "website",
  "tags": [1, 3],
  "custom_fields": {
    "city": "Kraków",
    "company": "Firma Sp. z o.o."
  },
  "ip_address": "192.168.1.1",
  "user_agent": "Mozilla/5.0",
  "device": "desktop"
}
```

**Parametry:**

| Pole             | Typ     | Wymagane | Opis                                                                            |
| ---------------- | ------- | -------- | ------------------------------------------------------------------------------- |
| `email`          | string  | ✅*      | Adres email subskrybenta (wymagane dla list typu `email`)                       |
| `contact_list_id`| integer | ✅       | ID listy kontaktów, do której dodać subskrybenta                                |
| `first_name`     | string  | ❌       | Imię subskrybenta (max 255 znaków)                                              |
| `last_name`      | string  | ❌       | Nazwisko subskrybenta (max 255 znaków)                                          |
| `phone`          | string  | ✅*      | Numer telefonu z kodem kraju (wymagane dla list typu `sms`, max 50 znaków)      |
| `status`         | string  | ❌       | Status: `active`, `inactive`, `unsubscribed`, `bounced` (domyślnie: `active`)   |
| `source`         | string  | ❌       | Źródło zapisu (domyślnie: `api`, max 255 znaków)                                |
| `tags`           | array   | ❌       | Tablica ID tagów do przypisania, np. `[1, 3]`                                   |
| `custom_fields`  | object  | ❌       | Obiekt z polami niestandardowymi, np. `{"city": "Kraków"}`                      |
| `ip_address`     | string  | ❌       | Adres IP subskrybenta (przydatne przy proxy, np. n8n)                           |
| `user_agent`     | string  | ❌       | User agent przeglądarki (max 500 znaków)                                        |
| `device`         | string  | ❌       | Typ urządzenia, np. `desktop`, `mobile` (max 50 znaków)                         |

\* `email` jest wymagane dla list typu `email`, `phone` jest wymagane dla list typu `sms`.

> **Uwaga:** Jeśli subskrybent o podanym adresie email już istnieje, zostanie dodany do nowej listy (lub reaktywowany) zamiast tworzenia duplikatu. W takim przypadku zwracany jest status `200` zamiast `201`.

**Odpowiedź (201):**

```json
{
  "data": {
    "id": 456,
    "email": "maria@example.com",
    "first_name": "Maria",
    "last_name": "Nowak",
    "phone": "+48987654321",
    "status": "active",
    "contact_list_id": 5,
    "source": "api",
    "tags": [
      {"id": 1, "name": "VIP"},
      {"id": 3, "name": "Newsletter"}
    ],
    "custom_fields": {
      "city": "Kraków",
      "company": "Firma Sp. z o.o."
    },
    "subscribed_at": "2025-01-15T10:30:00.000000Z",
    "created_at": "2025-01-15T10:30:00.000000Z"
  }
}
```

**Przykład cURL:**

```bash
curl -X POST "https://example.com/api/v1/subscribers" \
  -H "Authorization: Bearer ns_live_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "maria@example.com",
    "contact_list_id": 5,
    "first_name": "Maria",
    "last_name": "Nowak",
    "tags": [1, 3],
    "custom_fields": {"city": "Kraków"}
  }'
```

---

### Utwórz subskrybentów (batch)

```http
POST /api/v1/subscribers/batch
```

**Wymagane uprawnienie:** `subscribers:write`

**Body (JSON):**

```json
{
  "subscribers": [
    {
      "email": "jan@example.com",
      "contact_list_id": 5,
      "first_name": "Jan",
      "last_name": "Kowalski",
      "tags": [1, 2],
      "custom_fields": {
        "city": "Warszawa"
      }
    },
    {
      "email": "anna@example.com",
      "contact_list_id": 5,
      "first_name": "Anna"
    }
  ]
}
```

| Pole          | Typ   | Wymagane | Opis                             |
| ------------- | ----- | -------- | -------------------------------- |
| `subscribers` | array | ✅       | Tablica subskrybentów (max 1000) |

Każdy element tablicy przyjmuje te same pola co `POST /api/v1/subscribers`.

**Odpowiedź (200):**

```json
{
  "data": {
    "created": 45,
    "updated": 53,
    "skipped": 0,
    "errors": [
      {
        "index": 2,
        "email": "invalid@",
        "error": "The email must be a valid email address."
      }
    ]
  },
  "message": "Batch completed: 45 created, 53 updated, 0 skipped, 2 errors"
}
```

**Przykład cURL:**

```bash
curl -X POST "https://example.com/api/v1/subscribers/batch" \
  -H "Authorization: Bearer ns_live_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"subscribers":[{"email":"jan@example.com","contact_list_id":5},{"email":"anna@example.com","contact_list_id":5}]}'
```

> **Uwaga:** Webhooks (`subscriber.created`, `subscriber.subscribed`) są wysyłane asynchronicznie dla każdego subskrybenta, co nie blokuje requestu.

---

### Aktualizuj subskrybenta

```http
PUT /api/v1/subscribers/{id}
```

**Wymagane uprawnienie:** `subscribers:write`

**Body (JSON):**

```json
{
  "first_name": "Maria Anna",
  "status": "inactive",
  "custom_fields": {
    "city": "Gdańsk"
  }
}
```

---

### Usuń subskrybenta

```http
DELETE /api/v1/subscribers/{id}
```

**Wymagane uprawnienie:** `subscribers:write`

> **Uwaga:** Wykonuje soft delete (dane zachowane, ale oznaczone jako usunięte).

---

### Synchronizuj tagi

```http
POST /api/v1/subscribers/{id}/tags
```

**Wymagane uprawnienie:** `subscribers:write`

**Body:**

```json
{
  "tags": [1, 4, 7]
}
```

> Zastępuje wszystkie tagi subskrybenta podanymi wartościami.

---

## 📃 Listy kontaktów (Lists)

### Lista wszystkich list

```http
GET /api/v1/lists
```

**Parametry query:**

| Parametr   | Typ     | Opis              |
| ---------- | ------- | ----------------- |
| `type`     | string  | `email` lub `sms` |
| `group_id` | integer | Filtruj po grupie |
| `search`   | string  | Szukaj po nazwie  |

---

### Pobierz szczegóły listy

```http
GET /api/v1/lists/{id}
```

---

### Subskrybenci listy

```http
GET /api/v1/lists/{id}/subscribers
```

**Parametry query:**

| Parametr   | Typ     | Opis                |
| ---------- | ------- | ------------------- |
| `status`   | string  | Filtruj po statusie |
| `per_page` | integer | Wyników na stronę   |

---

### Statystyki listy

```http
GET /api/v1/lists/{id}/stats
```

Zwraca podział członków po statusach, udział zaangażowanych (kto kiedykolwiek otworzył
lub kliknął), przyrost z ostatnich 30 dni oraz konfigurację wpływającą na wysyłkę
(double opt-in, zachowanie przy ponownym zapisie, limity, domyślna skrzynka, webhook).

---

### Utwórz / zaktualizuj / usuń listę

```http
POST   /api/v1/lists
PUT    /api/v1/lists/{id}
DELETE /api/v1/lists/{id}?confirm=1
```

**Wymaga `lists:write`.**

| Pole                        | Typ     | Opis                                                                |
| --------------------------- | ------- | ------------------------------------------------------------------- |
| `name`                      | string  | Nazwa listy (wymagane przy POST)                                    |
| `type`                      | string  | `email` (domyślnie) lub `sms` — kanał listy                         |
| `description`               | string  | Opis                                                                |
| `contact_list_group_id`     | integer | Grupa                                                               |
| `default_mailbox_id`        | integer | Domyślna skrzynka nadawcza                                          |
| `default_sms_provider_id`   | integer | Domyślny provider SMS                                               |
| `double_opt_in`             | bool    | Potwierdzanie zapisu                                                |
| `resubscription_behavior`   | string  | `reset_date` lub `keep_original_date`                               |
| `max_subscribers`           | integer | Limit członków (0 = bez limitu)                                     |
| `signups_blocked`           | bool    | Wstrzymaj przyjmowanie zapisów (tylko PUT)                          |
| `webhook_url`               | string  | URL powiadamiany o zdarzeniach subskrybentów (tylko PUT)            |
| `webhook_events`            | array   | Zdarzenia do wysyłki, np. `["subscriber.subscribed"]` (tylko PUT)   |

`PUT` scala ustawienia — pola, których nie przesłano, zostają bez zmian.
`DELETE` na liście z członkami zwraca **409** dopóki nie podasz `confirm=1`.
Subskrybenci nie są usuwani — znika sama lista.

---

### Import subskrybentów

```http
POST /api/v1/lists/{id}/import/preview
POST /api/v1/lists/{id}/import
```

**Wymaga `lists:read` (podgląd) / `lists:write` (import).**

| Pole             | Typ    | Opis                                                                 |
| ---------------- | ------ | -------------------------------------------------------------------- |
| `format`         | string | `csv` (domyślnie), `tsv`, `json`, `emails`                           |
| `data`           | string | Surowy tekst dla `csv`/`tsv`/`emails`                                |
| `records`        | array  | Tablica obiektów dla `format=json`                                   |
| `delimiter`      | string | Wymuś separator CSV; domyślnie wykrywany (`,` `;` tab `\|`)          |
| `has_header`     | bool   | Wymuś obsługę wiersza nagłówka; domyślnie wykrywana                  |
| `column_mapping` | object | `{"0":"email","1":"first_name","3":"custom:miasto","2":"ignore"}`    |
| `dry_run`        | bool   | Zwróć podgląd zamiast zapisywać                                      |

**Opcje bezpieczeństwa importu:**

| Pole                  | Domyślnie | Działanie                                                |
| --------------------- | --------- | -------------------------------------------------------- |
| `skip_invalid`        | `true`    | Pomiń adresy z błędną składnią                           |
| `skip_disposable`     | `true`    | Pomiń skrzynki jednorazowe (mailinator.com itp.)         |
| `skip_suppressed`     | `true`    | Pomiń adresy z listy wykluczeń                           |
| `skip_role`           | `false`   | Pomiń adresy funkcyjne (`biuro@`, `info@`)               |
| `fix_typos`           | `false`   | Popraw literówki w domenach (`gmial.com` → `gmail.com`)  |
| `update_existing`     | `true`    | Uzupełnij braki na istniejących kontaktach               |
| `trigger_automations` | `true`    | Uruchom sekwencje powitalne i webhooki                   |
| `detect_gender`       | `true`    | Wykryj płeć z imienia                                    |
| `status`              | `active`  | Status członkostwa                                       |
| `source`              | —         | Etykieta źródła zapisana na członkostwie                 |
| `tags`                | —         | Nazwy tagów dla wszystkich zaimportowanych               |

Limit 5000 wierszy na żądanie. Duplikaty są składane po realnej skrzynce, więc
`JAN@x.pl`, `jan@x.pl` oraz `j.an+news@gmail.com` trafiają na jeden kontakt.

---

### Eksport inline

```http
GET /api/v1/lists/{id}/export?format=json&status=active&limit=500
GET /api/v1/lists/{id}/export/fields
```

**Wymaga `subscribers:read`.**

| Parametr                                  | Opis                                                       |
| ----------------------------------------- | ---------------------------------------------------------- |
| `format`                                  | `json` (domyślnie), `csv`, `ndjson`                        |
| `fields[]`                                | Kolumny, np. `fields[]=email&fields[]=tags`                |
| `status`                                  | `active` (domyślnie), `unsubscribed`, `bounced`, `all`     |
| `tag_ids[]`                               | Tylko kontakty z którymś z tagów                           |
| `subscribed_after` / `subscribed_before`  | Zakres dat zapisu                                          |
| `engaged`                                 | `false` = tylko kontakty bez otwarć i kliknięć             |
| `limit` / `cursor`                        | Max 5000 na wywołanie; przy `has_more` podaj `next_cursor` |

`POST /api/v1/lists/{id}/export` (bez zmian) kolejkuje pełny eksport CSV i wysyła
właścicielowi konta link do pobrania.

---

### Higiena listy

```http
GET  /api/v1/lists/hygiene-options
GET  /api/v1/lists/{id}/hygiene
POST /api/v1/lists/{id}/hygiene/clean
POST /api/v1/lists/{id}/hygiene/dedupe
POST /api/v1/lists/{id}/hygiene/verify
```

`GET .../hygiene` (wymaga `lists:read`) zwraca ocenę zdrowia 0–100, liczności w
kategoriach wraz z próbkami, podział zaangażowania i uszeregowane rekomendacje.

**Kategorie:** `invalid_syntax`, `typo_domain`, `disposable_domain`, `role_address`,
`missing_contact`, `duplicate`, `hard_bounced`, `soft_bounce_risk`, `suppressed`,
`unsubscribed`, `unconfirmed`, `globally_inactive`, `never_engaged`, `dormant`.

**Akcje `clean`:** `unsubscribe`, `remove`, `tag` (wymaga pola `tag`) oraz
nieodwracalne `delete` i `suppress`.

| Pole         | Domyślnie | Opis                                                        |
| ------------ | --------- | ----------------------------------------------------------- |
| `categories` | —         | Wymagane; kategorie do przetworzenia                        |
| `action`     | —         | Wymagane                                                    |
| `dry_run`    | `true`    | Bez tego flagi ustawionej na `false` nic nie jest zapisywane |
| `confirm`    | `false`   | Wymagane dla `delete` i `suppress` przy `dry_run=false`      |
| `limit`      | `1000`    | Maksimum przetworzonych członków                            |

Progi (`unconfirmed_after_days` 14, `never_engaged_after_days` 90,
`dormant_after_days` 180, `soft_bounce_threshold` z ustawień listy lub 3) można
nadpisać w każdym wywołaniu.

`dedupe` scala kontakty wskazujące na tę samą skrzynkę — rekord, który zostaje,
przejmuje członkostwa, tagi, pola własne i wyższe liczniki zaangażowania; również
domyślnie `dry_run=true` i wymaga `confirm=true` do zapisu.

`verify` sprawdza składnię oraz rekordy MX (zapytania DNS per domena, cache 24 h)
i zwraca podział na `deliverable` / `risky` / `undeliverable`.

---

### Operacje na członkostwie

```http
POST /api/v1/lists/{id}/members          # dodaj istniejące kontakty
POST /api/v1/lists/{id}/members/remove   # odłącz od listy
POST /api/v1/lists/{id}/members/status   # masowa zmiana statusu
POST /api/v1/lists/{id}/members/move     # przenieś na inną listę
POST /api/v1/lists/{id}/members/copy     # skopiuj na inną listę
POST /api/v1/lists/{id}/members/tags     # dodaj/usuń tagi na segmencie
```

**Wymaga `lists:write`.** Wszystkie przyjmują ten sam blok wyboru (max 5000 kontaktów):

```json
{
  "subscriber_ids": [12, 44],
  "emails": ["anna@example.com"],
  "filter": {
    "status": "active",
    "tag_ids": [3],
    "engaged": false,
    "never_opened": true,
    "subscribed_before": "2025-01-01",
    "limit": 500
  }
}
```

`trigger_automations: false` przenosi audytorium bez ponownego uruchamiania sekwencji
powitalnej. `move`/`copy` wymagają list tego samego kanału. Usuwanie wybrane przez
`filter` wymaga `confirm=true`.

---

### Aktywność i zaangażowanie

```http
GET /api/v1/lists/{id}/activity?days=30&limit=50&types[]=unsubscribed&types[]=bounced
GET /api/v1/lists/{id}/engagement?days=30
GET /api/v1/subscribers/{id}/activity
```

`activity` scala zdarzenia z członkostw (zapisy, potwierdzenia, wypisy), z trackingu
(otwarcia, kliknięcia) i z kolejki wysyłki (`sent`, `failed`) w jeden strumień.

`engagement` zwraca skład audytorium, przyrost i churn z serią dzienną, metryki
dostarczalności (open rate, click rate, CTOR), najlepsze wiadomości i linki, najbardziej
zaangażowane kontakty oraz rozmiar nieaktywnej części listy.

`subscribers/{id}/activity` daje pełną historię jednego kontaktu wraz z listami,
tagami i liczbą wiadomości wciąż zakolejkowanych.

---

## 🚫 Lista wykluczeń (Suppressions)

```http
GET    /api/v1/suppressions
POST   /api/v1/suppressions
DELETE /api/v1/suppressions
```

`POST` (`subscribers:write`) przyjmuje `emails[]`, opcjonalnie `reason` oraz
`unsubscribe_existing` (domyślnie `true` — wypisuje adres ze wszystkich list).
Wykluczenie działa na całe konto i jest respektowane przy każdym imporcie.
`DELETE` zdejmuje blokadę, ale **nikogo nie zapisuje ponownie** — zgodę trzeba zebrać
normalną ścieżką.

---

## 🔔 Powiadomienia (Notifications)

```http
GET  /api/v1/notifications
POST /api/v1/notifications
```

`POST` (`notifications:write`) tworzy powiadomienie w centrum powiadomień właściciela
konta: `title` (wymagane), `message`, `type` (`info`/`success`/`warning`/`error`),
`list_id` (podlinkowuje listę) lub `action_url`, oraz `data` z dowolnym ładunkiem.
Endpoint nie wysyła niczego do subskrybentów ani na zewnątrz konta.

---

## 🏷️ Tagi (Tags)

### Lista tagów

```http
GET /api/v1/tags
```

**Parametry query:**

| Parametr   | Typ     | Opis              |
| ---------- | ------- | ----------------- |
| `search`   | string  | Szukaj po nazwie  |
| `per_page` | integer | Wyników na stronę |

---

### Pobierz tag

```http
GET /api/v1/tags/{id}
```

---

## 📤 Eksport (Export)

### Rozpocznij eksport listy

```http
POST /api/v1/lists/{id}/export
```

**Odpowiedź (202):**

```json
{
  "message": "Export started. You will receive a notification when it is ready.",
  "list_id": 5
}
```

> Eksport wykonywany jest asynchronicznie. Powiadomienie zostanie wysłane po zakończeniu.

---

## 📝 Pola niestandardowe (Custom Fields)

### Lista pól niestandardowych

```http
GET /api/v1/custom-fields
```

**Parametry query:**

| Parametr      | Typ     | Opis                                      |
| ------------- | ------- | ----------------------------------------- |
| `scope`       | string  | `global` lub `list`                       |
| `list_id`     | integer | Pobierz pola globalne + dla tej listy     |
| `public_only` | boolean | Tylko pola publiczne (widoczne w formach) |
| `search`      | string  | Szukaj po nazwie lub etykiecie            |

**Odpowiedź:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "city",
      "label": "Miasto",
      "description": "Miasto zamieszkania",
      "type": "text",
      "placeholder": "[[city]]",
      "options": null,
      "default_value": null,
      "is_public": true,
      "is_required": false,
      "scope": "global",
      "contact_list_id": null
    }
  ]
}
```

---

### Pobierz szczegóły pola

```http
GET /api/v1/custom-fields/{id}
```

---

### Pobierz wszystkie dostępne placeholdery

```http
GET /api/v1/custom-fields/placeholders
```

Zwraca wszystkie dostępne placeholdery systemowe i niestandardowe.

**Odpowiedź:**

```json
{
  "data": {
    "system": [
      {
        "name": "email",
        "placeholder": "[[email]]",
        "label": "Email",
        "type": "system"
      },
      {
        "name": "fname",
        "placeholder": "[[fname]]",
        "label": "First Name",
        "type": "system"
      },
      {
        "name": "!fname",
        "placeholder": "[[!fname]]",
        "label": "First Name (Vocative)",
        "type": "system"
      },
      {
        "name": "lname",
        "placeholder": "[[lname]]",
        "label": "Last Name",
        "type": "system"
      },
      {
        "name": "phone",
        "placeholder": "[[phone]]",
        "label": "Phone",
        "type": "system"
      },
      {
        "name": "sex",
        "placeholder": "[[sex]]",
        "label": "Gender",
        "type": "system"
      },
      {
        "name": "unsubscribe",
        "placeholder": "[[unsubscribe]]",
        "label": "Unsubscribe Link",
        "type": "link"
      },
      {
        "name": "manage",
        "placeholder": "[[manage]]",
        "label": "Manage Preferences Link",
        "type": "link"
      }
    ],
    "custom": [
      {
        "name": "city",
        "placeholder": "[[city]]",
        "label": "Miasto",
        "type": "custom",
        "field_type": "text"
      },
      {
        "name": "company",
        "placeholder": "[[company]]",
        "label": "Firma",
        "type": "custom",
        "field_type": "text"
      }
    ]
  }
}
```

---

## 📧 Email (API)

### Wysyłka pojedynczego Email

```http
POST /api/v1/email/send
```

**Wymagane uprawnienie:** `email:write`

**Body (JSON):**

```json
{
  "email": "user@example.com",
  "subject": "Witaj!",
  "content": "<h1>Hello</h1><p>Treść wiadomości...</p>",
  "preheader": "Opcjonalny preheader",
  "mailbox_id": 1,
  "schedule_at": "2025-12-25T10:00:00Z"
}
```

| Pole            | Typ      | Wymagane | Opis                       |
| --------------- | -------- | -------- | -------------------------- |
| `email`         | string   | ✅       | Adres email odbiorcy       |
| `subject`       | string   | ✅       | Temat wiadomości           |
| `content`       | string   | ✅       | Treść HTML wiadomości      |
| `preheader`     | string   | ❌       | Preheader (max 500 znaków) |
| `mailbox_id`    | integer  | ❌       | ID skrzynki nadawczej      |
| `schedule_at`   | datetime | ❌       | Zaplanuj wysyłkę           |
| `subscriber_id` | integer  | ❌       | Powiąż z subskrybentem     |

**Odpowiedź (202):**

```json
{
  "data": {
    "id": 123,
    "email": "user@example.com",
    "subject": "Witaj!",
    "status": "queued",
    "mailbox": "Główna skrzynka",
    "scheduled_at": "2025-12-25T10:00:00Z"
  },
  "message": "Email queued successfully"
}
```

---

### Wysyłka batch Email

```http
POST /api/v1/email/batch
```

**Wymagane uprawnienie:** `email:write`

**Body (JSON):**

```json
{
  "subject": "Newsletter grudzień",
  "content": "<h1>Nasz newsletter</h1>...",
  "list_id": 5,
  "schedule_at": "2025-12-25T10:00:00Z",
  "excluded_list_ids": [7, 8]
}
```

| Pole                | Typ      | Wymagane | Opis                     |
| ------------------- | -------- | -------- | ------------------------ |
| `subject`           | string   | ✅       | Temat wiadomości         |
| `content`           | string   | ✅       | Treść HTML               |
| `list_id`           | integer  | ❌\*     | ID listy email           |
| `tag_ids`           | array    | ❌\*     | Tablica ID tagów         |
| `subscriber_ids`    | array    | ❌\*     | Tablica ID subskrybentów |
| `mailbox_id`        | integer  | ❌       | ID skrzynki nadawczej    |
| `schedule_at`       | datetime | ❌       | Zaplanuj wysyłkę         |
| `excluded_list_ids` | array    | ❌       | Listy do wykluczenia     |

\* Wymagane jest jedno z: `list_id`, `tag_ids` lub `subscriber_ids`

**Odpowiedź (202):**

```json
{
  "data": {
    "id": 124,
    "queued_count": 150,
    "subject": "Newsletter grudzień",
    "status": "queued",
    "mailbox": "Główna skrzynka",
    "scheduled_at": "2025-12-25T10:00:00Z"
  },
  "message": "Batch email queued for 150 recipients"
}
```

---

### Status Email

```http
GET /api/v1/email/status/{id}
```

**Wymagane uprawnienie:** `email:read`

**Odpowiedź:**

```json
{
  "data": {
    "id": 123,
    "subject": "Witaj!",
    "status": "scheduled",
    "scheduled_at": "2025-12-25T10:00:00.000000Z",
    "created_at": "2025-12-24T21:00:00.000000Z",
    "stats": {
      "planned": 10,
      "queued": 5,
      "sent": 140,
      "failed": 0
    }
  }
}
```

---

### Lista skrzynek nadawczych

```http
GET /api/v1/email/mailboxes
```

**Wymagane uprawnienie:** `email:read`

**Odpowiedź:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Główna skrzynka",
      "provider": "smtp",
      "from_email": "newsletter@example.com",
      "from_name": "NetSendo",
      "is_default": true
    }
  ]
}
```

---

## 📱 SMS

### Wysyłka pojedynczego SMS

```http
POST /api/v1/sms/send
```

**Wymagane uprawnienie:** `sms:write`

**Body (JSON):**

```json
{
  "phone": "+48123456789",
  "message": "Witaj! To jest wiadomość z NetSendo.",
  "provider_id": 1,
  "schedule_at": "2025-12-25T10:00:00Z"
}
```

| Pole            | Typ      | Wymagane | Opis                         |
| --------------- | -------- | -------- | ---------------------------- |
| `phone`         | string   | ✅       | Numer telefonu z kodem kraju |
| `message`       | string   | ✅       | Treść SMS (max 1600 znaków)  |
| `provider_id`   | integer  | ❌       | ID providera SMS             |
| `subscriber_id` | integer  | ❌       | Powiąż z subskrybentem       |
| `schedule_at`   | datetime | ❌       | Zaplanuj wysyłkę             |

**Odpowiedź (202):**

```json
{
  "data": {
    "id": 123,
    "phone": "+48123456789",
    "status": "queued",
    "provider": "SMS API (Polska)",
    "scheduled_at": null
  },
  "message": "SMS queued successfully"
}
```

---

### Wysyłka batch SMS

```http
POST /api/v1/sms/batch
```

**Wymagane uprawnienie:** `sms:write`

**Body (JSON):**

```json
{
  "message": "Hej! Mamy dla Ciebie promocję.",
  "list_id": 5
}
```

lub:

```json
{
  "message": "Twoja oferta wygasa!",
  "tag_ids": [1, 3]
}
```

| Pole             | Typ     | Wymagane | Opis                     |
| ---------------- | ------- | -------- | ------------------------ |
| `message`        | string  | ✅       | Treść SMS                |
| `list_id`        | integer | ❌\*     | ID listy SMS             |
| `tag_ids`        | array   | ❌\*     | Tablica ID tagów         |
| `subscriber_ids` | array   | ❌\*     | Tablica ID subskrybentów |
| `provider_id`    | integer | ❌       | ID providera SMS         |

\* Wymagane jest jedno z: `list_id`, `tag_ids` lub `subscriber_ids`

**Odpowiedź (202):**

```json
{
  "data": {
    "id": 124,
    "queued_count": 150,
    "status": "queued",
    "provider": "Twilio"
  },
  "message": "Batch SMS queued for 150 recipients"
}
```

---

### Status SMS

```http
GET /api/v1/sms/status/{id}
```

**Wymagane uprawnienie:** `sms:read`

**Odpowiedź:**

```json
{
  "data": {
    "id": 123,
    "status": "queued",
    "content": "Witaj! To jest wiadomość...",
    "created_at": "2025-12-24T21:00:00.000000Z",
    "stats": {
      "pending": 10,
      "sent": 140,
      "failed": 0
    }
  }
}
```

---

### Lista providerów SMS

```http
GET /api/v1/sms/providers
```

**Wymagane uprawnienie:** `sms:read`

**Odpowiedź:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Główny SMS",
      "provider": "smsapi",
      "is_default": true,
      "from_name": "NetSendo",
      "daily_limit": 1000,
      "sent_today": 150
    }
  ]
}
```

---

## 💻 Przykłady kodu

### JavaScript (fetch)

```javascript
const API_KEY = "ns_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
const BASE_URL = "https://example.com/api/v1";

async function getSubscribers() {
  const response = await fetch(`${BASE_URL}/subscribers?status=active`, {
    method: "GET",
    headers: {
      Authorization: `Bearer ${API_KEY}`,
      "Content-Type": "application/json",
    },
  });

  return response.json();
}

async function createSubscriber(email, listId) {
  const response = await fetch(`${BASE_URL}/subscribers`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${API_KEY}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      email: email,
      contact_list_id: listId,
    }),
  });

  if (response.status === 409) {
    console.log("Subskrybent już istnieje na tej liście");
  }

  return response.json();
}
```

### PHP

```php
<?php

$apiKey = 'ns_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
$baseUrl = 'https://example.com/api/v1';

function callApi($method, $endpoint, $data = null) {
    global $apiKey, $baseUrl;

    $ch = curl_init($baseUrl . $endpoint);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Pobierz subskrybentów
$subscribers = callApi('GET', '/subscribers?status=active');

// Utwórz subskrybenta
$newSub = callApi('POST', '/subscribers', [
    'email' => 'test@example.com',
    'contact_list_id' => 5
]);
```

---

## 📚 Dokumentacja interaktywna (Swagger)

Po uruchomieniu aplikacji, dokumentacja OpenAPI jest dostępna pod adresem:

```
https://your-domain.com/docs/api
```

Zawiera pełną specyfikację OpenAPI z możliwością testowania endpointów bezpośrednio w przeglądarce.

---

## 🔄 Migracja ze starego API

Jeśli korzystałeś ze starego API NetSendo, oto mapa migracji:

| Stary endpoint                   | Nowy endpoint              |
| -------------------------------- | -------------------------- |
| `POST /api.php?action=subscribe` | `POST /api/v1/subscribers` |
| `POST /api.php?action=lists`     | `GET /api/v1/lists`        |
| Domain-based auth                | Bearer Token auth          |

---

## ❓ Wsparcie

W razie pytań lub problemów kontaktuj się z zespołem wsparcia lub otwórz issue w repozytorium projektowym.
