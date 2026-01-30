# Google Calendar Integration

This document describes the Google Calendar integration in NetSendo, providing technical details for developers and AI agents building or maintaining this feature.

## Overview

The Google Calendar integration enables users to:

- **Sync CRM tasks to Google Calendar** as events (two-way)
- **Real-time synchronization** via Google push notifications (webhooks)
- **Create Google Meet meetings** automatically for tasks
- **Invite attendees** via calendar event invitations
- **Custom color coding** by task type in Google Calendar
- **Conflict detection** when events are modified in both systems

## Architecture

### Backend Components

```
src/app/
├── Http/Controllers/
│   ├── GoogleCalendarController.php        # OAuth flow & calendar settings
│   └── Webhooks/
│       └── GoogleCalendarController.php    # Push notification webhook handler
├── Models/
│   ├── GoogleIntegration.php               # Stores Google API credentials per user
│   └── UserCalendarConnection.php          # Stores OAuth tokens & sync settings
├── Services/
│   ├── GoogleCalendarOAuthService.php      # OAuth authorization & token refresh
│   └── GoogleCalendarService.php           # Calendar API operations (CRUD, watch)
├── Jobs/
│   ├── SyncTaskToCalendar.php              # Syncs task changes to Calendar
│   └── ProcessCalendarWebhook.php          # Processes incoming Calendar webhooks
├── Console/Commands/
│   ├── RefreshCalendarChannels.php         # Refreshes expiring webhook channels
│   ├── SyncOrphanedCalendarEvents.php      # Syncs orphaned events
│   └── SyncPendingCalendarTasks.php        # Syncs pending tasks
```

### Frontend Components

```
src/resources/js/Pages/
├── Marketplace/
│   └── GoogleCalendar.vue                  # Integration info page in Marketplace
├── Settings/
│   └── Calendar/
│       └── Index.vue                       # Configuration & account connection UI
```

### Database Schema

```
src/database/migrations/
├── 2025_12_18_125000_create_google_integrations_table.php
├── 2026_01_25_000006_add_google_calendar_sync_to_crm_tasks.php
├── 2026_01_25_000007_create_user_calendar_connections_table.php
├── 2026_01_25_000008_add_selected_calendar_to_crm_tasks.php
├── 2026_01_26_000001_add_google_meet_fields_to_crm_tasks.php
└── 2026_01_27_122845_add_task_type_colors_to_user_calendar_connections_table.php
```

---

## Database Tables

### `google_integrations`

Stores Google Cloud OAuth credentials per user (for self-hosted OAuth apps).

| Column          | Type      | Description                               |
| --------------- | --------- | ----------------------------------------- |
| `id`            | bigint    | Primary key                               |
| `user_id`       | foreignId | References `users.id` (cascade on delete) |
| `name`          | string    | Friendly name (e.g., "My Google App")     |
| `client_id`     | string    | Google OAuth Client ID                    |
| `client_secret` | string    | Google OAuth Client Secret                |
| `status`        | string    | `active` or `inactive`                    |
| `created_at`    | timestamp | Record creation timestamp                 |
| `updated_at`    | timestamp | Record update timestamp                   |

### `user_calendar_connections`

Stores OAuth connections and sync settings per user.

| Column                  | Type      | Description                             |
| ----------------------- | --------- | --------------------------------------- |
| `id`                    | bigint    | Primary key                             |
| `user_id`               | foreignId | References `users.id`                   |
| `google_integration_id` | foreignId | References `google_integrations.id`     |
| `access_token`          | text      | Encrypted OAuth access token            |
| `refresh_token`         | text      | Encrypted OAuth refresh token           |
| `token_expires_at`      | timestamp | Token expiration time                   |
| `calendar_id`           | string    | Target calendar ID (default: `primary`) |
| `connected_email`       | string    | Connected Google account email          |
| `channel_id`            | string    | Push notification channel ID            |
| `resource_id`           | string    | Google resource ID for webhook          |
| `channel_expires_at`    | timestamp | Push channel expiration (max 7 days)    |
| `is_active`             | boolean   | Connection active status                |
| `auto_sync_tasks`       | boolean   | Auto-sync enabled                       |
| `sync_settings`         | json      | Additional sync preferences             |
| `task_type_colors`      | json      | Custom colors per task type             |
| `sync_token`            | string    | Incremental sync token                  |
| `last_synced_at`        | timestamp | Last sync timestamp                     |

### CRM Tasks Calendar Fields (added to `crm_tasks`)

