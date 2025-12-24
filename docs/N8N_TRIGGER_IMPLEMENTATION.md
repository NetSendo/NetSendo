# NetSendo n8n Node - Implementacja Triggerów

Instrukcja dla agenta pracującego nad community node n8n-nodes-netsendo.

## Kontekst

NetSendo API v1 zostało rozbudowane o system webhooków (triggerów). Node n8n powinien obsługiwać te triggery jako **Webhook Trigger Node**.

---

## API Endpoints do użycia

| Endpoint                  | Metoda   | Opis                         |
| ------------------------- | -------- | ---------------------------- |
| `/api/v1/webhooks`        | `POST`   | Rejestracja nowego webhooków |
| `/api/v1/webhooks/{id}`   | `DELETE` | Usunięcie webhooków          |
| `/api/v1/webhooks/events` | `GET`    | Lista dostępnych eventów     |

---

## Dostępne eventy

```typescript
const NETSENDO_EVENTS = [
  "subscriber.created",
  "subscriber.updated",
  "subscriber.deleted",
  "subscriber.subscribed",
  "subscriber.unsubscribed",
  "subscriber.bounced",
  "subscriber.tag_added",
  "subscriber.tag_removed",
];
```

---

## Struktura plików do stworzenia

```
nodes/NetSendo/
├── NetSendoTrigger.node.ts    # Trigger node
├── NetSendoTrigger.node.json  # Manifest dla trigger node
```

---

## Implementacja NetSendoTrigger.node.ts

### 1. Definicja Node

```typescript
import type {
  IHookFunctions,
  IWebhookFunctions,
  INodeType,
  INodeTypeDescription,
  IWebhookResponseData,
} from 'n8n-workflow';

export class NetSendoTrigger implements INodeType {
  description: INodeTypeDescription = {
    displayName: 'NetSendo Trigger',
    name: 'netSendoTrigger',
    icon: 'file:netsendo.svg',
    group: ['trigger'],
    version: 1,
    description: 'Starts the workflow when NetSendo events occur',
    defaults: {
      name: 'NetSendo Trigger',
    },
    inputs: [],
    outputs: ['main'],
    credentials: [
      {
        name: 'netSendoApi',
        required: true,
      },
    ],
    webhooks: [
      {
        name: 'default',
        httpMethod: 'POST',
        responseMode: 'onReceived',
        path: 'webhook',
      },
    ],
    properties: [
      {
        displayName: 'Events',
        name: 'events',
        type: 'multiOptions',
        options: [
          { name: 'Subscriber Created', value: 'subscriber.created' },
          { name: 'Subscriber Updated', value: 'subscriber.updated' },
          { name: 'Subscriber Deleted', value: 'subscriber.deleted' },
          { name: 'Subscriber Subscribed', value: 'subscriber.subscribed' },
          { name: 'Subscriber Unsubscribed', value: 'subscriber.unsubscribed' },
          { name: 'Subscriber Bounced', value: 'subscriber.bounced' },
          { name: 'Tag Added', value: 'subscriber.tag_added' },
          { name: 'Tag Removed', value: 'subscriber.tag_removed' },
        ],
        default: ['subscriber.created'],
        required: true,
        description: 'Events to listen for',
      },
    ],
  };
```

### 2. Metody Webhook Lifecycle

