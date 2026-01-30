# Google Meet Integration

This document describes the Google Meet integration in NetSendo, providing technical details for developers and AI agents building or maintaining this feature.

## Overview

The Google Meet integration enables users to:

- **Create Google Meet video conferences** directly from CRM meeting tasks
- **Auto-generate meeting links** when syncing tasks to Google Calendar
- **Invite attendees** via email with calendar invitations
- **Track attendee responses** (accepted, declined, needs action)
- **Auto-add CRM contacts** as meeting attendees

> **Important:** Google Meet is built on top of the Google Calendar integration. It uses the Google Calendar API's `conferenceData` feature to create Meet links. **Google Calendar must be connected first.**

## Architecture

### How It Works

Google Meet is **not a separate OAuth integration** – it uses the existing Google Calendar connection:

```
CRM Task (include_google_meet = true)
    ↓
SyncTaskToCalendar job
    ↓
GoogleCalendarService.createEventFromTask()
    ↓
Calendar API with conferenceData payload
    ↓
Google creates Meet link automatically
    ↓
Meet link saved back to CRM task
```

### Backend Components

```
src/app/
├── Services/
│   └── GoogleCalendarService.php       # Creates Meet via conferenceData
├── Models/
│   └── CrmTask.php                     # Stores Meet link & attendees data
├── Http/Controllers/
│   └── CrmTaskController.php           # Handles task with Meet fields
├── Jobs/
│   └── SyncTaskToCalendar.php          # Syncs task with Meet to Calendar
```

### Frontend Components

```
src/resources/js/
├── Pages/
│   └── Marketplace/
│       └── GoogleMeet.vue              # Integration info page
├── Components/
│   └── Crm/
│       └── TaskModal.vue               # Meet toggle & attendee management
│   └── MeetingReminderNotification.vue # Meeting reminder with Meet link
├── Composables/
│   └── useMeetingReminders.js          # Handles "Join Meeting" action
```

### Database Schema

```
src/database/migrations/
└── 2026_01_26_000001_add_google_meet_fields_to_crm_tasks.php
```

---

## Database Fields (in `crm_tasks`)

| Column                | Type    | Description                                     |
| --------------------- | ------- | ----------------------------------------------- |
| `google_meet_link`    | string  | The Meet video conference URL (meet.google.com) |
| `google_meet_id`      | string  | The Meet conference ID from Google              |
| `include_google_meet` | boolean | Whether to create a Meet link for this task     |
| `attendee_emails`     | json    | Array of email addresses to invite              |
| `attendees_data`      | json    | Attendee responses with status                  |

### Attendees Data Structure

```json
[
  {
    "email": "client@example.com",
    "displayName": "John Doe",
    "status": "accepted"
  },
  {
    "email": "team@company.com",
    "status": "needsAction"
  }
]
```

Possible status values:

- `needsAction` - Hasn't responded yet
- `accepted` - Confirmed attendance
- `declined` - Declined the invitation
- `tentative` - Maybe attending

---

## API Implementation

### Creating Meet via Calendar API

When a task has `include_google_meet = true`, the `GoogleCalendarService` adds `conferenceData` to the event payload:

```php
// In GoogleCalendarService::taskToEventPayload()

if ($task->include_google_meet) {
    $payload['conferenceData'] = [
        'createRequest' => [
            'requestId' => 'netsendo-meet-' . $task->id . '-' . time(),
            'conferenceSolutionKey' => [
                'type' => 'hangoutsMeet',
            ],
        ],
    ];
}
```

### Query Parameter for Meet Creation

The API request must include `conferenceDataVersion=1`:

```php
if ($task->include_google_meet) {
    $queryParams['conferenceDataVersion'] = 1;
}

$url = self::CALENDAR_API_URL . "/calendars/{$calendarId}/events";
if (!empty($queryParams)) {
    $url .= '?' . http_build_query($queryParams);
}
```

### Extracting Meet Link from Response

After creating the event, the Meet link is extracted from the response:

```php
private function saveMeetLinkFromEvent(CrmTask $task, array $event): void
{
    if (!isset($event['conferenceData']['entryPoints'])) {
        return;
    }

    $videoEntryPoint = collect($event['conferenceData']['entryPoints'])
        ->firstWhere('entryPointType', 'video');

    if ($videoEntryPoint && isset($videoEntryPoint['uri'])) {
        $task->update([
            'google_meet_link' => $videoEntryPoint['uri'],
            'google_meet_id' => $event['conferenceData']['conferenceId'] ?? null,
        ]);
    }
}
```

### Adding Attendees

Attendees are built from the CRM contact and manual email list:

```php
private function buildAttendeesList(CrmTask $task): array
{
    $attendees = [];

    // Add contact email if available
    if ($task->contact && $task->contact->email) {
        $attendees[] = [
            'email' => $task->contact->email,
            'displayName' => $task->contact->full_name,
            'responseStatus' => 'needsAction',
        ];
    }

    // Add manual attendee emails
    foreach ($task->attendee_emails as $email) {
        $attendees[] = [
            'email' => $email,
            'responseStatus' => 'needsAction',
        ];
    }

    return $attendees;
}
```

### Sending Invitations

When attendees are added, Google Calendar automatically sends email invitations:

```php
if ($this->hasAttendees($task)) {
    $queryParams['sendUpdates'] = 'all'; // Sends email to all attendees
}
```

---

## Routes

```php
// Marketplace info page
Route::get('/marketplace/google-meet', fn() => Inertia::render('Marketplace/GoogleMeet'))
    ->name('marketplace.google-meet');
```

No additional routes are needed – Meet functionality is embedded in the existing task creation/update flows.

---

## Frontend Implementation

### TaskModal.vue - Meet Section

The Meet section appears when task type is `meeting`:

```vue
<div v-if="form.type === 'meeting'" class="rounded-xl border p-4">
    <!-- Disabled if Calendar not connected or sync not enabled -->
    <div v-if="!calendarConnection || !form.sync_to_calendar" class="overlay">
        <p>{{ !calendarConnection ? 'Connect Google Calendar first' : 'Enable calendar sync first' }}</p>
    </div>

<!-- Meet Toggle -->
<input type="checkbox" v-model="form.include_google_meet" />
<span>Add Google Meet link</span>

<!-- Attendee Emails -->
<div v-if="form.include_google_meet">
        <!-- Email tags with response status -->
        <span v-for="email in form.attendee_emails">
            <span>{{ getAttendeeStatusIcon(status) }}</span>
            {{ email }}
        </span>
        
        <!-- Add email input -->
        <input type="email" v-model="newAttendeeEmail" />
        <button @click="addAttendeeEmail">Add Guest</button>
    </div>

<!-- Show Meet Link if exists -->
<div v-if="task?.google_meet_link">
        <a :href="task.google_meet_link" target="_blank">Join Meeting</a>
    </div>
```

### Attendee Status Icons

```javascript
function getAttendeeStatusIcon(status) {
  switch (status) {
    case "accepted":
      return "✅";
    case "declined":
      return "❌";
    case "tentative":
      return "❓";
    default:
      return "⏳"; // needsAction
  }
}
```

### Mutual Exclusion with Zoom

When Google Meet is enabled, Zoom is automatically disabled (and vice versa):

```vue
<input
  type="checkbox"
  v-model="form.include_zoom_meeting"
  @change="form.include_zoom_meeting && (form.include_google_meet = false)"
/>
```

---

## Flow: Creating a Meeting with Google Meet

### Prerequisites

1. Google Calendar integration is connected
2. User creates a task with type "meeting"

### Steps

1. User enables "Sync to Google Calendar" toggle
2. User enables "Add Google Meet link" toggle
3. (Optional) User adds attendee emails
4. User saves the task
5. `SyncTaskToCalendar` job is dispatched
6. `GoogleCalendarService.createEventFromTask()` is called with `conferenceData`
7. Google Calendar API creates event + Meet conference
8. Response contains `conferenceData.entryPoints[0].uri` (the Meet link)
9. Meet link is saved to `CrmTask.google_meet_link`
10. Attendees receive calendar invitations via email

