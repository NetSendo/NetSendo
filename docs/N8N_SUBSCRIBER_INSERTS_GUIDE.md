# NetSendo n8n Node - Subscriber Inserts (Wstawki) Implementation Guide

Instrukcja dla agenta pracującego nad community node **n8n-nodes-netsendo** - obsługa wstawek (placeholders) i dodatkowych pól przy zapisie subskrybentów dla SMS i Email.

---

## 📋 Podsumowanie

NetSendo API v1 obsługuje:

1. **Pola niestandardowe (custom_fields)** przy tworzeniu/aktualizacji subskrybentów
2. **Wstawki (placeholders)** w treści Email i SMS - automatycznie zamieniane na dane subskrybenta
3. **Zmienne systemowe** jak `[[fname]]`, `[[email]]`, `[[phone]]` i inne

---

## 🔧 Dostępne Placeholdery (Wstawki)

### Dane subskrybenta

| Placeholder  | Opis                    | Przykład wartości |
| ------------ | ----------------------- | ----------------- |
| `[[email]]`  | Adres email             | `jan@example.com` |
| `[[fname]]`  | Imię                    | `Jan`             |
| `[[!fname]]` | Imię w wołaczu (polski) | `Janie`           |
| `[[lname]]`  | Nazwisko                | `Kowalski`        |
| `[[phone]]`  | Numer telefonu          | `+48123456789`    |
| `[[sex]]`    | Płeć (male/female)      | `male`            |

### Linki systemowe

| Placeholder           | Opis                                                                                                                         |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `[[unsubscribe]]`     | Link wypisania z listy                                                                                                       |
| `[[unsubscribe_now]]` | Wypis jednym przyciskiem: z listy wiadomości albo ze wszystkich list, bez e-maila z potwierdzeniem i bez strony ustawień |
| `[[manage]]`          | Link zarządzania preferencjami                                                                                               |

### Daty

| Placeholder          | Opis                      |
| -------------------- | ------------------------- |
| `[[system-created]]` | Data utworzenia konta     |
| `[[last-message]]`   | Data ostatniej wiadomości |
| `[[list-created]]`   | Data zapisania na listę   |
| `[[list-activated]]` | Data aktywacji na liście  |

### Pola niestandardowe

Każde zdefiniowane pole niestandardowe jest dostępne jako `[[nazwa_pola]]`:

```
[[city]]        → Warszawa
[[company]]     → Firma Sp. z o.o.
[[birth_date]]  → 1990-05-15
```

### Forma warunkowa (polski)

```
{{męska|żeńska}}  → "męska" dla mężczyzn, "żeńska" dla kobiet
```

---

## 📧 Tworzenie Subskrybenta z Custom Fields

### Endpoint API

```http
POST /api/v1/subscribers
```

### Struktura żądania

```json
{
  "email": "jan@example.com",
  "contact_list_id": 5,
  "first_name": "Jan",
  "last_name": "Kowalski",
  "phone": "+48123456789",
  "status": "active",
  "source": "n8n",
  "tags": [1, 3],
  "custom_fields": {
    "city": "Warszawa",
    "company": "Firma Sp. z o.o.",
    "birth_date": "1990-05-15"
  }
}
```

### Parametry custom_fields

| Pole            | Typ    | Opis                                   |
| --------------- | ------ | -------------------------------------- |
| `custom_fields` | object | Klucz-wartość par pól niestandardowych |

> **Uwaga:** Klucze w `custom_fields` muszą odpowiadać nazwie (`name`) zdefiniowanych pól niestandardowych w NetSendo.

---

## 📨 Wysyłka Email z Placeholderami

### Endpoint

```http
POST /api/v1/email/send
```

### Przykład z personalizacją

```json
{
  "email": "jan@example.com",
  "subject": "Witaj [[fname]]!",
  "content": "<h1>Cześć [[fname]] [[lname]]!</h1><p>Twoja firma: [[company]]</p><p><a href=\"[[unsubscribe]]\">Wypisz się</a></p>",
  "subscriber_id": 123
}
```

**Wynik po personalizacji:**

- Subject: `Witaj Jan!`
- Content: `<h1>Cześć Jan Kowalski!</h1><p>Twoja firma: Firma Sp. z o.o.</p>...`

### Batch Email z personalizacją

```json
{
  "subject": "Newsletter dla [[fname]]",
  "content": "<p>Drogi/a {{Pan|Pani}} [[fname]],</p><p>Mamy ofertę dla [[company]]!</p>",
  "list_id": 5
}
```

