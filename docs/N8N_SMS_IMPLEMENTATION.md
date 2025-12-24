# NetSendo n8n Node - SMS Implementation Guide

Instrukcja dla agenta pracującego nad community node **n8n-nodes-netsendo** - rozszerzenie o funkcjonalności SMS.

---

## Podsumowanie zmian w API

NetSendo API v1 zostało rozbudowane o:

1. **Nowe endpointy SMS:**

   - `POST /api/v1/sms/send` - Wysyłka pojedynczego SMS
   - `POST /api/v1/sms/batch` - Wysyłka batch SMS
   - `GET /api/v1/sms/status/{id}` - Status wiadomości
   - `GET /api/v1/sms/providers` - Lista providerów SMS

2. **Nowe webhook eventy:**

   - `sms.queued` - SMS dodany do kolejki
   - `sms.sent` - SMS wysłany pomyślnie
   - `sms.failed` - SMS nie został dostarczony

3. **Nowe uprawnienia API Key:**
   - `sms:read` - Odczyt statusu SMS i providerów
   - `sms:write` - Wysyłanie SMS

---

## Struktura node'a n8n

### Proponowana architektura plików

```
nodes/NetSendo/
├── NetSendo.node.ts          # Główny action node
├── NetSendo.node.json        # Manifest
├── NetSendoTrigger.node.ts   # Trigger node (webhooks)
├── NetSendoTrigger.node.json
├── netsendo.svg              # Ikona
└── actions/
    ├── subscriber.ts
    ├── list.ts
    ├── tag.ts
    ├── webhook.ts
    └── sms.ts                # NOWY - operacje SMS
```

---

## Implementacja SMS Resource

### 1. Definicja resource SMS w NetSendo.node.ts

```typescript
// W sekcji properties, dodaj do multiOptions 'resource':
{
  displayName: 'Resource',
  name: 'resource',
  type: 'options',
  noDataExpression: true,
  options: [
    { name: 'Subscriber', value: 'subscriber' },
    { name: 'List', value: 'list' },
    { name: 'Tag', value: 'tag' },
    { name: 'Webhook', value: 'webhook' },
    { name: 'SMS', value: 'sms' },  // NOWY
  ],
  default: 'subscriber',
}
```

### 2. Operacje SMS

```typescript
// operations dla resource 'sms'
{
  displayName: 'Operation',
  name: 'operation',
  type: 'options',
  displayOptions: {
    show: { resource: ['sms'] },
  },
  options: [
    {
      name: 'Send',
      value: 'send',
      description: 'Send a single SMS message',
      action: 'Send an SMS',
    },
    {
      name: 'Send Batch',
      value: 'sendBatch',
      description: 'Send SMS to multiple recipients',
      action: 'Send batch SMS',
    },
    {
      name: 'Get Status',
      value: 'getStatus',
      description: 'Get SMS delivery status',
      action: 'Get SMS status',
    },
    {
      name: 'List Providers',
      value: 'listProviders',
      description: 'Get available SMS providers',
      action: 'List SMS providers',
    },
  ],
  default: 'send',
}
```

### 3. Pola dla operacji SMS Send

```typescript
// Pola dla sms:send
{
  displayName: 'Phone Number',
  name: 'phone',
  type: 'string',
  required: true,
  displayOptions: {
    show: { resource: ['sms'], operation: ['send'] },
  },
  default: '',
  placeholder: '+48123456789',
  description: 'Phone number with country code',
},
{
  displayName: 'Message',
  name: 'message',
  type: 'string',
  typeOptions: { rows: 4 },
  required: true,
  displayOptions: {
    show: { resource: ['sms'], operation: ['send'] },
  },
  default: '',
  description: 'SMS message content (max 1600 characters)',
},
{
  displayName: 'Additional Fields',
  name: 'additionalFields',
  type: 'collection',
  placeholder: 'Add Field',
  default: {},
  displayOptions: {
    show: { resource: ['sms'], operation: ['send'] },
  },
  options: [
    {
      displayName: 'Provider ID',
      name: 'provider_id',
      type: 'number',
      default: 0,
      description: 'Specific SMS provider to use (optional)',
    },
    {
      displayName: 'Schedule At',
      name: 'schedule_at',
      type: 'dateTime',
      default: '',
      description: 'Schedule SMS for later delivery',
    },
    {
      displayName: 'Subscriber ID',
      name: 'subscriber_id',
      type: 'number',
      default: 0,
      description: 'Link to existing subscriber',
    },
  ],
}
```

### 4. Pola dla operacji SMS Batch