---

## Security Considerations

### Scopes Required

Google Meet creation requires the full calendar scope (already included in Calendar integration):

```php
'https://www.googleapis.com/auth/calendar'       // Full calendar access
'https://www.googleapis.com/auth/calendar.events' // Event management
```

### No Separate OAuth

- Google Meet does **not** require a separate OAuth flow
- It leverages the existing Google Calendar connection
- User's Google Workspace or personal Gmail determines Meet features

---

## Integration with Zoom

A task can have **either** Google Meet **or** Zoom, but not both simultaneously:

| Feature           | Google Meet               | Zoom                 |
| ----------------- | ------------------------- | -------------------- |
| Requires          | Google Calendar connected | Zoom OAuth connected |
| Created via       | Calendar API              | Zoom API             |
| Link stored in    | `google_meet_link`        | `zoom_join_url`      |
| Attendees invited | Via Calendar event        | Via Zoom meeting     |

The frontend enforces mutual exclusion – enabling one automatically disables the other.

---

## Troubleshooting

### Common Issues

| Issue                        | Cause                           | Solution                       |
| ---------------------------- | ------------------------------- | ------------------------------ |
| "Connect Google Calendar"    | Calendar not connected          | Set up Calendar integration    |
| "Enable calendar sync first" | `sync_to_calendar` is false     | Enable the toggle              |
| No Meet link after save      | `conferenceDataVersion` missing | Check service implementation   |
| Attendees not receiving mail | `sendUpdates` not set           | Verify API request params      |
| Meet link not showing        | Response parsing failed         | Check `entryPoints` extraction |

### Debug Logging

```bash
# Search for Meet-related logs
tail -f storage/logs/laravel.log | grep -E "(Meet|conference)"

# Check specific task sync
grep "task_id.:.$TASK_ID" storage/logs/laravel.log
```

---

## Requirements

- PHP 8.2+
- Laravel 10+
- **Google Calendar integration connected** (prerequisite)
- Google Account (personal Gmail or Google Workspace)
- Google Workspace may have enhanced Meet features

---

## Extending the Integration

### Adding More Conference Types

Google Calendar API supports other conference types:

```php
$conferenceSolutionKey = [
    'type' => 'hangoutsMeet',  // Current
    // Other options: 'eventHangout', 'addOn' (for third-party)
];
```

### Adding Phone Dial-In

For Workspace users, dial-in info is available in `entryPoints`:

```php
$phoneEntryPoint = collect($event['conferenceData']['entryPoints'])
    ->firstWhere('entryPointType', 'phone');

if ($phoneEntryPoint) {
    $dialIn = $phoneEntryPoint['uri'];
    $pin = $phoneEntryPoint['pin'] ?? null;
}
```

---

## Related Files

- [GoogleCalendarService.php](file:///src/app/Services/GoogleCalendarService.php) - Meet creation logic (lines 31, 96, 441, 578-598)
- [CrmTask.php](file:///src/app/Models/CrmTask.php) - Model with Meet fields (lines 57-59, 88)
- [CrmTaskController.php](file:///src/app/Http/Controllers/CrmTaskController.php) - Task validation (lines 169, 263)
- [TaskModal.vue](file:///src/resources/js/Components/Crm/TaskModal.vue) - Meet UI (lines 1123-1341)
- [Marketplace/GoogleMeet.vue](file:///src/resources/js/Pages/Marketplace/GoogleMeet.vue) - Info page
- [Migration](file:///src/database/migrations/2026_01_26_000001_add_google_meet_fields_to_crm_tasks.php) - Database fields

---

## See Also

- [GOOGLE_CALENDAR_INTEGRATION.md](./GOOGLE_CALENDAR_INTEGRATION.md) - Parent integration
- [ZOOM_INTEGRATION.md](./ZOOM_INTEGRATION.md) - Alternative video conferencing