> **Ważne:** Przy batch wysyłce każdy email jest personalizowany indywidualnie dla każdego subskrybenta.

---

## 📱 Wysyłka SMS z Placeholderami

### Endpoint

```http
POST /api/v1/sms/send
```

### Przykład z personalizacją

```json
{
  "phone": "+48123456789",
  "message": "Cześć [[fname]]! Twoja oferta czeka. Szczegóły: example.com/oferta",
  "subscriber_id": 123
}
```

### Batch SMS

```json
{
  "message": "[[fname]], mamy dla Ciebie promocję! -20% z kodem VIP",
  "list_id": 7
}
```

---

## 💻 Implementacja w n8n Node

### 1. Nowe pola dla Subscriber Create/Update

```typescript
// Dodaj do pól operacji subscriber:create i subscriber:update
{
  displayName: 'Custom Fields',
  name: 'customFields',
  type: 'fixedCollection',
  typeOptions: {
    multipleValues: true,
  },
  placeholder: 'Add Custom Field',
  default: {},
  displayOptions: {
    show: { resource: ['subscriber'], operation: ['create', 'update'] },
  },
  options: [
    {
      name: 'field',
      displayName: 'Field',
      values: [
        {
          displayName: 'Field Name',
          name: 'name',
          type: 'string',
          default: '',
          placeholder: 'city',
          description: 'Name of the custom field (must match NetSendo field name)',
        },
        {
          displayName: 'Value',
          name: 'value',
          type: 'string',
          default: '',
          placeholder: 'Warszawa',
          description: 'Value for the custom field',
        },
      ],
    },
  ],
  description: 'Custom field values for personalization. Field names must match those defined in NetSendo.',
}
```

### 2. Execute: Tworzenie subskrybenta z custom_fields

```typescript
// W actions/subscriber.ts - operacja create
if (operation === "create") {
  const email = this.getNodeParameter("email", i) as string;
  const contact_list_id = this.getNodeParameter("listId", i) as number;
  const additionalFields = this.getNodeParameter("additionalFields", i) as {
    first_name?: string;
    last_name?: string;
    phone?: string;
    status?: string;
    source?: string;
    tags?: number[];
  };

  // Pobierz custom fields
  const customFieldsInput = this.getNodeParameter("customFields", i, {}) as {
    field?: Array<{ name: string; value: string }>;
  };

  // Przekształć do formatu API
  const custom_fields: Record<string, string> = {};
  if (customFieldsInput.field) {
    for (const field of customFieldsInput.field) {
      if (field.name && field.value) {
        custom_fields[field.name] = field.value;
      }
    }
  }

  const body: Record<string, unknown> = {
    email,
    contact_list_id,
    ...additionalFields,
  };

  // Dodaj custom_fields tylko jeśli niepuste
  if (Object.keys(custom_fields).length > 0) {
    body.custom_fields = custom_fields;
  }

  responseData = await this.helpers.httpRequest({
    method: "POST",
    url: `${baseUrl}/api/v1/subscribers`,
    headers: {
      Authorization: `Bearer ${credentials.apiKey}`,
      "Content-Type": "application/json",
    },
    body,
    json: true,
  });
}
```

### 3. Pole informacyjne o dostępnych placeholderach

```typescript
// Dodaj do pól Content dla email:send i sms:send
{
  displayName: 'Available Placeholders',
  name: 'placeholdersNotice',
  type: 'notice',
  displayOptions: {
    show: { resource: ['email', 'sms'], operation: ['send', 'sendBatch'] },
  },
  default: '',
  description: `
    <strong>Dostępne placeholdery:</strong><br/>
    <code>[[email]]</code> - Email<br/>
    <code>[[fname]]</code> - Imię<br/>
    <code>[[lname]]</code> - Nazwisko<br/>
    <code>[[phone]]</code> - Telefon<br/>
    <code>[[unsubscribe]]</code> - Link wypisania<br/>
    <code>[[custom_field_name]]</code> - Pola niestandardowe
  `,
}
```

### 4. Metoda ładowania pól niestandardowych z API

NetSendo udostępnia endpoint do pobierania listy dostępnych pól niestandardowych:

```http
GET /api/v1/custom-fields
GET /api/v1/custom-fields/placeholders   # Wszystkie placeholdery (systemowe + custom)
```