| Column                      | Type      | Description                          |
| --------------------------- | --------- | ------------------------------------ |
| `google_calendar_event_id`  | string    | Google Calendar event ID             |
| `google_calendar_id`        | string    | Calendar ID where event was created  |
| `google_calendar_synced_at` | timestamp | Last sync timestamp                  |
| `google_calendar_etag`      | string    | Event ETag for conflict detection    |
| `sync_to_calendar`          | boolean   | Whether task should sync to Calendar |
| `selected_calendar_id`      | string    | User-selected calendar for this task |
| `has_conflict`              | boolean   | Whether a sync conflict exists       |
| `conflict_data`             | json      | Local vs remote conflict data        |
| `google_meet_link`          | string    | Google Meet video call link          |
| `google_meet_id`            | string    | Google Meet conference ID            |
| `include_google_meet`       | boolean   | Whether to include Google Meet       |
| `attendee_emails`           | json      | List of attendee emails              |
| `attendees_data`            | json      | Attendees with response status       |

---

## API Credentials Storage

Google OAuth credentials are stored **per user** in the `google_integrations` table. This allows each user to configure their own Google Cloud Project OAuth credentials.

The `GoogleCalendarOAuthService` uses these credentials:

```php
public function getAuthorizationUrl(GoogleIntegration $integration, string $state): string
{
    $params = [
        'client_id' => $integration->client_id,
        'redirect_uri' => route('settings.calendar.callback'),
        // ...
    ];
}
```

---

## OAuth Flow

### Required Scopes

```php
private const SCOPES = [
    'https://www.googleapis.com/auth/calendar',        // Full calendar access
    'https://www.googleapis.com/auth/calendar.events', // Event management
    'https://www.googleapis.com/auth/userinfo.email',  // Get user email
    'openid',                                          // OpenID Connect
];
```

### OAuth URLs

| Endpoint       | URL                                             |
| -------------- | ----------------------------------------------- |
| Authorization  | `https://accounts.google.com/o/oauth2/v2/auth`  |
| Token Exchange | `https://oauth2.googleapis.com/token`           |
| User Info      | `https://www.googleapis.com/oauth2/v2/userinfo` |
| Revoke Token   | `https://oauth2.googleapis.com/revoke`          |

### Flow Steps

1. **User enters credentials** in Settings → Integrations
2. **User clicks "Connect"** → Redirects to Google authorization
3. **Google prompts consent** with calendar scopes
4. **User authorizes** → Redirects to `/settings/calendar/callback`
5. **Callback exchanges code** for access + refresh tokens
6. **Tokens are encrypted** and stored in `user_calendar_connections`
7. **Push notification channel** is automatically set up

---

## Routes

All routes are defined in `src/routes/web.php`:

```php
// Marketplace info page
Route::get('/marketplace/google-calendar', fn() => Inertia::render('Marketplace/GoogleCalendar'))
    ->name('marketplace.google-calendar');

// Calendar settings routes
Route::prefix('settings/calendar')->name('settings.calendar.')->group(function () {
    Route::get('/', [GoogleCalendarController::class, 'index'])->name('index');
    Route::get('/connect/{integration}', [GoogleCalendarController::class, 'connect'])->name('connect');
    Route::get('/callback', [GoogleCalendarController::class, 'callback'])->name('callback');
    Route::post('/disconnect/{connection}', [GoogleCalendarController::class, 'disconnect'])->name('disconnect');
    Route::put('/settings/{connection}', [GoogleCalendarController::class, 'updateSettings'])->name('settings');
    Route::post('/sync/{connection}', [GoogleCalendarController::class, 'syncNow'])->name('sync');
    Route::post('/bulk-sync/{connection}', [GoogleCalendarController::class, 'bulkSync'])->name('bulk-sync');
    Route::post('/refresh-channel/{connection}', [GoogleCalendarController::class, 'refreshChannel'])->name('refresh-channel');
    Route::get('/status', [GoogleCalendarController::class, 'syncStatus'])->name('status');
    Route::put('/task-colors/{connection}', [GoogleCalendarController::class, 'updateTaskColors'])->name('task-colors');
});

// Webhook (no auth required)
Route::post('/webhooks/google-calendar', [Webhooks\GoogleCalendarController::class, 'handle'])
    ->name('webhooks.google-calendar');
```

---

## Two-Way Synchronization

### Task → Calendar (Outbound)

When a CRM task is created/updated with `sync_to_calendar = true`:

1. `SyncTaskToCalendar` job is dispatched
2. Job creates/updates Google Calendar event
3. Event includes: title, description, time, color, reminders
4. If `include_google_meet = true`, Google Meet link is created
5. If attendees exist, calendar invitations are sent
6. Event ID and ETag are saved to task for future updates

