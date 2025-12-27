# NetSendo n8n Node - Email Implementation Guide

Instrukcja dla agenta pracującego nad community node **n8n-nodes-netsendo** - rozszerzenie o funkcjonalności Email.

---

## Podsumowanie zmian w API

NetSendo API v1 zostało rozbudowane o:

1. **Nowe endpointy Email:**

   - `POST /api/v1/email/send` - Wysyłka pojedynczego Email
   - `POST /api/v1/email/batch` - Wysyłka batch Email do listy/tagów
   - `GET /api/v1/email/status/{id}` - Status wiadomości
   - `GET /api/v1/email/mailboxes` - Lista skrzynek nadawczych

2. **Nowe webhook eventy:**

   - `email.queued` - Email dodany do kolejki

3. **Nowe uprawnienia API Key:**
   - `email:read` - Odczyt statusu Email i mailboxów
   - `email:write` - Wysyłanie Email

---

## Implementacja Email Resource

### 1. Definicja resource Email w NetSendo.node.ts

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
    { name: 'Email', value: 'email' },  // NOWY
    { name: 'SMS', value: 'sms' },
  ],
  default: 'subscriber',
}
```

### 2. Operacje Email

```typescript
// operations dla resource 'email'
{
  displayName: 'Operation',
  name: 'operation',
  type: 'options',
  displayOptions: {
    show: { resource: ['email'] },
  },
  options: [
    {
      name: 'Send',
      value: 'send',
      description: 'Send a single email message',
      action: 'Send an email',
    },
    {
      name: 'Send Batch',
      value: 'sendBatch',
      description: 'Send email to multiple recipients',
      action: 'Send batch email',
    },
    {
      name: 'Get Status',
      value: 'getStatus',
      description: 'Get email delivery status',
      action: 'Get email status',
    },
    {
      name: 'List Mailboxes',
      value: 'listMailboxes',
      description: 'Get available mailboxes',
      action: 'List mailboxes',
    },
  ],
  default: 'send',
}
```

### 3. Pola dla operacji Email Send

```typescript
// Pola dla email:send
{
  displayName: 'Email Address',
  name: 'email',
  type: 'string',
  required: true,
  displayOptions: {
    show: { resource: ['email'], operation: ['send'] },
  },
  default: '',
  placeholder: 'user@example.com',
  description: 'Recipient email address',
},
{
  displayName: 'Subject',
  name: 'subject',
  type: 'string',
  required: true,
  displayOptions: {
    show: { resource: ['email'], operation: ['send', 'sendBatch'] },
  },
  default: '',
  description: 'Email subject line',
},
{
  displayName: 'Content (HTML)',
  name: 'content',
  type: 'string',
  typeOptions: { rows: 8 },
  required: true,
  displayOptions: {
    show: { resource: ['email'], operation: ['send', 'sendBatch'] },
  },
  default: '',
  description: 'Email body content (HTML supported)',
},
{
  displayName: 'Additional Fields',
  name: 'additionalFields',
  type: 'collection',
  placeholder: 'Add Field',
  default: {},
  displayOptions: {
    show: { resource: ['email'], operation: ['send'] },
  },
  options: [
    {
      displayName: 'Mailbox',
      name: 'mailbox_id',
      type: 'options',
      typeOptions: { loadOptionsMethod: 'getMailboxes' },
      default: '',
      description: 'Mailbox to send from',
    },
    {
      displayName: 'Preheader',
      name: 'preheader',
      type: 'string',
      default: '',
      description: 'Email preheader text',
    },
    {
      displayName: 'Schedule At',
      name: 'schedule_at',
      type: 'dateTime',
      default: '',
      description: 'Schedule email for later delivery',
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

### 4. Pola dla operacji Email Batch

```typescript
{
  displayName: 'Target',
  name: 'target',
  type: 'options',
  displayOptions: {
    show: { resource: ['email'], operation: ['sendBatch'] },
  },
  options: [
    { name: 'Email List', value: 'list' },
    { name: 'Tags', value: 'tags' },
    { name: 'Subscriber IDs', value: 'subscribers' },
  ],
  default: 'list',
},
{
  displayName: 'Email List',
  name: 'list_id',
  type: 'options',
  typeOptions: { loadOptionsMethod: 'getEmailLists' },
  required: true,
  displayOptions: {
    show: { resource: ['email'], operation: ['sendBatch'], target: ['list'] },
  },
  default: '',
  description: 'Email list to send to',
},
{
  displayName: 'Tag IDs',
  name: 'tag_ids',
  type: 'string',
  required: true,
  displayOptions: {
    show: { resource: ['email'], operation: ['sendBatch'], target: ['tags'] },
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
    show: { resource: ['email'], operation: ['sendBatch'], target: ['subscribers'] },
  },
  default: '',
  placeholder: '1,2,3',
  description: 'Comma-separated subscriber IDs',
},
{
  displayName: 'Mailbox',
  name: 'mailbox_id',
  type: 'options',
  typeOptions: { loadOptionsMethod: 'getMailboxes' },
  default: '',
  displayOptions: {
    show: { resource: ['email'], operation: ['sendBatch'] },
  },
  description: 'Mailbox to send from',
},
{
  displayName: 'Schedule At',
  name: 'schedule_at',
  type: 'dateTime',
  default: '',
  displayOptions: {
    show: { resource: ['email'], operation: ['sendBatch'] },
  },
  description: 'Schedule batch email for later delivery',
},
{
  displayName: 'Excluded Lists',
  name: 'excluded_list_ids',
  type: 'multiOptions',
  typeOptions: { loadOptionsMethod: 'getEmailLists' },
  default: [],
  displayOptions: {
    show: { resource: ['email'], operation: ['sendBatch'] },
  },
  description: 'Lists to exclude from sending',
}
```

---

## Implementacja execute dla Email

### actions/email.ts

```typescript
import {
  IExecuteFunctions,
  INodeExecutionData,
  IHttpRequestMethods,
} from "n8n-workflow";

export async function executeEmailOperations(
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
        const email = this.getNodeParameter("email", i) as string;
        const subject = this.getNodeParameter("subject", i) as string;
        const content = this.getNodeParameter("content", i) as string;
        const additionalFields = this.getNodeParameter(
          "additionalFields",
          i
        ) as object;

        responseData = await this.helpers.httpRequest({
          method: "POST" as IHttpRequestMethods,
          url: `${baseUrl}/api/v1/email/send`,
          headers: {
            Authorization: `Bearer ${credentials.apiKey}`,
            "Content-Type": "application/json",
          },
          body: {
            email,
            subject,
            content,
            ...additionalFields,
          },
          json: true,
        });
      }

      if (operation === "sendBatch") {
        const subject = this.getNodeParameter("subject", i) as string;
        const content = this.getNodeParameter("content", i) as string;
        const target = this.getNodeParameter("target", i) as string;
        const mailbox_id = this.getNodeParameter("mailbox_id", i, "") as string;
        const schedule_at = this.getNodeParameter(
          "schedule_at",
          i,
          ""
        ) as string;
        const excluded_list_ids = this.getNodeParameter(
          "excluded_list_ids",
          i,
          []
        ) as number[];

        const body: Record<string, unknown> = { subject, content };

        if (mailbox_id) body.mailbox_id = parseInt(mailbox_id);
        if (schedule_at) body.schedule_at = schedule_at;
        if (excluded_list_ids.length)
          body.excluded_list_ids = excluded_list_ids;

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
          url: `${baseUrl}/api/v1/email/batch`,
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
          url: `${baseUrl}/api/v1/email/status/${id}`,
          headers: {
            Authorization: `Bearer ${credentials.apiKey}`,
          },
          json: true,
        });
      }

      if (operation === "listMailboxes") {
        responseData = await this.helpers.httpRequest({
          method: "GET" as IHttpRequestMethods,
          url: `${baseUrl}/api/v1/email/mailboxes`,
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

## Metoda ładowania mailboxów

### methods/mailboxMethods.ts

```typescript
import { ILoadOptionsFunctions, INodePropertyOptions } from "n8n-workflow";

export async function getMailboxes(
  this: ILoadOptionsFunctions
): Promise<INodePropertyOptions[]> {
  const credentials = await this.getCredentials("netSendoApi");
  const baseUrl = credentials.baseUrl as string;

  const response = await this.helpers.httpRequest({
    method: "GET",
    url: `${baseUrl}/api/v1/email/mailboxes`,
    headers: {
      Authorization: `Bearer ${credentials.apiKey}`,
    },
    json: true,
  });

  return response.data.map(
    (mailbox: {
      id: number;
      name: string;
      from_email: string;
      is_default: boolean;
    }) => ({
      name: `${mailbox.name} (${mailbox.from_email})${
        mailbox.is_default ? " ★" : ""
      }`,
      value: mailbox.id,
    })
  );
}
```

---

## Rozszerzenie NetSendoTrigger o eventy Email

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
    // Email events (NOWE)
    { name: 'Email Queued', value: 'email.queued' },
    // SMS events
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

## Payload webhooka email.queued

```json
{
  "event": "email.queued",
  "timestamp": "2025-12-24T21:10:00Z",
  "data": {
    "message_id": 123,
    "email": "user@example.com",
    "subject": "Newsletter December",
    "mailbox": "Main Mailbox",
    "scheduled_at": "2025-12-25T10:00:00Z"
  }
}
```

---

## Testowanie

### 1. Test wysyłki pojedynczego Email

1. Skonfiguruj credentials z API Key posiadającym `email:write`
2. Stwórz workflow z node NetSendo
3. Wybierz: Resource = Email, Operation = Send
4. Wprowadź email, temat, treść
5. Opcjonalnie: ustaw Schedule At
6. Wykonaj i sprawdź odpowiedź z `message_id`

### 2. Test batch Email

1. Stwórz listę Email w NetSendo z subskrybentami
2. Użyj operacji Send Batch z Target = Email List
3. Sprawdź `queued_count` w odpowiedzi

### 3. Test planowania Email

1. Użyj pola Schedule At z datą w przyszłości
2. Sprawdź status przez Get Status
3. Zweryfikuj czy `scheduled_at` jest ustawiony

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
  description: 'API key from NetSendo. Required permissions: subscribers:read/write, lists:read, tags:read, webhooks:read/write, email:read/write, sms:read/write',
}
```

---

## Pliki do modyfikacji

1. `nodes/NetSendo/NetSendo.node.ts` - dodaj resource Email i operacje
2. `nodes/NetSendo/actions/email.ts` - logika execute (NOWY PLIK)
3. `nodes/NetSendo/methods/mailboxMethods.ts` - metoda getMailboxes (NOWY PLIK)
4. `nodes/NetSendo/NetSendoTrigger.node.ts` - dodaj event email.queued
