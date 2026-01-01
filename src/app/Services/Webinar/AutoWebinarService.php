<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use App\Models\WebinarSession;
use App\Models\AutoWebinarSchedule;
use App\Models\AutoWebinarChatScript;
use App\Models\WebinarChatMessage;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AutoWebinarService
{
    public function __construct(
        protected WebinarChatSimulatorService $chatSimulator
    ) {}

    /**
     * Configure auto-webinar schedule.
     */
    public function configureSchedule(Webinar $webinar, array $scheduleData): AutoWebinarSchedule
    {
        if ($webinar->schedule) {
            $webinar->schedule->update($scheduleData);
            return $webinar->schedule->fresh();
        }

        return $webinar->schedule()->create($scheduleData);
    }

    /**
     * Get next available session times.
     */
    public function getNextSessionTimes(Webinar $webinar, int $count = 5): array
    {
        if (!$webinar->schedule) {
            return [];
        }

        return $webinar->schedule->getNextSessionTimes($count);
    }

    /**
     * Create a session for a specific time slot.
     */
    public function createSession(Webinar $webinar, Carbon $scheduledAt): WebinarSession
    {
        $sessionNumber = $webinar->sessions()->count() + 1;

        return WebinarSession::create([
            'webinar_id' => $webinar->id,
            'scheduled_at' => $scheduledAt,
            'status' => WebinarSession::STATUS_SCHEDULED,
            'is_replay' => true,
            'session_number' => $sessionNumber,
        ]);
    }

    /**
     * Start an auto-webinar session.
     */
    public function startSession(WebinarSession $session): bool
    {
        if ($session->status !== WebinarSession::STATUS_SCHEDULED) {
            return false;
        }

        $session->update([
            'status' => WebinarSession::STATUS_LIVE,
            'started_at' => now(),
            'current_position_seconds' => 0,
        ]);

        // Update webinar status if not already live
        if ($session->webinar->status !== Webinar::STATUS_LIVE) {
            $session->webinar->update([
                'status' => Webinar::STATUS_LIVE,
            ]);
        }

        return true;
    }

    /**
     * Update session playback position.
     */
    public function updatePosition(WebinarSession $session, int $positionSeconds): void
    {
        $session->updatePosition($positionSeconds);

        // Trigger timed events (chat scripts, CTAs, products)
        $this->triggerTimedEvents($session, $positionSeconds);
    }

    /**
     * Trigger events at specific video positions.
     */
    protected function triggerTimedEvents(WebinarSession $session, int $positionSeconds): void
    {
        $webinar = $session->webinar;

        // Trigger chat scripts
        $this->chatSimulator->triggerScriptsAtTime($session, $positionSeconds);

        // Trigger product pins
        foreach ($webinar->products()->active()->get() as $product) {
            if ($product->shouldPinAt($positionSeconds)) {
                $product->pin();
            } elseif ($product->shouldUnpinAt($positionSeconds)) {
                $product->unpin();
            }
        }

        // Trigger CTAs
        foreach ($webinar->ctas()->active()->get() as $cta) {
            if ($cta->shouldShowAt($positionSeconds)) {
                $cta->show();
            } elseif ($cta->shouldHideAt($positionSeconds)) {
                $cta->hide();
            }
        }
    }

    /**
     * End an auto-webinar session.
     */
    public function endSession(WebinarSession $session): bool
    {
        if ($session->status !== WebinarSession::STATUS_LIVE) {
            return false;
        }

        $session->update([
            'status' => WebinarSession::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        // Mark registered users who didn't fully attend as partial
        $session->registrations()
            ->where('status', 'attended')
            ->where('watch_time_seconds', '<', $session->webinar->duration_minutes * 60 * 0.8)
            ->update(['status' => 'partial']);

        return true;
    }

    /**
     * Import chat from a live webinar session.
     */
    public function importChatFromLive(Webinar $autoWebinar, WebinarSession $liveSession): int
    {
        $messages = WebinarChatMessage::where('webinar_session_id', $liveSession->id)
            ->visible()
            ->fromAttendees()
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            return 0;
        }

        $sessionStart = $liveSession->started_at;
        $imported = 0;

        foreach ($messages as $message) {
            $showAtSeconds = $sessionStart ? $message->created_at->diffInSeconds($sessionStart) : 0;

            AutoWebinarChatScript::create([
                'webinar_id' => $autoWebinar->id,
                'show_at_seconds' => $showAtSeconds,
                'sender_name' => $message->sender_name,
                'sender_avatar_seed' => md5($message->sender_name),
                'message_type' => $this->mapMessageType($message->message_type),
                'message_text' => $message->message,
                'reaction_count' => $message->likes_count,
                'delay_variance_seconds' => 2,
                'show_randomly' => false,
                'is_original' => true,
                'source_message_id' => $message->id,
                'sort_order' => $imported,
                'is_active' => true,
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * Generate random chat scripts.
     */
    public function generateRandomChatScripts(
        Webinar $webinar,
        int $durationSeconds,
        int $density = 1
    ): int {
        $scripts = AutoWebinarChatScript::generateRandomMessages($webinar, $durationSeconds, $density);

        foreach ($scripts as $scriptData) {
            AutoWebinarChatScript::create($scriptData);
        }

        return count($scripts);
    }

    /**
     * Clear all chat scripts.
     */
    public function clearChatScripts(Webinar $webinar): int
    {
        return $webinar->chatScripts()->delete();
    }

    /**
     * Preview chat scripts as timeline.
     */
    public function previewChatTimeline(Webinar $webinar, int $limitPerMinute = 5): Collection
    {
        $scripts = $webinar->chatScripts()
            ->active()
            ->orderBy('show_at_seconds')
            ->get();

        // Group by minute for preview
        return $scripts->groupBy(fn($s) => floor($s->show_at_seconds / 60))
            ->map(fn($group) => $group->take($limitPerMinute))
            ->flatten(1);
    }

    /**
     * Get chat scripts for a specific time range.
     */
    public function getScriptsForTimeRange(
        Webinar $webinar,
        int $fromSeconds,
        int $toSeconds
    ): Collection {
        return $webinar->chatScripts()
            ->active()
            ->whereBetween('show_at_seconds', [$fromSeconds, $toSeconds])
            ->orderBy('show_at_seconds')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Schedule upcoming auto-webinar sessions.
     */
    public function scheduleUpcomingSessions(Webinar $webinar, int $daysAhead = 7): int
    {
        if (!$webinar->schedule || !$webinar->schedule->is_active) {
            return 0;
        }

        $schedule = $webinar->schedule;
        $sessionsCreated = 0;

        // Get next session times
        $times = $schedule->getNextSessionTimes(50); // Get enough for a week
        $cutoff = now()->addDays($daysAhead);

        foreach ($times as $time) {
            if ($time->greaterThan($cutoff)) {
                break;
            }

            // Check if session already exists
            $exists = $webinar->sessions()
                ->where('scheduled_at', $time)
                ->exists();

            if (!$exists && $schedule->canScheduleAt($time)) {
                $this->createSession($webinar, $time);
                $sessionsCreated++;
            }
        }

        return $sessionsCreated;
    }

    /**
     * Get sessions that should start now.
     */
    public function getSessionsToStart(): Collection
    {
        return WebinarSession::where('status', WebinarSession::STATUS_SCHEDULED)
            ->where('is_replay', true)
            ->where('scheduled_at', '<=', now())
            ->where('scheduled_at', '>', now()->subMinutes(5))
            ->with('webinar')
            ->get();
    }

    /**
     * Map message type from chat to script type.
     */
    protected function mapMessageType(string $chatType): string
    {
        return match ($chatType) {
            WebinarChatMessage::TYPE_QUESTION => AutoWebinarChatScript::TYPE_QUESTION,
            WebinarChatMessage::TYPE_REACTION => AutoWebinarChatScript::TYPE_REACTION,
            default => AutoWebinarChatScript::TYPE_COMMENT,
        };
    }

    /**
     * Convert webinar from live to auto.
     */
    public function convertToAutoWebinar(Webinar $webinar): bool
    {
        if ($webinar->type !== Webinar::TYPE_LIVE) {
            return false;
        }

        // Get the latest ended session
        $liveSession = $webinar->sessions()
            ->where('status', WebinarSession::STATUS_ENDED)
            ->orderBy('ended_at', 'desc')
            ->first();

        // Update webinar type
        $webinar->update([
            'type' => Webinar::TYPE_AUTO,
            'status' => Webinar::STATUS_PUBLISHED,
        ]);

        // Import chat from live session
        if ($liveSession) {
            $this->importChatFromLive($webinar, $liveSession);
        }

        // Create default schedule
        $this->configureSchedule($webinar, [
            'schedule_type' => AutoWebinarSchedule::TYPE_RECURRING,
            'days_of_week' => [1, 2, 3, 4, 5], // Mon-Fri
            'times_of_day' => ['10:00', '14:00', '19:00'],
            'timezone' => $webinar->timezone,
            'is_active' => true,
        ]);

        return true;
    }
}
