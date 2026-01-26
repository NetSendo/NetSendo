<?php

namespace App\Services;

use App\Models\CrmTask;
use App\Models\UserZoomConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZoomMeetingService
{
    private const ZOOM_API_URL = 'https://api.zoom.us/v2';

    public function __construct(
        private ZoomOAuthService $oauthService
    ) {}

    /**
     * Create a Zoom meeting from a CRM task.
     */
    public function createMeetingFromTask(CrmTask $task, UserZoomConnection $connection): ?array
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);
        $payload = $this->taskToMeetingPayload($task);

        $response = Http::withToken($accessToken)
            ->post(self::ZOOM_API_URL . '/users/me/meetings', $payload);

        if (!$response->successful()) {
            Log::error('Failed to create Zoom meeting', [
                'task_id' => $task->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $meeting = $response->json();

        // Save meeting data to task
        $task->update([
            'zoom_meeting_id' => $meeting['id'] ?? null,
            'zoom_meeting_link' => $meeting['start_url'] ?? null,
            'zoom_join_url' => $meeting['join_url'] ?? null,
        ]);

        Log::info('Created Zoom meeting', [
            'task_id' => $task->id,
            'meeting_id' => $meeting['id'] ?? null,
        ]);

        return $meeting;
    }

    /**
     * Update an existing Zoom meeting.
     */
    public function updateMeeting(CrmTask $task, UserZoomConnection $connection): ?array
    {
        if (!$task->zoom_meeting_id) {
            return $this->createMeetingFromTask($task, $connection);
        }

        $accessToken = $this->oauthService->getValidAccessToken($connection);
        $payload = $this->taskToMeetingPayload($task);

        $response = Http::withToken($accessToken)
            ->patch(self::ZOOM_API_URL . "/meetings/{$task->zoom_meeting_id}", $payload);

        if (!$response->successful()) {
            // If meeting not found, create new one
            if ($response->status() === 404) {
                $task->update([
                    'zoom_meeting_id' => null,
                    'zoom_meeting_link' => null,
                    'zoom_join_url' => null,
                ]);
                return $this->createMeetingFromTask($task, $connection);
            }

            Log::error('Failed to update Zoom meeting', [
                'task_id' => $task->id,
                'meeting_id' => $task->zoom_meeting_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        Log::info('Updated Zoom meeting', [
            'task_id' => $task->id,
            'meeting_id' => $task->zoom_meeting_id,
        ]);

        // Fetch updated meeting details
        return $this->getMeeting($task->zoom_meeting_id, $connection);
    }

    /**
     * Delete a Zoom meeting.
     */
    public function deleteMeeting(string $meetingId, UserZoomConnection $connection): bool
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $response = Http::withToken($accessToken)
            ->delete(self::ZOOM_API_URL . "/meetings/{$meetingId}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('Failed to delete Zoom meeting', [
                'meeting_id' => $meetingId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('Deleted Zoom meeting', ['meeting_id' => $meetingId]);
        return true;
    }

    /**
     * Get meeting details.
     */
    public function getMeeting(string $meetingId, UserZoomConnection $connection): ?array
    {
        $accessToken = $this->oauthService->getValidAccessToken($connection);

        $response = Http::withToken($accessToken)
            ->get(self::ZOOM_API_URL . "/meetings/{$meetingId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Convert a CRM task to Zoom meeting payload.
     */
    private function taskToMeetingPayload(CrmTask $task): array
    {
        // Get user's timezone from their profile
        $userTimezone = $task->user?->timezone ?? config('app.timezone', 'UTC');

        $payload = [
            'topic' => $task->title,
            'type' => 2, // Scheduled meeting
            'timezone' => $userTimezone,
            'agenda' => $this->buildMeetingAgenda($task),
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => true,
                'mute_upon_entry' => false,
                'waiting_room' => false,
                'audio' => 'both',
                'auto_recording' => 'none',
            ],
        ];

        // Set meeting timing
        if ($task->due_date) {
            $payload['start_time'] = $task->due_date->toIso8601String();

            // Calculate duration in minutes
            if ($task->end_date) {
                $duration = $task->due_date->diffInMinutes($task->end_date);
                $payload['duration'] = max(30, min($duration, 1440)); // 30 min to 24 hours
            } else {
                $payload['duration'] = 60; // Default 1 hour
            }
        }

        // Add meeting invitees from task attendees
        $invitees = $this->buildInviteesList($task);
        if (!empty($invitees)) {
            $payload['settings']['meeting_invitees'] = $invitees;
        }

        return $payload;
    }

    /**
     * Build meeting agenda from task.
     */
    private function buildMeetingAgenda(CrmTask $task): string
    {
        $lines = [];

        if ($task->description) {
            $lines[] = $task->description;
            $lines[] = '';
        }

        if ($task->notes) {
            $lines[] = 'Notes:';
            $lines[] = $task->notes;
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = "Type: " . ucfirst($task->type);
        $lines[] = "Priority: " . ucfirst($task->priority);

        if ($task->contact) {
            $lines[] = "Contact: " . $task->contact->full_name;
        }

        if ($task->deal) {
            $lines[] = "Deal: " . $task->deal->name;
        }

        $lines[] = '';
        $lines[] = 'Managed by NetSendo CRM';

        return implode("\n", $lines);
    }

    /**
     * Build invitees list from task contact and attendee emails.
     */
    private function buildInviteesList(CrmTask $task): array
    {
        $invitees = [];
        $emails = [];

        // Add contact email if available
        if ($task->contact && $task->contact->email && !empty($task->contact->email)) {
            $invitees[] = ['email' => $task->contact->email];
            $emails[] = strtolower($task->contact->email);
        }

        // Add manual attendee emails (avoiding duplicates)
        if (!empty($task->attendee_emails) && is_array($task->attendee_emails)) {
            foreach ($task->attendee_emails as $email) {
                $email = trim($email);
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $normalizedEmail = strtolower($email);
                    if (!in_array($normalizedEmail, $emails)) {
                        $invitees[] = ['email' => $email];
                        $emails[] = $normalizedEmail;
                    }
                }
            }
        }

        return $invitees;
    }

    /**
     * Save Zoom meeting link from response to task.
     */
    public function saveMeetingLinkToTask(CrmTask $task, array $meeting): void
    {
        $task->update([
            'zoom_meeting_id' => $meeting['id'] ?? null,
            'zoom_meeting_link' => $meeting['start_url'] ?? null,
            'zoom_join_url' => $meeting['join_url'] ?? null,
        ]);

        Log::info('Saved Zoom meeting link to task', [
            'task_id' => $task->id,
            'join_url' => $meeting['join_url'] ?? null,
        ]);
    }

    /**
     * Clear Zoom meeting data from task.
     */
    public function clearMeetingFromTask(CrmTask $task): void
    {
        $task->update([
            'zoom_meeting_id' => null,
            'zoom_meeting_link' => null,
            'zoom_join_url' => null,
        ]);
    }
}