```typescript
// Pola dla sms:sendBatch
{
  displayName: 'Message',
  name: 'message',
  type: 'string',
  typeOptions: { rows: 4 },
  required: true,
  displayOptions: {
    show: { resource: ['sms'], operation: ['sendBatch'] },
  },
  default: '',
  description: 'SMS message content (max 1600 characters)',
},
{
  displayName: 'Target',
  name: 'target',
  type: 'options',
  displayOptions: {
    show: { resource: ['sms'], operation: ['sendBatch'] },
  },
  options: [
    { name: 'SMS List', value: 'list' },
    { name: 'Tags', value: 'tags' },
    { name: 'Subscriber IDs', value: 'subscribers' },
  ],
  default: 'list',
},
{
  displayName: 'List ID',
  name: 'list_id',
  type: 'number',
  required: true,
  displayOptions: {
    show: { resource: ['sms'], operation: ['sendBatch'], target: ['list'] },
  },
  default: 0,
  description: 'SMS list to send to',
},
{
  displayName: 'Tag IDs',
  name: 'tag_ids',
  type: 'string',
  required: true,
  displayOptions: {
    show: { resource: ['sms'], operation: ['sendBatch'], target: ['tags'] },
  },
  default: '',
  placeholder: '1,2,3',
  description: 'Comma-separated tag IDs',
},
{
  displayName: 'Subscriber IDs',
  name: 'subscriber_ids',
  type: 'string',
  required: true,
  displayOptions: {
    show: { resource: ['sms'], operation: ['sendBatch'], target: ['subscribers'] },
  },
  default: '',
  placeholder: '1,2,3',
  description: 'Comma-separated subscriber IDs',
}
```

---

## Implementacja execute dla SMS

### sms.ts

```typescript
import {
  IExecuteFunctions,
  INodeExecutionData,
  IHttpRequestMethods,
} from "n8n-workflow";

export async function executeSmsOperations(
  this: IExecuteFunctions,
  items: INodeExecutionData[],
  operation: string
): Promise<INodeExecutionData[]> {
  const returnData: INodeExecutionData[] = [];
  const credentials = await this.getCredentials("netSendoApi");
  const baseUrl = credentials.baseUrl as string;

  for (let i = 0; i < items.length; i++) {
    try {
      let responseData;

      if (operation === "send") {
        const phone = this.getNodeParameter("phone", i) as string;
        const message = this.getNodeParameter("message", i) as string;
        const additionalFields = this.getNodeParameter(
          "additionalFields",
          i
        ) as object;

        responseData = await this.helpers.httpRequest({
          method: "POST" as IHttpRequestMethods,
          url: `${baseUrl}/api/v1/sms/send`,
          headers: {
            Authorization: `Bearer ${credentials.apiKey}`,
            "Content-Type": "application/json",
          },
          body: {
            phone,
            message,
            ...additionalFields,
          },
          json: true,
        });
      }

      if (operation === "sendBatch") {
        const message = this.getNodeParameter("message", i) as string;
        const target = this.getNodeParameter("target", i) as string;

        const body: Record<string, unknown> = { message };

        if (target === "list") {
          body.list_id = this.getNodeParameter("list_id", i);
        } else if (target === "tags") {
          const tagIds = this.getNodeParameter("tag_ids", i) as string;
          body.tag_ids = tagIds.split(",").map((id) => parseInt(id.trim()));
        } else if (target === "subscribers") {
          const subIds = this.getNodeParameter("subscriber_ids", i) as string;
          body.subscriber_ids = subIds
            .split(",")
            .map((id) => parseInt(id.trim()));
        }

        responseData = await this.helpers.httpRequest({
          method: "POST" as IHttpRequestMethods,
          url: `${baseUrl}/api/v1/sms/batch`,
          headers: {
            Authorization: `Bearer ${credentials.apiKey}`,
            "Content-Type": "application/json",
          },
          body,
          json: true,
        });
      }

      if (operation === "getStatus") {
        const id = this.getNodeParameter("id", i) as number;

        responseData = await this.helpers.httpRequest({
          method: "GET" as IHttpRequestMethods,
          url: `${baseUrl}/api/v1/sms/status/${id}`,
          headers: {
            Authorization: `Bearer ${credentials.apiKey}`,
          },
          json: true,
        });
      }

      if (operation === "listProviders") {
        responseData = await this.helpers.httpRequest({
          method: "GET" as IHttpRequestMethods,
          url: `${baseUrl}/api/v1/sms/providers`,
          headers: {
            Authorization: `Bearer ${credentials.apiKey}`,
          },
          json: true,
        });
      }

      returnData.push({ json: responseData });
    } catch (error) {
      if (this.continueOnFail()) {
        returnData.push({ json: { error: (error as Error).message } });
        continue;
      }
      throw error;
    }
  }

  return returnData;
}
```

---

## Rozszerzenie NetSendoTrigger o eventy SMS

### Aktualizacja eventów w NetSendoTrigger.node.ts

