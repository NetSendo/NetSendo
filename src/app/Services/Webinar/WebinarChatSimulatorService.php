<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use App\Models\WebinarSession;
use App\Models\AutoWebinarChatScript;
use App\Models\WebinarChatMessage;
use Illuminate\Support\Collection;

class WebinarChatSimulatorService
{
    /**
     * Track which scripts have been triggered per session.
     */
    protected array $triggeredScripts = [];

    /**
     * Trigger chat scripts at a specific time.
     */
    public function triggerScriptsAtTime(WebinarSession $session, int $currentSeconds): Collection
    {
        $webinar = $session->webinar;
        $cacheKey = "session_{$session->id}";

        // Get scripts that should show at this time (with 2-second tolerance)
        $scripts = $webinar->chatScripts()
            ->active()
            ->atSecond($currentSeconds, 2)
            ->get();

        $messages = collect();

        foreach ($scripts as $script) {
            // Skip if already triggered this session
            if ($this->isAlreadyTriggered($cacheKey, $script->id)) {
                continue;
            }

            // Check if should show (handles random flag)
            if (!$script->shouldShow()) {
                $this->markAsTriggered($cacheKey, $script->id);
                continue;
            }

            // Create and save the chat message
            $message = $this->createMessageFromScript($script, $session);
            $messages->push($message);

            $this->markAsTriggered($cacheKey, $script->id);
        }

        return $messages;
    }

