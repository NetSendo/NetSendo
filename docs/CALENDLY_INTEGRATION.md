# Calendly Integration

This document describes how to set up and use the Calendly integration in NetSendo.

## Overview

The Calendly integration allows you to:

- **Sync booking events** from Calendly to NetSendo
- **Automatically create CRM contacts** when someone books a meeting
- **Add invitees to mailing lists** with optional tags
- **Create CRM tasks** for follow-up actions

## Setup Instructions

### Step 1: Create a Calendly OAuth App

1. Go to [Calendly Integrations](https://calendly.com/integrations/api_webhooks)
2. Click **"Create OAuth App"**
3. Fill in the required details:
   - **App Name:** NetSendo (or your preferred name)
   - **Redirect URI:** `https://your-domain.com/settings/calendly/callback`
4. Click **"Create"**
5. On the confirmation screen, copy:
   - **Client ID**
   - **Client Secret**

> **Note:** The Client Secret is only shown once. Save it securely!

### Step 2: Connect in NetSendo

1. Go to **Settings → Calendly** in NetSendo
2. Click **"Connect with Calendly"**
3. Enter your:
   - **Client ID** (from Step 1)
   - **Client Secret** (from Step 1)
4. Click **"Connect"**
5. You will be redirected to Calendly to authorize the connection
6. After authorization, you'll be redirected back to NetSendo

### Step 3: Configure Integration Settings

After connecting, configure how bookings should be handled:

#### CRM Integration

- **Create CRM contacts:** Automatically create contacts for new invitees
- **Default status:** Set the initial status for new contacts (e.g., "Lead", "New")
- **Create tasks:** Automatically create follow-up tasks for bookings
- **Default owner:** Assign tasks/contacts to a specific team member

#### Mailing List Integration

- **Add to lists:** Automatically add invitees to selected mailing lists
- **Apply tags:** Add specific tags to new subscribers

#### Event Type Mappings

- Configure different settings for each Calendly event type
- Map specific event types to specific lists and tags

## How It Works

### Webhook Flow

```
Calendly Booking → Webhook → NetSendo → CRM Contact + Mailing List + Task
```

1. User books a meeting via Calendly
2. Calendly sends a webhook notification to NetSendo
3. NetSendo processes the booking:
   - Creates/updates CRM contact (if enabled)
   - Adds subscriber to mailing lists (if enabled)
   - Creates CRM task (if enabled)

### Supported Events

| Event              | Description               |
| ------------------ | ------------------------- |
| `invitee.created`  | New booking created       |
| `invitee.canceled` | Booking canceled          |
| `invitee.no_show`  | Invitee marked as no-show |

## Security

### Credential Storage

- All API credentials (Client ID, Client Secret) are **encrypted at rest** using Laravel's encryption
- OAuth tokens are automatically refreshed when they expire

### Webhook Verification

- Webhooks are automatically created with a signing key
- All incoming webhooks are verified using HMAC-SHA256
- Webhooks with invalid signatures are rejected

## Troubleshooting

### Common Issues

#### "405 Method Not Allowed" Error

- **Cause:** Route cache is stale
- **Solution:** Run `php artisan route:clear && php artisan route:cache`

#### "Table already exists" Error

- **Cause:** Migration running on a database with existing tables
- **Solution:** This is now automatically handled. Run `php artisan migrate` again.

#### "Integration not found" in Webhook Logs

- **Cause:** Webhook received before integration was fully configured
- **Solution:** Ensure the integration has `calendly_user_uri` set correctly

#### Token Expired

- **Cause:** OAuth tokens expire periodically
- **Solution:** Tokens are automatically refreshed. If issues persist, disconnect and reconnect.

### Debugging

Check logs for webhook processing:

```bash
tail -f storage/logs/laravel.log | grep Calendly
```

## API Reference

### Webhook Endpoint

```
POST /api/webhooks/calendly
```

### OAuth Callback

```
GET /settings/calendly/callback
```

### Settings Endpoints

```
GET  /settings/calendly           # View integration settings
POST /settings/calendly/connect   # Start OAuth flow
POST /settings/calendly/{id}/disconnect   # Remove integration
PUT  /settings/calendly/{id}/settings     # Update settings
```

## Event Type Mappings

You can configure different behaviors for each Calendly event type:

```json
{
  "event_type_mappings": {
    "https://api.calendly.com/event_types/abc123": {
      "list_ids": [1, 2],
      "tag_ids": [5, 6],
      "crm_status": "hot_lead"
    }
  }
}
```

## Requirements

- Laravel 10+
- PHP 8.2+
- Valid Calendly OAuth App
- HTTPS (required for OAuth redirect and webhooks)
