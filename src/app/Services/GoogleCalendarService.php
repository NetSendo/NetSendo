<?php

namespace App\Services;

use App\Models\CrmTask;
use App\Models\UserCalendarConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GoogleCalendarService
{
    private const CALENDAR_API_URL = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private GoogleCalendarOAuthService $oauthService
    ) {}

    /**
     * Create a Google Calendar event from a CRM task.
     */
    public function createEventFromTask(CrmTask $task, UserCalendarConnection $connection, ?string $calendarId = null): ?array
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);
        $payload = $this->taskToEventPayload($task);
        $targetCalendarId = $calendarId ?? $connection->calendar_id;

        // Build query parameters for Meet and attendee notifications
        $queryParams = [];
        if ($task->include_google_meet) {
            $queryParams['conferenceDataVersion'] = 1;
        }
        if ($this->hasAttendees($task)) {
            $queryParams['sendUpdates'] = 'all'; // Send email invitations to attendees
        }

        $url = self::CALENDAR_API_URL . "/calendars/{$targetCalendarId}/events";
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Failed to create Google Calendar event', [
                'task_id' => $task->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $event = $response->json();

        // Extract and save Google Meet link if created
        $this->saveMeetLinkFromEvent($task, $event);

        // Save attendees data with status
        $this->saveAttendeesData($task, $event);

        Log::info('Created Google Calendar event', [
            'task_id' => $task->id,
            'event_id' => $event['id'] ?? null,
            'calendar_id' => $targetCalendarId,
            'has_meet' => isset($event['conferenceData']),
        ]);

        return $event;
    }

    /**
     * Update an existing Google Calendar event.
     */
    public function updateEvent(CrmTask $task, UserCalendarConnection $connection): ?array
    {
        if (!$task->google_calendar_event_id) {
            return $this->createEventFromTask($task, $connection);
        }

        $accessToken = $this->oauthService->getValidAccessToken($connection);
        $payload = $this->taskToEventPayload($task);
        $calendarId = $task->google_calendar_id ?? $connection->calendar_id;

        // Build request with optional If-Match header for conflict detection
        $request = Http::withToken($accessToken);

        if ($task->google_calendar_etag) {
            $request = $request->withHeaders([
                'If-Match' => $task->google_calendar_etag,
            ]);
        }

        // Build query parameters
        $queryParams = [];
        if ($task->include_google_meet) {
            $queryParams['conferenceDataVersion'] = 1;
        }
        if ($this->hasAttendees($task)) {
            $queryParams['sendUpdates'] = 'all'; // Send email invitations to new attendees
        }

        $url = self::CALENDAR_API_URL . "/calendars/{$calendarId}/events/{$task->google_calendar_event_id}";
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $response = $request->put($url, $payload);

        if (!$response->successful()) {
            // Conflict detected (etag mismatch)
            if ($response->status() === 412) {
                Log::warning('Calendar sync conflict detected', [
                    'task_id' => $task->id,
                    'event_id' => $task->google_calendar_event_id,
                ]);

                // Fetch current remote version
                $remoteEvent = $this->getEvent($task->google_calendar_event_id, $calendarId, $connection);
                if ($remoteEvent) {
                    $task->markConflict(
                        [
                            'title' => $task->title,
                            'description' => $task->description,
                            'due_date' => $task->due_date?->toISOString(),
                        ],
                        [
                            'title' => $remoteEvent['summary'] ?? null,
                            'description' => $remoteEvent['description'] ?? null,
                            'due_date' => $remoteEvent['start']['dateTime'] ?? $remoteEvent['start']['date'] ?? null,
                            'etag' => $remoteEvent['etag'] ?? null,
                        ]
                    );
                }

                return null;
            }

            // If event not found, try to create new one
            if ($response->status() === 404) {
                $task->clearCalendarSync();
                return $this->createEventFromTask($task, $connection);
            }

            Log::error('Failed to update Google Calendar event', [
                'task_id' => $task->id,
                'event_id' => $task->google_calendar_event_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $event = $response->json();

        // Update sync timestamp and etag
        $task->update([
            'google_calendar_synced_at' => now(),
            'google_calendar_etag' => $event['etag'] ?? null,
            'has_conflict' => false,
            'conflict_data' => null,
        ]);

        // Extract and save Google Meet link if created/updated
        $this->saveMeetLinkFromEvent($task, $event);

        // Save attendees data with status
        $this->saveAttendeesData($task, $event);

        Log::info('Updated Google Calendar event', [
            'task_id' => $task->id,
            'event_id' => $event['id'] ?? null,
        ]);

        return $event;
    }

    /**
     * Delete a Google Calendar event.
     */
    public function deleteEvent(string $eventId, string $calendarId, UserCalendarConnection $connection): bool
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $response = Http::withToken($accessToken)
            ->delete(self::CALENDAR_API_URL . "/calendars/{$calendarId}/events/{$eventId}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('Failed to delete Google Calendar event', [
                'event_id' => $eventId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('Deleted Google Calendar event', ['event_id' => $eventId]);
        return true;
    }

    /**
     * Get a specific event by ID.
     */
    public function getEvent(string $eventId, string $calendarId, UserCalendarConnection $connection): ?array
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $response = Http::withToken($accessToken)
            ->get(self::CALENDAR_API_URL . "/calendars/{$calendarId}/events/{$eventId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * List events in a time range (for sync).
     */
    public function listEvents(
        UserCalendarConnection $connection,
        Carbon $from,
        Carbon $to,
        ?string $syncToken = null
    ): array {
        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $params = [
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 250,
        ];

        if ($syncToken) {
            $params['syncToken'] = $syncToken;
        } else {
            $params['timeMin'] = $from->toRfc3339String();
            $params['timeMax'] = $to->toRfc3339String();
        }

        $response = Http::withToken($accessToken)
            ->get(self::CALENDAR_API_URL . "/calendars/{$connection->calendar_id}/events", $params);

        if (!$response->successful()) {
            Log::error('Failed to list Google Calendar events', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['items' => [], 'nextSyncToken' => null];
        }

        $data = $response->json();
        return [
            'items' => $data['items'] ?? [],
            'nextSyncToken' => $data['nextSyncToken'] ?? null,
            'nextPageToken' => $data['nextPageToken'] ?? null,
        ];
    }

    /**
     * Set up push notifications webhook for real-time sync.
     */
    public function watchCalendar(UserCalendarConnection $connection): array
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);
        $channelId = 'netsendo-calendar-' . $connection->id . '-' . time();

        // Channel expires in 7 days (maximum for Calendar API)
        $expiration = now()->addDays(7)->timestamp * 1000;

        $response = Http::withToken($accessToken)
            ->post(self::CALENDAR_API_URL . "/calendars/{$connection->calendar_id}/events/watch", [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => route('webhooks.google-calendar'),
                'expiration' => $expiration,
            ]);

        if (!$response->successful()) {
            Log::error('Failed to set up Calendar watch', [
                'connection_id' => $connection->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to set up Calendar push notifications');
        }

        $data = $response->json();

        $connection->updateChannel(
            $data['id'],
            $data['resourceId'],
            7 * 24 * 60 * 60 // 7 days in seconds
        );

        Log::info('Set up Calendar watch', [
            'connection_id' => $connection->id,
            'channel_id' => $data['id'],
        ]);

        return $data;
    }

    /**
     * Stop watching a calendar (unsubscribe from push notifications).
     */
    public function stopWatch(UserCalendarConnection $connection): bool
    {
        if (!$connection->channel_id || !$connection->resource_id) {
            return true;
        }

        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $response = Http::withToken($accessToken)
            ->post(self::CALENDAR_API_URL . '/channels/stop', [
                'id' => $connection->channel_id,
                'resourceId' => $connection->resource_id,
            ]);

        if (!$response->successful() && $response->status() !== 404) {
            Log::warning('Failed to stop Calendar watch', [
                'connection_id' => $connection->id,
                'status' => $response->status(),
            ]);
            // Don't throw, just clear the channel data
        }

        $connection->clearChannel();
        return true;
    }

    /**
     * Get list of user's calendars.
     */
    public function listCalendars(UserCalendarConnection $connection): array
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $response = Http::withToken($accessToken)
            ->get(self::CALENDAR_API_URL . '/users/me/calendarList');

        if (!$response->successful()) {
            Log::error('Failed to list calendars', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json()['items'] ?? [];
    }

    /**
     * Convert a CRM task to Google Calendar event payload.
     */
    private function taskToEventPayload(CrmTask $task): array
    {
        // Get user's timezone from their profile
        $userTimezone = $task->user?->timezone ?? config('app.timezone', 'UTC');

        $payload = [
            'summary' => $task->title,
            'description' => $this->buildEventDescription($task),
        ];

        // Add Zoom meeting link as location for quick join button
        if ($task->zoom_join_url) {
            $payload['location'] = $task->zoom_join_url;
        }

        // Set event timing
        if ($task->due_date) {
            // For tasks with specific time, use dateTime
            // For all-day tasks, use date
            if ($task->due_date->format('H:i') !== '00:00') {
                // Convert from UTC (storage) to user's timezone for Google Calendar
                $startInUserTz = $task->due_date->copy()->setTimezone($userTimezone);
                $payload['start'] = [
                    'dateTime' => $startInUserTz->toRfc3339String(),
                    'timeZone' => $userTimezone,
                ];
                // Use end_date if available, otherwise default to 1 hour duration
                $endDateTime = $task->end_date ?? $task->due_date->copy()->addHour();
                $endInUserTz = $endDateTime->copy()->setTimezone($userTimezone);
                $payload['end'] = [
                    'dateTime' => $endInUserTz->toRfc3339String(),
                    'timeZone' => $userTimezone,
                ];
            } else {
                // All-day event - convert to user's timezone for correct date
                $dateInUserTz = $task->due_date->copy()->setTimezone($userTimezone);
                $payload['start'] = ['date' => $dateInUserTz->format('Y-m-d')];
                $payload['end'] = ['date' => $dateInUserTz->format('Y-m-d')];
            }
        } else {
            // No due date - set for today as all-day (in user's timezone)
            $todayInUserTz = now()->setTimezone($userTimezone);
            $payload['start'] = ['date' => $todayInUserTz->format('Y-m-d')];
            $payload['end'] = ['date' => $todayInUserTz->format('Y-m-d')];
        }

        // Add color based on task type (using user's custom colors if available)
        $taskTypeColors = $this->getTaskTypeColorsForUser($task->user_id);
        $hexColor = $taskTypeColors[$task->type] ?? UserCalendarConnection::DEFAULT_TASK_TYPE_COLORS[$task->type] ?? '#6B7280';
        $payload['colorId'] = $this->hexToGoogleColorId($hexColor);

        // Add reminders
        if ($task->reminder_at) {
            $minutesBefore = max(0, $task->due_date->diffInMinutes($task->reminder_at));
            $payload['reminders'] = [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => $minutesBefore],
                ],
            ];
        } else {
            $payload['reminders'] = [
                'useDefault' => true,
            ];
        }

        // Extended properties for linking back to CRM
        $payload['extendedProperties'] = [
            'private' => [
                'netsendo_task_id' => (string) $task->id,
                'netsendo_task_type' => $task->type,
            ],
        ];

        // Add recurrence if task is recurring
        if ($task->is_recurring) {
            $rrule = $task->buildRecurrenceRule();
            if ($rrule) {
                $payload['recurrence'] = [$rrule];
            }
        }

        // Add Google Meet conference if requested
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

        // Add attendees (guests) from contact or manual list
        $attendees = $this->buildAttendeesList($task);
        if (!empty($attendees)) {
            $payload['attendees'] = $attendees;
            $payload['guestsCanModify'] = false;
            $payload['guestsCanInviteOthers'] = false;
            $payload['guestsCanSeeOtherGuests'] = true;
        }

        return $payload;
    }

    /**
     * Build event description from task.
     */
    private function buildEventDescription(CrmTask $task): string
    {
        $lines = [];

        if ($task->description) {
            $lines[] = $task->description;
            $lines[] = '';
        }

        // Add Zoom meeting link if available
        if ($task->zoom_join_url) {
            $lines[] = '📹 Zoom Meeting:';
            $lines[] = $task->zoom_join_url;
            $lines[] = '';
        }

        if ($task->notes) {
            $lines[] = '📝 Notes:';
            $lines[] = $task->notes;
            $lines[] = '';
        }

        // Add task metadata
        $lines[] = '---';
        $lines[] = "Type: " . ucfirst($task->type);
        $lines[] = "Priority: " . ucfirst($task->priority);
        $lines[] = "Status: " . ucfirst(str_replace('_', ' ', $task->status));

        if ($task->contact) {
            $lines[] = "Contact: " . $task->contact->full_name;
        }

        if ($task->deal) {
            $lines[] = "Deal: " . $task->deal->name;
        }

        $lines[] = '';
        $lines[] = '🔗 Managed by NetSendo CRM';

        return implode("\n", $lines);
    }

    /**
     * Convert a Google Calendar event to CRM task data.
     */
    public function eventToTaskData(array $event): array
    {
        $data = [
            'title' => $event['summary'] ?? 'Untitled Event',
            'description' => $this->extractDescriptionFromEvent($event),
        ];

        // Parse start time
        if (isset($event['start'])) {
            if (isset($event['start']['dateTime'])) {
                $data['due_date'] = Carbon::parse($event['start']['dateTime']);
            } elseif (isset($event['start']['date'])) {
                $data['due_date'] = Carbon::parse($event['start']['date'])->startOfDay();
            }
        }

        // Map status
        if (isset($event['status'])) {
            $data['status'] = match($event['status']) {
                'cancelled' => 'cancelled',
                'confirmed' => 'pending',
                'tentative' => 'pending',
                default => 'pending',
            };
        }

        return $data;
    }

    /**
     * Extract description without NetSendo metadata.
     */
    private function extractDescriptionFromEvent(array $event): ?string
    {
        $description = $event['description'] ?? '';

        // Remove the NetSendo metadata section
        $parts = explode('---', $description, 2);
        $cleanDescription = trim($parts[0] ?? '');

        // Remove notes section marker
        $cleanDescription = preg_replace('/📝 Notes:\s*/i', '', $cleanDescription);

        return $cleanDescription ?: null;
    }

    /**
     * Check if an event was created by NetSendo.
     */
    public function isNetSendoEvent(array $event): bool
    {
        return isset($event['extendedProperties']['private']['netsendo_task_id']);
    }

    /**
     * Get the CRM task ID from an event.
     */
    public function getTaskIdFromEvent(array $event): ?int
    {
        $taskId = $event['extendedProperties']['private']['netsendo_task_id'] ?? null;
        return $taskId ? (int) $taskId : null;
    }

    /**
     * Save Google Meet link from event response to task.
     */
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

            Log::info('Saved Google Meet link to task', [
                'task_id' => $task->id,
                'meet_link' => $videoEntryPoint['uri'],
            ]);
        }
    }

    /**
     * Build attendees list from task contact and manual attendee emails.
     */
    private function buildAttendeesList(CrmTask $task): array
    {
        $attendees = [];
        $emails = [];

        // Add contact email if available
        if ($task->contact && $task->contact->email && !empty($task->contact->email)) {
            $attendees[] = [
                'email' => $task->contact->email,
                'displayName' => $task->contact->full_name ?? $task->contact->email,
                'responseStatus' => 'needsAction',
            ];
            $emails[] = strtolower($task->contact->email);
        }

        // Add manual attendee emails (avoiding duplicates)
        if (!empty($task->attendee_emails) && is_array($task->attendee_emails)) {
            foreach ($task->attendee_emails as $email) {
                $email = trim($email);
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $normalizedEmail = strtolower($email);
                    if (!in_array($normalizedEmail, $emails)) {
                        $attendees[] = [
                            'email' => $email,
                            'responseStatus' => 'needsAction',
                        ];
                        $emails[] = $normalizedEmail;
                    }
                }
            }
        }

        return $attendees;
    }

    /**
     * Check if task has any attendees.
     */
    private function hasAttendees(CrmTask $task): bool
    {
        if ($task->contact && $task->contact->email && !empty($task->contact->email)) {
            return true;
        }

        return !empty($task->attendee_emails) && is_array($task->attendee_emails) && count($task->attendee_emails) > 0;
    }

    /**
     * Save attendees data from event response.
     */
    private function saveAttendeesData(CrmTask $task, array $event): void
    {
        if (isset($event['attendees'])) {
            $attendeesData = collect($event['attendees'])->map(function ($attendee) {
                return [
                    'email' => $attendee['email'] ?? '',
                    'status' => $attendee['responseStatus'] ?? 'needsAction',
                    'displayName' => $attendee['displayName'] ?? null,
                ];
            })->toArray();

            $task->update(['attendees_data' => $attendeesData]);
        }
    }

    /**
     * Get task type colors for a user.
     */
    private function getTaskTypeColorsForUser(int $userId): array
    {
        $connection = UserCalendarConnection::where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        return $connection?->getAllTaskTypeColors() ?? UserCalendarConnection::DEFAULT_TASK_TYPE_COLORS;
    }

    /**
     * Convert hex color to closest Google Calendar colorId.
     * Google Calendar has 11 predefined colors.
     */
    private function hexToGoogleColorId(string $hexColor): string
    {
        // Google Calendar predefined colors (event colors, not calendar colors)
        $googleColors = [
            '1'  => '#7986CB', // Lavender
            '2'  => '#33B679', // Sage
            '3'  => '#8E24AA', // Grape
            '4'  => '#E67C73', // Flamingo
            '5'  => '#F6BF26', // Banana
            '6'  => '#F4511E', // Tangerine
            '7'  => '#039BE5', // Peacock
            '8'  => '#616161', // Graphite
            '9'  => '#3F51B5', // Blueberry
            '10' => '#0B8043', // Basil
            '11' => '#D50000', // Tomato
        ];

        // Normalize hex color
        $hexColor = strtoupper(ltrim($hexColor, '#'));

        // Direct mappings for common colors (for better accuracy)
        $directMappings = [
            'EF4444' => '11', // Red -> Tomato
            'F59E0B' => '5',  // Amber -> Banana
            '10B981' => '10', // Green -> Basil
            '3B82F6' => '9',  // Blue -> Blueberry
            '8B5CF6' => '3',  // Purple -> Grape
            'EC4899' => '4',  // Pink -> Flamingo
            '6B7280' => '8',  // Gray -> Graphite
            '14B8A6' => '2',  // Teal -> Sage
        ];

        if (isset($directMappings[$hexColor])) {
            return $directMappings[$hexColor];
        }

        // Find closest color by calculating color distance
        $minDistance = PHP_INT_MAX;
        $closestId = '1';

        $targetRgb = $this->hexToRgb($hexColor);
        if (!$targetRgb) {
            return '1'; // Default to Lavender if parsing fails
        }

        foreach ($googleColors as $colorId => $googleHex) {
            $googleRgb = $this->hexToRgb(ltrim($googleHex, '#'));
            if (!$googleRgb) continue;

            // Calculate Euclidean distance in RGB space
            $distance = sqrt(
                pow($targetRgb['r'] - $googleRgb['r'], 2) +
                pow($targetRgb['g'] - $googleRgb['g'], 2) +
                pow($targetRgb['b'] - $googleRgb['b'], 2)
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestId = $colorId;
            }
        }

        return $closestId;
    }

    /**
     * Convert hex color string to RGB array.
     */
    private function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return null;
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}