```typescript
webhookMethods = {
  default: {
    // Sprawdź czy webhook już istnieje
    async checkExists(this: IHookFunctions): Promise<boolean> {
      const webhookData = this.getWorkflowStaticData("node");
      return webhookData.webhookId !== undefined;
    },

    // Zarejestruj webhook w NetSendo
    async create(this: IHookFunctions): Promise<boolean> {
      const webhookUrl = this.getNodeWebhookUrl("default");
      const events = this.getNodeParameter("events") as string[];
      const credentials = await this.getCredentials("netSendoApi");

      const body = {
        name: `n8n Workflow: ${this.getWorkflow().name}`,
        url: webhookUrl,
        events: events,
      };

      const response = await this.helpers.httpRequest({
        method: "POST",
        url: `${credentials.baseUrl}/api/v1/webhooks`,
        headers: {
          Authorization: `Bearer ${credentials.apiKey}`,
          "Content-Type": "application/json",
        },
        body: body,
        json: true,
      });

      const webhookData = this.getWorkflowStaticData("node");
      webhookData.webhookId = response.data.id;
      webhookData.webhookSecret = response.data.secret;

      return true;
    },

    // Usuń webhook z NetSendo
    async delete(this: IHookFunctions): Promise<boolean> {
      const webhookData = this.getWorkflowStaticData("node");
      const credentials = await this.getCredentials("netSendoApi");

      if (webhookData.webhookId) {
        try {
          await this.helpers.httpRequest({
            method: "DELETE",
            url: `${credentials.baseUrl}/api/v1/webhooks/${webhookData.webhookId}`,
            headers: {
              Authorization: `Bearer ${credentials.apiKey}`,
            },
          });
        } catch (error) {
          // Webhook may already be deleted
        }
      }

      delete webhookData.webhookId;
      delete webhookData.webhookSecret;

      return true;
    },
  },
};
```

### 3. Handler dla przychodzących webhooków

```typescript
  async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
    const req = this.getRequestObject();
    const body = this.getBodyData();

    // Opcjonalnie: Weryfikacja HMAC signature
    const webhookData = this.getWorkflowStaticData('node');
    const signature = req.headers['x-netsendo-signature'] as string;

    if (webhookData.webhookSecret && signature) {
      const crypto = require('crypto');
      const expectedSignature = crypto
        .createHmac('sha256', webhookData.webhookSecret)
        .update(JSON.stringify(body))
        .digest('hex');

      if (signature !== expectedSignature) {
        return {
          webhookResponse: 'Invalid signature',
        };
      }
    }

    return {
      workflowData: [this.helpers.returnJsonArray(body as object)],
    };
  }
}
```

---

## Struktura payloadu przychodzącego

n8n otrzyma payload w formacie:

```json
{
  "event": "subscriber.created",
  "timestamp": "2025-12-24T02:00:00Z",
  "data": {
    "subscriber": {
      "id": 123,
      "email": "test@example.com",
      "first_name": "Jan",
      "last_name": "Kowalski",
      "phone": null,
      "status": "active",
      "source": "api",
      "tags": [],
      "custom_fields": {},
      "lists": [{ "id": 1, "name": "Newsletter" }],
      "created_at": "2025-12-24T02:00:00Z",
      "updated_at": "2025-12-24T02:00:00Z"
    },
    "list_id": 1,
    "list_name": "Newsletter"
  }
}
```

---

## Aktualizacja package.json

```json
{
  "n8n": {
    "nodes": [
      "dist/nodes/NetSendo/NetSendo.node.js",
      "dist/nodes/NetSendo/NetSendoTrigger.node.js"
    ]
  }
}
```

---

## Wymagane permissions dla API Key

API Key używany w credentials musi mieć:

- `webhooks:read`
- `webhooks:write`

---

## Testowanie

1. Stwórz workflow z NetSendo Trigger
2. Wybierz eventy (np. `subscriber.created`)
3. Aktywuj workflow
4. W NetSendo: sprawdź czy webhook został zarejestrowany (`GET /api/v1/webhooks`)
5. Dodaj subskrybenta przez API lub UI
6. Sprawdź czy n8n otrzymał payload

---

## Headers wysyłane przez NetSendo

| Header                 | Wartość                |
| ---------------------- | ---------------------- |
| `Content-Type`         | `application/json`     |
| `X-NetSendo-Signature` | HMAC-SHA256 podpis     |
| `X-NetSendo-Event`     | Nazwa eventu           |
| `User-Agent`           | `NetSendo-Webhook/1.0` |
