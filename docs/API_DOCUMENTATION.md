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

| Uprawnienie | Opis |
|-------------|------|
| `subscribers:read` | Odczyt subskrybentów |
| `subscribers:write` | Tworzenie/edycja/usuwanie subskrybentów |
| `lists:read` | Odczyt list kontaktów |
| `tags:read` | Odczyt tagów |

> **Uwaga:** Uprawnienie `subscribers:write` automatycznie obejmuje `subscribers:read`.

---

## ⚡ Rate Limiting

- **Limit:** 60 żądań na minutę na klucz API
- **Nagłówki odpowiedzi:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- **Status przy przekroczeniu:** `429 Too Many Requests`

---

## 📋 Kody odpowiedzi

| Kod | Opis |
|-----|------|
| `200` | Sukces |
| `201` | Zasób utworzony |
| `202` | Żądanie przyjęte (async) |
| `400` | Błąd walidacji |
| `401` | Brak autoryzacji |
| `403` | Brak uprawnień |
| `404` | Nie znaleziono |
| `409` | Konflikt (duplikat) |
| `422` | Niewalidowane dane |
| `429` | Przekroczony limit |
| `500` | Błąd serwera |

---

## 📧 Subskrybenci (Subscribers)

### Lista subskrybentów

```http
GET /api/v1/subscribers
```

**Parametry query:**

| Parametr | Typ | Opis |
|----------|-----|------|
| `contact_list_id` | integer | Filtruj po ID listy |
| `status` | string | `active`, `inactive`, `unsubscribed`, `bounced` |
| `email` | string | Szukaj po fragmencie email |
| `tag_id` | integer | Filtruj po tagu |
| `per_page` | integer | Wyników na stronę (max 100) |
| `sort_by` | string | Pole sortowania: `created_at`, `email` |
| `sort_order` | string | `asc` lub `desc` |

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
  }
}
```

**Odpowiedź (201):**

```json
{
  "data": {
    "id": 456,
    "email": "maria@example.com",
    ...
  }
}
```

**Przykład cURL:**

```bash
curl -X POST "https://example.com/api/v1/subscribers" \
  -H "Authorization: Bearer ns_live_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"email":"maria@example.com","contact_list_id":5}'
```

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

| Parametr | Typ | Opis |
|----------|-----|------|
| `type` | string | `email` lub `sms` |
| `group_id` | integer | Filtruj po grupie |
| `search` | string | Szukaj po nazwie |

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

| Parametr | Typ | Opis |
|----------|-----|------|
| `status` | string | Filtruj po statusie |
| `per_page` | integer | Wyników na stronę |

---

## 🏷️ Tagi (Tags)

### Lista tagów

```http
GET /api/v1/tags
```

**Parametry query:**

| Parametr | Typ | Opis |
|----------|-----|------|
| `search` | string | Szukaj po nazwie |
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

## 💻 Przykłady kodu

### JavaScript (fetch)

```javascript
const API_KEY = 'ns_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
const BASE_URL = 'https://example.com/api/v1';

async function getSubscribers() {
  const response = await fetch(`${BASE_URL}/subscribers?status=active`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${API_KEY}`,
      'Content-Type': 'application/json'
    }
  });
  
  return response.json();
}

async function createSubscriber(email, listId) {
  const response = await fetch(`${BASE_URL}/subscribers`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${API_KEY}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      email: email,
      contact_list_id: listId
    })
  });
  
  if (response.status === 409) {
    console.log('Subskrybent już istnieje na tej liście');
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

| Stary endpoint | Nowy endpoint |
|----------------|---------------|
| `POST /api.php?action=subscribe` | `POST /api/v1/subscribers` |
| `POST /api.php?action=lists` | `GET /api/v1/lists` |
| Domain-based auth | Bearer Token auth |

---

## ❓ Wsparcie

W razie pytań lub problemów kontaktuj się z zespołem wsparcia lub otwórz issue w repozytorium projektowym.