```typescript
// Dodaj do options dla 'events' property:
{
  displayName: 'Events',
  name: 'events',
  type: 'multiOptions',
  options: [
    // Subscriber events
    { name: 'Subscriber Created', value: 'subscriber.created' },
    { name: 'Subscriber Updated', value: 'subscriber.updated' },
    { name: 'Subscriber Deleted', value: 'subscriber.deleted' },
    { name: 'Subscriber Subscribed', value: 'subscriber.subscribed' },
    { name: 'Subscriber Unsubscribed', value: 'subscriber.unsubscribed' },
    { name: 'Subscriber Bounced', value: 'subscriber.bounced' },
    { name: 'Tag Added', value: 'subscriber.tag_added' },
    { name: 'Tag Removed', value: 'subscriber.tag_removed' },
    // SMS events (NOWE)
    { name: 'SMS Queued', value: 'sms.queued' },
    { name: 'SMS Sent', value: 'sms.sent' },
    { name: 'SMS Failed', value: 'sms.failed' },
  ],
  default: ['subscriber.created'],
  required: true,
  description: 'Events to listen for',
}
```

---

## Payloady SMS Webhooków

### sms.queued (pojedynczy SMS)

```json
{
  "event": "sms.queued",
  "timestamp": "2025-12-24T21:10:00Z",
  "data": {
    "message_id": 123,
    "phone": "+48123456789",
    "content": "Hello from NetSendo!",
    "provider": "Twilio",
    "scheduled_at": null
  }
}
```

### sms.queued (batch SMS)

```json
{
  "event": "sms.queued",
  "timestamp": "2025-12-24T21:10:00Z",
  "data": {
    "message_id": 124,
    "type": "batch",
    "recipients_count": 150,
    "provider": "SMS API (Polska)"
  }
}
```

### sms.sent

```json
{
  "event": "sms.sent",
  "timestamp": "2025-12-24T21:10:05Z",
  "data": {
    "message_id": 123,
    "phone": "+48123456789",
    "external_id": "SM1234567890",
    "credits_used": 1,
    "parts": 1
  }
}
```

### sms.failed

```json
{
  "event": "sms.failed",
  "timestamp": "2025-12-24T21:10:05Z",
  "data": {
    "message_id": 123,
    "phone": "+48123456789",
    "error_code": "INVALID_NUMBER",
    "error_message": "Phone number is not valid"
  }
}
```

---

## Podział list w node n8n

### Filtrowanie list po typie

Dla resource `list` dodaj możliwość filtrowania po typie:

```typescript
{
  displayName: 'List Type',
  name: 'listType',
  type: 'options',
  displayOptions: {
    show: { resource: ['list'], operation: ['getAll'] },
  },
  options: [
    { name: 'All', value: 'all' },
    { name: 'Email Lists', value: 'email' },
    { name: 'SMS Lists', value: 'sms' },
  ],
  default: 'all',
  description: 'Filter lists by type',
}
```

Przy wywołaniu API dodaj query param:

```typescript
if (listType !== "all") {
  qs.type = listType;
}
```

---

## Aktualizacja credentials

Upewnij się, że credentials zawierają informację o wymaganych uprawnieniach:

```typescript
// W NetSendoApi.credentials.ts
{
  displayName: 'API Key',
  name: 'apiKey',
  type: 'string',
  typeOptions: { password: true },
  default: '',
  description: 'API key from NetSendo. Required permissions: subscribers:read/write, lists:read, tags:read, webhooks:read/write, sms:read/write',
}
```

---

## Testowanie

### 1. Test wysyłki pojedynczego SMS

1. Skonfiguruj credentials z API Key posiadającym `sms:write`
2. Stwórz workflow z node NetSendo
3. Wybierz: Resource = SMS, Operation = Send
4. Wprowadź numer telefonu i treść
5. Wykonaj i sprawdź odpowiedź z `message_id`

### 2. Test batch SMS

1. Stwórz listę SMS w NetSendo z subskrybentami
2. Użyj operacji Send Batch z Target = SMS List
3. Sprawdź `queued_count` w odpowiedzi

### 3. Test triggerów SMS

1. Stwórz workflow z NetSendo Trigger
2. Wybierz eventy: `sms.queued`, `sms.sent`, `sms.failed`
3. Aktywuj workflow
4. Wyślij SMS przez API lub panel
5. Sprawdź czy trigger otrzymał payload

---

## Migracja istniejącego node'a

Jeśli node już istnieje, dodaj:

1. Nowy resource `sms` do listy resources
2. Nowe operacje dla SMS
3. Pola formularza dla każdej operacji
4. Funkcję execute dla SMS
5. Nowe eventy w triggerze

Pamiętaj o backward compatibility - istniejące funkcje muszą działać bez zmian.