### Calendar → Task (Inbound)

When a Google Calendar event changes:

1. Google sends push notification to `/webhooks/google-calendar`
2. `ProcessCalendarWebhook` job is dispatched
3. Job fetches recent events from Google Calendar API
4. For NetSendo events (via `extendedProperties.private.netsendo_task_id`):
   - Updates corresponding CRM task
   - Handles cancellations
5. For external events (if `import_external_events` enabled):
   - Creates new CRM tasks from calendar events

### Conflict Detection

When both local and remote changes occur:

1. Service compares ETags during update
2. If ETag mismatch (HTTP 412), conflict is detected
3. Task is marked with `has_conflict = true`
4. `conflict_data` stores both versions for user resolution

---

## Event Payload

When syncing a task to Google Calendar:

```php
$payload = [
    'summary' => $task->title,
    'description' => $this->buildEventDescription($task),
    'start' => [
        'dateTime' => $startTime->toRfc3339String(),
        'timeZone' => $userTimezone,
    ],
    'end' => [
        'dateTime' => $endTime->toRfc3339String(),
        'timeZone' => $userTimezone,
    ],
    'colorId' => '9',  // Mapped from task type color
    'reminders' => [...],
    'extendedProperties' => [
        'private' => [
            'netsendo_task_id' => $task->id,
            'netsendo_task_type' => $task->type,
        ],
    ],
    // Optional: Google Meet
    'conferenceData' => [
        'createRequest' => [
            'requestId' => 'netsendo-meet-' . $task->id,
            'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
        ],
    ],
    // Optional: Attendees
    'attendees' => [
        ['email' => 'contact@example.com', 'displayName' => 'John Doe'],
    ],
];
```

### Event Description Format

```
{Task Description}

📹 Zoom Meeting:
{Zoom Join URL}

📝 Notes:
{Task Notes}

---
Type: Meeting
Priority: High
Status: Pending
Contact: John Doe
Deal: Enterprise Deal

🔗 Managed by NetSendo CRM
```

---

## Task Type Colors

Custom colors can be set per task type:

| Task Type   | Default Hex | Google Color ID |
| ----------- | ----------- | --------------- |
| `call`      | `#8B5CF6`   | 3 (Grape)       |
| `email`     | `#3B82F6`   | 9 (Blueberry)   |
| `meeting`   | `#EF4444`   | 11 (Tomato)     |
| `task`      | `#10B981`   | 10 (Basil)      |
| `follow_up` | `#F59E0B`   | 5 (Banana)      |

Google Calendar has 11 predefined colors. NetSendo maps hex colors to the nearest Google color using RGB distance calculation.

---

## Push Notifications (Webhooks)

Google Calendar API uses push notifications for real-time sync.

### Channel Setup

```php
public function watchCalendar(UserCalendarConnection $connection): array
{
    $response = Http::withToken($accessToken)
        ->post(self::CALENDAR_API_URL . "/calendars/{$calendarId}/events/watch", [
            'id' => 'netsendo-calendar-' . $connection->id . '-' . time(),
            'type' => 'web_hook',
            'address' => route('webhooks.google-calendar'),
            'expiration' => now()->addDays(7)->timestamp * 1000,
        ]);
}
```

### Webhook Headers

| Header                  | Purpose                                |
| ----------------------- | -------------------------------------- |
| `X-Goog-Channel-ID`     | Identifies the subscription channel    |
| `X-Goog-Resource-ID`    | Identifies the watched resource        |
| `X-Goog-Resource-State` | Event type: `sync`, `exists`, `update` |
| `X-Goog-Message-Number` | Sequence number                        |

### Channel Expiration

- Maximum lifetime: **7 days**
- `RefreshCalendarChannels` command renews expiring channels
- Recommended: Schedule command to run hourly

---

## Token Management

### Encrypted Storage

Tokens are encrypted using Laravel's `Crypt` facade:

```php
public function setAccessTokenAttribute($value): void
{
    $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
}
```

### Token Refresh

Tokens are automatically refreshed when expired:

```php
public function getValidAccessToken(UserCalendarConnection $connection): string
{
    if ($connection->isTokenExpired()) {
        $tokens = $this->refreshAccessToken($connection);
        return $tokens['access_token'];
    }
    return $connection->getDecryptedAccessToken();
}
```

Tokens are considered expired **5 minutes** before actual expiration.

---

## Scheduled Commands

Add to `app/Console/Kernel.php`:

```php
$schedule->command('calendar:refresh-channels')->hourly();
$schedule->command('calendar:sync-orphaned')->everyThirtyMinutes();
$schedule->command('calendar:sync-pending')->everyFiveMinutes();
```

---

## Integration with Zoom

The Google Calendar integration works alongside Zoom:

1. CRM task can have both `include_google_meet` AND `include_zoom_meeting`
2. `SyncTaskToCalendar` job also creates Zoom meetings if enabled
3. Zoom join URL is added to Google Calendar event description and location
4. Both video conference links appear in the task and calendar event

---

## Security

### Credential Storage

- OAuth tokens are encrypted at rest using Laravel encryption
- API credentials stored in database per user
- Client Secret should never be exposed in frontend

### OAuth State

- State includes CSRF token and user ID
- State is base64-encoded JSON
- Verified on callback to prevent CSRF attacks

### Webhook Verification

- Webhooks validated via `channel_id` and `resource_id`
- Only known channels are processed
- Unknown channels are logged and rejected

---

## Frontend Components

### Marketplace Page (`Marketplace/GoogleCalendar.vue`)

Displays:

- Integration features (two-way sync, real-time, reminders)
- 5-step setup guide
- Links to Google Cloud Console
- Links to Calendar API documentation
- Requirements checklist

### Settings Page (`Settings/Calendar/Index.vue`)

Provides:

- Connected account display with email
- Calendar selector dropdown
- Auto-sync toggle
- Task type color customization
- Manual sync buttons
- Bulk sync for existing tasks
- Push notification status and refresh
- Disconnect button

---

## Extending the Integration

### Adding New Features

1. **Add new scopes** in `GoogleCalendarOAuthService::SCOPES`
2. **Create API methods** in `GoogleCalendarService`
3. **Update jobs** to handle new functionality
4. **Update frontend** to expose settings
5. **Test OAuth reconnection** (scopes change requires re-auth)

### Adding New Task Fields

1. Create migration for new `crm_tasks` columns
2. Update `taskToEventPayload()` in `GoogleCalendarService`
3. Update `eventToTaskData()` for inbound sync
4. Update frontend task forms

---

## Troubleshooting

### Common Issues

| Issue                          | Cause                                  | Solution                             |
| ------------------------------ | -------------------------------------- | ------------------------------------ |
| "Invalid state: user mismatch" | OAuth session expired                  | Start OAuth flow again               |
| "Failed to exchange code"      | Invalid credentials or redirect URI    | Verify Google Cloud Console settings |
| Token refresh failing          | Refresh token revoked                  | User must reconnect account          |
| Push notifications not working | Channel expired or invalid webhook URL | Refresh channel or check SSL         |
| Events not syncing             | `auto_sync_tasks` disabled             | Enable in calendar settings          |
| Conflict detected              | Event edited in both systems           | Resolve conflict in task view        |

### Debug Logging

```bash
# Main application logs
tail -f storage/logs/laravel.log | grep -E "(Calendar|Google)"

# Specific log files
tail -f storage/logs/calendar-channels.log
tail -f storage/logs/calendar-orphaned-sync.log
tail -f storage/logs/calendar-pending-sync.log
```

---

## Requirements

- PHP 8.2+
- Laravel 10+
- HTTPS (required for OAuth and webhooks)
- Google Account
- Google Cloud Project with Calendar API enabled
- OAuth consent screen configured
- OAuth 2.0 credentials (Web application type)

---

## Related Files

- [GoogleCalendarController.php](file:///src/app/Http/Controllers/GoogleCalendarController.php)
- [Webhooks/GoogleCalendarController.php](file:///src/app/Http/Controllers/Webhooks/GoogleCalendarController.php)
- [GoogleCalendarOAuthService.php](file:///src/app/Services/GoogleCalendarOAuthService.php)
- [GoogleCalendarService.php](file:///src/app/Services/GoogleCalendarService.php)
- [UserCalendarConnection.php](file:///src/app/Models/UserCalendarConnection.php)
- [GoogleIntegration.php](file:///src/app/Models/GoogleIntegration.php)
- [SyncTaskToCalendar.php](file:///src/app/Jobs/SyncTaskToCalendar.php)
- [ProcessCalendarWebhook.php](file:///src/app/Jobs/ProcessCalendarWebhook.php)
- [Settings/Calendar/Index.vue](file:///src/resources/js/Pages/Settings/Calendar/Index.vue)
- [Marketplace/GoogleCalendar.vue](file:///src/resources/js/Pages/Marketplace/GoogleCalendar.vue)