    /**
     * Get simulated messages for a time range (preview mode).
     */
    public function getSimulatedMessagesForRange(
        Webinar $webinar,
        int $fromSeconds,
        int $toSeconds
    ): Collection {
        return $webinar->chatScripts()
            ->active()
            ->whereBetween('show_at_seconds', [$fromSeconds, $toSeconds])
            ->orderBy('show_at_seconds')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($s) => $s->shouldShow())
            ->map(fn($s) => $this->scriptToPreviewMessage($s));
    }

    /**
     * Create a chat message from a script.
     */
    protected function createMessageFromScript(
        AutoWebinarChatScript $script,
        WebinarSession $session
    ): WebinarChatMessage {
        $message = WebinarChatMessage::create([
            'webinar_id' => $script->webinar_id,
            'webinar_session_id' => $session->id,
            'sender_type' => WebinarChatMessage::SENDER_BOT,
            'sender_name' => $script->sender_name,
            'sender_avatar_url' => $script->avatar_url,
            'message' => $script->message_text,
            'message_type' => WebinarChatMessage::TYPE_TEXT,
            'show_at_seconds' => $script->actual_show_time,
            'likes_count' => $script->reaction_count,
            'is_visible' => true,
        ]);

        // Broadcast to viewers
        broadcast(new \App\Events\WebinarChatMessageSent($message))->toOthers();

        return $message;
    }

    /**
     * Convert script to preview message format.
     */
    protected function scriptToPreviewMessage(AutoWebinarChatScript $script): array
    {
        return [
            'id' => $script->id,
            'show_at_seconds' => $script->actual_show_time,
            'show_at_formatted' => gmdate('i:s', $script->show_at_seconds),
            'sender_name' => $script->sender_name,
            'sender_avatar_url' => $script->avatar_url,
            'message_type' => $script->message_type,
            'message_text' => $script->message_text,
            'reaction_count' => $script->reaction_count,
            'is_original' => $script->is_original,
        ];
    }

    /**
     * Check if a script was already triggered.
     */
    protected function isAlreadyTriggered(string $cacheKey, int $scriptId): bool
    {
        return in_array($scriptId, $this->triggeredScripts[$cacheKey] ?? []);
    }

    /**
     * Mark script as triggered.
     */
    protected function markAsTriggered(string $cacheKey, int $scriptId): void
    {
        if (!isset($this->triggeredScripts[$cacheKey])) {
            $this->triggeredScripts[$cacheKey] = [];
        }
        $this->triggeredScripts[$cacheKey][] = $scriptId;
    }

    /**
     * Clear triggered scripts for a session.
     */
    public function clearTriggeredScripts(WebinarSession $session): void
    {
        $cacheKey = "session_{$session->id}";
        unset($this->triggeredScripts[$cacheKey]);
    }

    /**
     * Generate engagement burst (multiple messages in quick succession).
     */
    public function generateEngagementBurst(
        Webinar $webinar,
        int $atSeconds,
        int $count = 5,
        string $type = AutoWebinarChatScript::TYPE_EXCITEMENT
    ): array {
        $scripts = [];

        for ($i = 0; $i < $count; $i++) {
            $scripts[] = AutoWebinarChatScript::create([
                'webinar_id' => $webinar->id,
                'show_at_seconds' => $atSeconds + $i * 2, // 2 second intervals
                'sender_name' => AutoWebinarChatScript::getRandomName(),
                'sender_avatar_seed' => uniqid(),
                'message_type' => $type,
                'message_text' => AutoWebinarChatScript::getRandomMessage($type),
                'reaction_count' => mt_rand(1, 5),
                'delay_variance_seconds' => 1,
                'show_randomly' => false, // Always show burst messages
                'is_original' => false,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        return $scripts;
    }

    /**
     * Add contextual messages around key moments.
     */
    public function addContextualMessages(
        Webinar $webinar,
        array $keyMoments
    ): int {
        $added = 0;

        foreach ($keyMoments as $moment) {
            $type = $moment['type'] ?? AutoWebinarChatScript::TYPE_COMMENT;
            $atSeconds = $moment['at_seconds'];
            $count = $moment['count'] ?? 3;

            // Add messages before the moment (building anticipation)
            for ($i = 0; $i < ceil($count / 2); $i++) {
                AutoWebinarChatScript::create([
                    'webinar_id' => $webinar->id,
                    'show_at_seconds' => max(0, $atSeconds - 30 + ($i * 10)),
                    'sender_name' => AutoWebinarChatScript::getRandomName(),
                    'sender_avatar_seed' => uniqid(),
                    'message_type' => AutoWebinarChatScript::TYPE_COMMENT,
                    'message_text' => AutoWebinarChatScript::getRandomMessage(AutoWebinarChatScript::TYPE_COMMENT),
                    'reaction_count' => mt_rand(0, 3),
                    'delay_variance_seconds' => 3,
                    'show_randomly' => true,
                    'is_original' => false,
                    'is_active' => true,
                ]);
                $added++;
            }

            // Add messages after the moment (reactions)
            for ($i = 0; $i < floor($count / 2); $i++) {
                AutoWebinarChatScript::create([
                    'webinar_id' => $webinar->id,
                    'show_at_seconds' => $atSeconds + 5 + ($i * 5),
                    'sender_name' => AutoWebinarChatScript::getRandomName(),
                    'sender_avatar_seed' => uniqid(),
                    'message_type' => $type,
                    'message_text' => AutoWebinarChatScript::getRandomMessage($type),
                    'reaction_count' => mt_rand(1, 8),
                    'delay_variance_seconds' => 2,
                    'show_randomly' => false,
                    'is_original' => false,
                    'is_active' => true,
                ]);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Simulate real attendee joining messages.
     */
    public function addJoinMessages(Webinar $webinar, int $durationSeconds): int
    {
        $added = 0;

        // Most joins happen in first 5 minutes
        $joinTimes = [
            0 => 5,
            30 => 3,
            60 => 4,
            120 => 3,
            180 => 2,
            240 => 2,
            300 => 1,
        ];

        foreach ($joinTimes as $seconds => $count) {
            if ($seconds > $durationSeconds) {
                break;
            }

            for ($i = 0; $i < $count; $i++) {
                $name = AutoWebinarChatScript::getRandomName();
                AutoWebinarChatScript::create([
                    'webinar_id' => $webinar->id,
                    'show_at_seconds' => $seconds + mt_rand(-5, 15),
                    'sender_name' => 'System',
                    'sender_avatar_seed' => 'system',
                    'message_type' => AutoWebinarChatScript::TYPE_COMMENT,
                    'message_text' => "{$name} dołączył/a do webinaru",
                    'reaction_count' => 0,
                    'delay_variance_seconds' => 5,
                    'show_randomly' => true, // 70% chance
                    'is_original' => false,
                    'is_active' => true,
                ]);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Simulate social proof with purchase notifications.
     */
    public function addPurchaseNotifications(
        Webinar $webinar,
        int $productPinSeconds,
        int $count = 5,
        int $spreadSeconds = 300
    ): int {
        $added = 0;

        for ($i = 0; $i < $count; $i++) {
            $name = AutoWebinarChatScript::getRandomName();
            $showAt = $productPinSeconds + 60 + mt_rand(0, $spreadSeconds);

            AutoWebinarChatScript::create([
                'webinar_id' => $webinar->id,
                'show_at_seconds' => $showAt,
                'sender_name' => 'System',
                'sender_avatar_seed' => 'purchase',
                'message_type' => AutoWebinarChatScript::TYPE_TESTIMONIAL,
                'message_text' => "🎉 {$name} właśnie dokonał/a zakupu!",
                'reaction_count' => mt_rand(2, 10),
                'delay_variance_seconds' => 10,
                'show_randomly' => true,
                'is_original' => false,
                'is_active' => true,
            ]);
            $added++;
        }

        return $added;
    }
}
