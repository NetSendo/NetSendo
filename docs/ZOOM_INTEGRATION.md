# Zoom Integration

This document describes the Zoom video conferencing integration in NetSendo, providing technical details for developers and AI agents building or maintaining this feature.

## Overview

The Zoom integration enables users to:

- **Create Zoom video meetings** directly from CRM tasks
- **Auto-generate meeting links** with host and join URLs
- **Invite attendees** via email from task contacts
- **Sync meeting details** (topic, agenda, duration) from task data

## Architecture

### Backend Components

```
src/app/
├── Http/Controllers/
│   └── ZoomController.php          # OAuth flow & settings management
├── Models/
│   └── UserZoomConnection.php      # Stores OAuth tokens per user
├── Services/
│   ├── ZoomOAuthService.php        # OAuth authorization & token refresh
│   └── ZoomMeetingService.php      # CRUD operations on Zoom meetings
```

### Frontend Components

```
src/resources/js/Pages/
├── Marketplace/
│   └── Zoom.vue                    # Integration info page in Marketplace
├── Settings/
│   └── Zoom/
│       └── Index.vue               # Configuration & account connection UI
```

### Database Schema

```
src/database/migrations/
├── 2026_01_26_090000_add_zoom_integration.php
└── 2026_01_27_133700_add_granted_scopes_to_user_zoom_connections.php
```

---

## Database Tables

### `user_zoom_connections`

Stores OAuth connections between NetSendo users and their Zoom accounts.

| Column             | Type      | Description                                  |
| ------------------ | --------- | -------------------------------------------- |
| `id`               | bigint    | Primary key                                  |
| `user_id`          | foreignId | References `users.id` (cascade on delete)    |
| `zoom_user_id`     | string    | Zoom user ID from API                        |
| `zoom_email`       | string    | Zoom account email                           |
| `granted_scopes`   | text      | Space-separated list of OAuth scopes granted |
| `access_token`     | text      | Encrypted OAuth access token                 |
| `refresh_token`    | text      | Encrypted OAuth refresh token                |
| `token_expires_at` | timestamp | Token expiration timestamp                   |
| `is_active`        | boolean   | Connection active status                     |
| `created_at`       | timestamp | Record creation timestamp                    |
| `updated_at`       | timestamp | Record update timestamp                      |

### CRM Tasks Zoom Fields (added to `crm_tasks`)

| Column                 | Type        | Description                             |
| ---------------------- | ----------- | --------------------------------------- |
| `zoom_meeting_id`      | string      | Zoom meeting ID                         |
| `zoom_meeting_link`    | string(500) | Start URL for host                      |
| `zoom_join_url`        | string(500) | Join URL for guests                     |
| `include_zoom_meeting` | boolean     | Whether to create Zoom meeting for task |

---

## API Credentials Storage

Zoom OAuth credentials are stored in the `settings` table (not environment variables):

- `zoom_client_id` - Zoom OAuth Client ID
- `zoom_client_secret` - Zoom OAuth Client Secret

The `ZoomOAuthService` retrieves these dynamically:

```php
private function getClientId(): ?string
{
    return \App\Models\Setting::where('key', 'zoom_client_id')->value('value')
        ?: config('services.zoom.client_id');
}
```

> **Note:** Database settings take priority over config/environment variables.

---

## OAuth Flow

### Required Scopes (Granular Format - Zoom 2024+)

```php
private const ZOOM_SCOPES = [
    'user:read:user:admin',            // View user info after auth
    'meeting:write:meeting:admin',     // Create meetings
    'meeting:read:meeting:admin',      // View meeting details
    'meeting:update:meeting:admin',    // Update meetings
    'meeting:delete:meeting:admin',    // Delete meetings
];
```

### OAuth URLs

| Endpoint         | URL                               |
| ---------------- | --------------------------------- |
| Authorization    | `https://zoom.us/oauth/authorize` |
| Token Exchange   | `https://zoom.us/oauth/token`     |
| User Info        | `https://api.zoom.us/v2/users/me` |
| Token Revocation | `https://zoom.us/oauth/revoke`    |

### Flow Steps

1. **User clicks "Connect"** → Redirects to Zoom authorization URL
2. **User authorizes** → Redirects back to `/settings/zoom/callback`
3. **Callback exchanges code** for access + refresh tokens
4. **Tokens are encrypted** and stored in `user_zoom_connections`
5. **User info is fetched** to store `zoom_email` and `zoom_user_id`

---

## Routes

All routes are defined in `src/routes/web.php`:

```php
// Marketplace info page
Route::get('/marketplace/zoom', fn() => Inertia::render('Marketplace/Zoom'))
    ->name('marketplace.zoom');

// Settings routes
Route::prefix('settings/zoom')->name('settings.zoom.')->group(function () {
    Route::get('/', [ZoomController::class, 'index'])->name('index');
    Route::post('/save', [ZoomController::class, 'save'])->name('save');
    Route::get('/connect', [ZoomController::class, 'connect'])->name('connect');
    Route::get('/callback', [ZoomController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [ZoomController::class, 'disconnect'])->name('disconnect');
    Route::get('/status', [ZoomController::class, 'status'])->name('status');
});
```

---

## Meeting Creation

The `ZoomMeetingService` creates meetings from CRM tasks:

### Create Meeting Payload

```php
$payload = [
    'topic' => $task->title,
    'type' => 2,  // Scheduled meeting
    'timezone' => $userTimezone,
    'start_time' => $task->due_date->toIso8601String(),
    'duration' => 60,  // Minutes (30-1440)
    'agenda' => $meetingAgenda,
    'settings' => [
        'host_video' => true,
        'participant_video' => true,
        'join_before_host' => true,
        'mute_upon_entry' => false,
        'waiting_room' => false,
        'audio' => 'both',
        'auto_recording' => 'none',
        'meeting_invitees' => [
            ['email' => 'contact@example.com'],
        ],
    ],
];
```

### Meeting Agenda Format

```
{Task Description}

Notes:
{Task Notes}

---
Type: {Meeting/Call/etc}
Priority: {High/Medium/Low}
Contact: {Contact Name}
Deal: {Deal Name}

Managed by NetSendo CRM
```

---

## Token Management

### Encrypted Storage

Tokens are encrypted using Laravel's `Crypt` facade:

```php
'access_token' => Crypt::encryptString($tokens['access_token']),
'refresh_token' => Crypt::encryptString($tokens['refresh_token']),
```

### Token Refresh

The service automatically refreshes expired tokens:

```php
public function getValidAccessToken(UserZoomConnection $connection): string
{
    if ($connection->isTokenExpired()) {
        $tokens = $this->refreshAccessToken($connection);
        return $tokens['access_token'];
    }
    return $connection->getDecryptedAccessToken();
}
```

Tokens are considered expired 5 minutes before actual expiration.

---

## Security

### Credential Storage

- OAuth tokens are encrypted at rest using Laravel encryption
- API credentials are stored in database (not in code or env)
- Client Secret is masked in UI (`••••••••••••`)

### OAuth State

- OAuth state includes CSRF token and user ID
- State is base64-encoded JSON

---

## Frontend Components

### Marketplace Page (`Marketplace/Zoom.vue`)

Displays:

- Integration features list
- Setup steps guide
- OAuth App creation instructions
- Required scopes documentation
- Quick action buttons

### Settings Page (`Settings/Zoom/Index.vue`)

Provides:

- API credentials form (Client ID, Client Secret)
- Redirect URI (with copy button)
- Connect/Disconnect buttons
- Connected account display
- Granted scopes visualization

---

## Extending the Integration

### Adding New Features

1. **Add new scopes** in `ZoomOAuthService::ZOOM_SCOPES`
2. **Create API methods** in `ZoomMeetingService`
3. **Update frontend** to expose new functionality
4. **Test OAuth reconnection** (scopes change requires re-auth)

### Adding Webhooks (Future)

If webhooks are needed:

1. Create webhook controller in `App\Http\Controllers\Webhooks\ZoomController`
2. Add route as `POST /api/webhooks/zoom`
3. Implement HMAC-SHA256 signature verification
4. Create webhook subscription via Zoom API

---

## Troubleshooting

### Common Issues

| Issue                       | Cause                               | Solution                       |
| --------------------------- | ----------------------------------- | ------------------------------ |
| "Zoom is not configured"    | Missing Client ID/Secret            | Add credentials in Settings    |
| "Failed to exchange code"   | Invalid credentials or redirect URI | Verify Zoom OAuth App settings |
| Token refresh failing       | Expired refresh token               | User must reconnect account    |
| Missing meeting permissions | Insufficient scopes                 | Reconnect with updated scopes  |

### Debug Logging

```bash
tail -f storage/logs/laravel.log | grep Zoom
```

---

## Requirements

- PHP 8.2+
- Laravel 10+
- HTTPS (required for OAuth)
- Zoom Account (Pro/Business recommended)
- Zoom OAuth App (General App type)

---

## Related Files

- [ZoomController.php](file:///src/app/Http/Controllers/ZoomController.php)
- [ZoomOAuthService.php](file:///src/app/Services/ZoomOAuthService.php)
- [ZoomMeetingService.php](file:///src/app/Services/ZoomMeetingService.php)
- [UserZoomConnection.php](file:///src/app/Models/UserZoomConnection.php)
- [Settings/Zoom/Index.vue](file:///src/resources/js/Pages/Settings/Zoom/Index.vue)
- [Marketplace/Zoom.vue](file:///src/resources/js/Pages/Marketplace/Zoom.vue)