```typescript
// methods/customFieldMethods.ts
export async function getCustomFields(
  this: ILoadOptionsFunctions
): Promise<INodePropertyOptions[]> {
  const credentials = await this.getCredentials("netSendoApi");
  const baseUrl = credentials.baseUrl as string;

  try {
    const response = await this.helpers.httpRequest({
      method: "GET",
      url: `${baseUrl}/api/v1/custom-fields`,
      headers: {
        Authorization: `Bearer ${credentials.apiKey}`,
      },
      json: true,
    });

    return response.data.map((field: { name: string; label: string }) => ({
      name: `${field.label} (${field.name})`,
      value: field.name,
    }));
  } catch (error) {
    return [];
  }
}

// Pobierz wszystkie placeholdery (do notice lub dropdown)
export async function getAllPlaceholders(
  this: ILoadOptionsFunctions
): Promise<INodePropertyOptions[]> {
  const credentials = await this.getCredentials("netSendoApi");
  const baseUrl = credentials.baseUrl as string;

  try {
    const response = await this.helpers.httpRequest({
      method: "GET",
      url: `${baseUrl}/api/v1/custom-fields/placeholders`,
      headers: {
        Authorization: `Bearer ${credentials.apiKey}`,
      },
      json: true,
    });

    const placeholders: INodePropertyOptions[] = [];

    // System placeholders
    for (const p of response.data.system) {
      placeholders.push({
        name: `${p.label} - ${p.placeholder}`,
        value: p.placeholder,
      });
    }

    // Custom placeholders
    for (const p of response.data.custom) {
      placeholders.push({
        name: `${p.label} - ${p.placeholder}`,
        value: p.placeholder,
      });
    }

    return placeholders;
  } catch (error) {
    return [];
  }
}
```

---

## 🔄 Przykładowe Workflow n8n

### Workflow 1: Webhook → Utwórz subskrybenta z custom fields → Wyślij powitalny Email

```
[n8n Webhook]
     ↓ body: { email, first_name, city, company }
[NetSendo: Create Subscriber]
     → email: {{$json.email}}
     → contact_list_id: 5
     → first_name: {{$json.first_name}}
     → custom_fields:
         - city: {{$json.city}}
         - company: {{$json.company}}
     ↓
[NetSendo: Send Email]
     → email: {{$json.email}}
     → subject: "Witaj [[fname]] z [[city]]!"
     → content: "<p>Dziękujemy za zapisanie się!</p><p>[[company]] jest teraz z nami.</p>"
     → subscriber_id: {{$node["Create Subscriber"].json.data.id}}
```

### Workflow 2: Subskrybent otrzymuje spersonalizowany SMS

```
[Schedule Trigger] (codziennie o 10:00)
     ↓
[NetSendo: SMS Batch]
     → message: "[[!fname]], mamy dla Ciebie ofertę! Sprawdź: example.com"
     → list_id: 7 (SMS list)
```

---

## 📝 Odpowiedź API z custom_fields

Przy pobieraniu subskrybenta, custom_fields są zwracane jako obiekt:

```json
{
  "data": {
    "id": 456,
    "email": "jan@example.com",
    "first_name": "Jan",
    "last_name": "Kowalski",
    "phone": "+48123456789",
    "status": "active",
    "custom_fields": {
      "city": "Warszawa",
      "company": "Firma Sp. z o.o.",
      "birth_date": "1990-05-15"
    },
    "tags": [{ "id": 1, "name": "VIP" }],
    "created_at": "2025-01-15T10:30:00.000000Z"
  }
}
```

---

## ⚠️ Ważne uwagi

1. **Subscriber ID przy personalizacji**: Przy wysyłce pojedynczego email/SMS z placeholderami, podaj `subscriber_id` aby system mógł pobrać dane subskrybenta do personalizacji.

2. **Batch wysyłka**: Przy batch wysyłce (do listy/tagów) personalizacja następuje automatycznie dla każdego odbiorcy.

3. **Nieznane placeholdery**: Jeśli placeholder nie ma wartości, zostanie zastąpiony pustym stringiem.

4. **Walidacja pól**: Upewnij się, że nazwy pól w `custom_fields` odpowiadają zdefiniowanym polom w NetSendo.

---

## 📁 Pliki do modyfikacji w n8n-nodes-netsendo

1. `nodes/NetSendo/NetSendo.node.ts` - dodaj pole customFields do subscriber operations
2. `nodes/NetSendo/actions/subscriber.ts` - dodaj obsługę custom_fields w execute
3. `nodes/NetSendo/actions/email.ts` - dodaj notice o placeholderach
4. `nodes/NetSendo/actions/sms.ts` - dodaj notice o placeholderach
