# Bounce webhooks

Email providers can report bounces to NetSendo as they happen. NetSendo then marks the subscriber as bounced according to the list settings: bounce analysis on or off, list or global scope, and the soft-bounce threshold.

Bounce webhooks are optional. NetSendo also detects bounces in two other ways:

- **Bounce mailbox (IMAP):** Settings → Mailboxes → edit a mailbox → *Bounce Mailbox Monitoring*.
- **SMTP errors:** NetSendo reads the error when a message is rejected while it is being sent.

## Security

Every webhook request must carry the secret of the mailbox in its URL. Each provider uses its own mechanism:

| Provider | URL | Authentication |
|---|---|---|
| SendGrid | `/webhooks/bounce/sendgrid/{mailbox}` | Signed Event Webhook: an ECDSA signature of the timestamp and the raw body, checked against the SendGrid verification key |
| Mailgun | `/webhooks/bounce/mailgun/{mailbox}` | HMAC-SHA256 of the timestamp and the token, made with the Mailgun HTTP webhook signing key |
| Postmark | `/webhooks/bounce/postmark/{mailbox}` | HTTP Basic auth with the mailbox's NetSendo token as the password, or `?token=` |
| Other | `/webhooks/bounce/generic/{mailbox}` | The NetSendo token in the `X-NetSendo-Webhook-Token` header, as `Authorization: Bearer`, as a Basic auth password, or as `?token=` |

- Requests without valid credentials get `401 {"error":"Unauthorized"}`. NetSendo logs each one as `Bounce webhook rejected`, with the provider, the mailbox ID, the reason and the sender's IP address.
- A mailbox only changes subscribers that belong to its owner. An address that also exists in another account on the same instance is not touched.
- Secrets are stored encrypted with the application key, like other mailbox credentials. The Mailgun signing key is never sent back to the browser.
- Tokens and Mailgun HMAC signatures are compared in constant time (`hash_equals`).
- **Old URLs are rejected.** `/webhooks/bounce/sendgrid`, `/mailgun`, `/postmark` and `/generic` without a mailbox ID could not be authenticated, so anyone who knew an instance URL could mark any address as bounced. These URLs now always answer 401. If your provider still posts to one of them, set the webhook up again as described below.

## Where to find the URL and the secrets

Go to **Settings → Mailboxes**, edit a mailbox and open the **Bounce webhooks** section. Gmail mailboxes do not have this section. It shows:

- a tab for each provider, with the webhook URL and a copy button,
- the field for the SendGrid or Mailgun key, or the NetSendo token for Postmark and *Other*,
- whether a secret is saved. Without a secret, NetSendo rejects requests to that URL.

Use the mailbox you send through with that provider. If several mailboxes share one provider account, one webhook pointing to one of them is enough, because bounces apply to all subscribers of that account owner.

## SendGrid

1. In NetSendo, copy the URL from the **SendGrid** tab.
2. In SendGrid, open **Settings → Mail Settings → Event Webhook** and create a webhook (or edit the existing one).
3. Paste the URL as the *Post URL*. Under events, select **Bounced**, **Dropped** and **Deferred**. NetSendo ignores the other events.
4. Turn on **Signed Event Webhook** and copy the **Verification Key** that SendGrid shows.
5. Paste the key into the *SendGrid verification key* field in NetSendo and save the mailbox. NetSendo accepts the key with or without the `-----BEGIN PUBLIC KEY-----` lines.
6. Use SendGrid's **Test Your Integration**. A correct setup answers `200`. The sample events use example addresses, so no subscriber changes.

If you turn signing off and on again in SendGrid, it creates a new key. Paste the new key into NetSendo.

## Mailgun

1. In NetSendo, copy the URL from the **Mailgun** tab.
2. In Mailgun, open **Send → Sending → Webhooks** and select the sending domain.
3. Add the URL for **Permanent failure** and **Temporary failure**.
4. Copy the **HTTP webhook signing key** from the same page, paste it into the *Mailgun HTTP webhook signing key* field in NetSendo and save the mailbox.

Mailgun signs only the timestamp and a random token, not the event itself. NetSendo therefore accepts each token only once.

## Postmark

Postmark does not sign webhooks, so NetSendo uses its own token.

1. In NetSendo, open the **Postmark** tab and click **Generate token**.
2. In Postmark, open your server's message stream → **Webhooks → Add webhook**.
3. Paste the URL from the **Postmark** tab. Under **Basic auth credentials**, enter the username `netsendo` and the token as the password. NetSendo checks only the password.
4. Select the **Bounce** and **Spam complaint** events and save.

Instead of Basic auth, you can append `?token=<token>` to the URL. Basic auth is the better choice, because web servers write full URLs, including the query string, to their access logs.

## Other providers and your own scripts

Generate a token in the **Other** tab and send a POST:

```bash
curl -X POST https://netsendo.example.com/webhooks/bounce/generic/12 \
  -H "X-NetSendo-Webhook-Token: <token>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"reader@example.com","bounce_type":"hard","bounce_reason":"550 5.1.1 User unknown"}'
```

| Field | Required | Description |
|---|---|---|
| `email` | yes | The address that bounced |
| `bounce_type` | no | `hard` (default) or `soft` |
| `bounce_reason` | no | Up to 255 characters |
| `message_id` | no | ID of the NetSendo message that bounced |

Postmark and *Other* share one token per mailbox. **Generate new token** replaces it, and the previous token stops working at once.

## Responses

| Status | Meaning |
|---|---|
| `200 {"status":"ok"}` | Authenticated and processed. |
| `200 {"status":"ignored"}` | Authenticated, but not a bounce event (for example a Mailgun `delivered` event). |
| `401 {"error":"Unauthorized"}` | Missing or wrong credentials, an unknown mailbox or an old URL. |
| `422` | *Other* only: invalid fields. Send `Accept: application/json` to get JSON errors. |

## Replay protection

SendGrid and Mailgun send a timestamp. NetSendo accepts a request only within 5 minutes of that timestamp, and each signature only once. It remembers seen signatures in the application cache.

- The window is set by `BOUNCE_WEBHOOK_TIMESTAMP_TOLERANCE` (seconds, default `300`).
- The server clock must be accurate. Keep NTP running on the host.

## Troubleshooting

Rejected requests appear in the application log:

```bash
docker compose exec app grep "Bounce webhook rejected" storage/logs/laravel.log
```

| `reason` | What to do |
|---|---|
| `legacy_url` | The provider posts to an old URL without a mailbox ID. Copy the new URL from the mailbox. |
| `unknown_mailbox` | The mailbox ID in the URL does not exist or the mailbox was deleted. |
| `not_configured` | No key or token is saved for this provider in that mailbox. |
| `missing_signature` | SendGrid: Signed Event Webhook is off. Mailgun: the request has no `signature` object. |
| `invalid_signature` | The key in NetSendo does not match the provider account or webhook, for example after SendGrid generated a new key. It can also mean a proxy changed the request body. |
| `missing_token` | Postmark or *Other*: no Basic auth, header or `?token=`. |
| `invalid_token` | The token is wrong or has been replaced. |
| `stale_timestamp` | The request is older than the tolerance, or the server clock is off. |
| `replayed` | A signature was sent a second time. |
