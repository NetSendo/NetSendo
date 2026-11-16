<?php

namespace App\Services\Webinar;

use App\Models\AutoWebinarChatScript;
use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use App\Models\WebinarRegistration;
use App\Models\WebinarSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The chat panel of the public webinar room (/webinar/{slug}/watch/{token}).
 *
 * The room is a plain Blade page without a WebSocket client, so it polls
 * feed() and posts through post(). Scripted chat of auto-webinars follows the
 * viewer's own session clock: a message scheduled at 00:25 appears 25 seconds
 * after that viewer's session started, and a late joiner sees everything that
 * was "said" before they arrived. Only messages already due are sent, so the
 * rest of the script never reaches the browser.
 */
class WebinarRoomChatService
{
    /** Most messages the room shows at once. */
    public const FEED_LIMIT = 150;

    /** Share of "show randomly" script lines a viewer sees (matches AutoWebinarChatScript::shouldShow). */
    protected const RANDOM_SHOW_PERCENT = 70;

    public function __construct(protected WebinarChatService $chatService) {}

    /**
     * When this viewer's session starts (or started): the registered session,
     * else the webinar's fixed time, else the next slot of an auto-webinar.
     */
    public function sessionStart(WebinarRegistration $registration): ?Carbon
    {
        $webinar = $registration->webinar;

        if ($registration->session && $registration->session->scheduled_at) {
            return $registration->session->scheduled_at;
        }

        if ($webinar->scheduled_at) {
            return $webinar->scheduled_at;
        }

        if ($webinar->isAutoWebinar() && $webinar->schedule) {
            return $webinar->schedule->getNextSessionTimes(1)[0] ?? null;
        }

        return null;
    }

    public function liveSession(Webinar $webinar): ?WebinarSession
    {
        return $webinar->sessions()->where('status', WebinarSession::STATUS_LIVE)->first();
    }

    /**
     * What the chat panel shows right now.
     *
     * @param int|null $playerPosition the video position reported by the page,
     *                                 used only when the session has no start time
     */
    public function feed(WebinarRegistration $registration, ?int $playerPosition = null): array
    {
        $webinar = $registration->webinar;

        if (!$webinar->isChatEnabled()) {
            return ['enabled' => false, 'can_send' => false, 'mode' => null, 'messages' => []];
        }

        $settings = $webinar->chat_settings_with_defaults;
        $liveSession = $this->liveSession($webinar);
        $start = $this->sessionStart($registration);

        $messages = $this->realMessages($registration, $liveSession, $start);

        if (!$liveSession && $this->playsScript($webinar)) {
            $elapsed = $start ? (int) floor($start->diffInSeconds(now(), false)) : $playerPosition;

            if ($elapsed !== null && $elapsed >= 0) {
                $messages = $messages->concat($this->scriptedMessages($registration, $elapsed, $start));
            }
        }

        $messages = $messages
            ->sortBy([['at', 'asc'], ['key', 'asc']])
            ->values()
            ->take(-self::FEED_LIMIT)
            ->values();

        return [
            'enabled' => true,
            'can_send' => ($settings['mode'] ?? 'open') !== 'host_only',
            'mode' => $settings['mode'] ?? 'open',
            'messages' => $messages->all(),
        ];
    }

    /**
     * Post a viewer's message.
     *
     * @throws RoomChatRefused when the chat does not take it
     */
    public function post(WebinarRegistration $registration, string $text): array
    {
        $webinar = $registration->webinar;
        $settings = $webinar->chat_settings_with_defaults;

        if (!$webinar->isChatEnabled()) {
            throw new RoomChatRefused('disabled');
        }

        if (($settings['mode'] ?? 'open') === 'host_only') {
            throw new RoomChatRefused('host_only');
        }

        $slowMode = (int) ($settings['slow_mode_seconds'] ?? 0);
        if ($slowMode > 0) {
            $last = $webinar->chatMessages()
                ->where('registration_id', $registration->id)
                ->latest('created_at')
                ->value('created_at');

            if ($last) {
                $wait = $slowMode - (int) floor(Carbon::parse($last)->diffInSeconds(now()));
                if ($wait > 0) {
                    throw new RoomChatRefused('slow_mode', $wait);
                }
            }
        }

        $type = ($settings['mode'] ?? 'open') === 'qa_only'
            ? WebinarChatMessage::TYPE_QUESTION
            : WebinarChatMessage::TYPE_TEXT;

        $message = $this->chatService->sendAttendeeMessage(
            $webinar,
            $registration,
            $text,
            $this->liveSession($webinar) ?? $registration->session,
            $type
        );

        return $this->present($message, $registration);
    }

    /**
     * Auto and hybrid webinars replay a recording, so they replay its chat script too.
     */
    protected function playsScript(Webinar $webinar): bool
    {
        return in_array($webinar->type, [Webinar::TYPE_AUTO, Webinar::TYPE_HYBRID], true);
    }

    /**
     * Script lines already due at $elapsed seconds into this viewer's session.
     */
    protected function scriptedMessages(WebinarRegistration $registration, int $elapsed, ?Carbon $start): Collection
    {
        $now = now();

        return $registration->webinar->chatScripts()
            ->active()
            // actual_show_time moves a line by at most delay_variance_seconds (max 30)
            ->where('show_at_seconds', '<=', $elapsed + 30)
            ->get()
            ->filter(fn (AutoWebinarChatScript $script) => $script->actual_show_time <= $elapsed
                && $this->viewerSees($registration, $script))
            ->map(function (AutoWebinarChatScript $script) use ($start, $now, $elapsed) {
                $at = $start
                    ? $start->copy()->addSeconds($script->actual_show_time)
                    : $now->copy()->subSeconds($elapsed - $script->actual_show_time);

                return [
                    'key' => 's' . $script->id,
                    'sender_name' => $script->sender_name,
                    'message' => $script->message_text,
                    'kind' => $script->message_type === AutoWebinarChatScript::TYPE_QUESTION ? 'question' : 'text',
                    'is_host' => false,
                    'is_own' => false,
                    'pending' => false,
                    'at' => $at->copy()->utc()->toIso8601String(),
                ];
            })
            ->values();
    }

    /**
     * "Show randomly" lines appear for about 70% of viewers. The choice is fixed
     * per viewer, so a reload or the next poll does not make lines vanish.
     */
    protected function viewerSees(WebinarRegistration $registration, AutoWebinarChatScript $script): bool
    {
        if (!$script->show_randomly) {
            return true;
        }

        return crc32($registration->id . ':' . $script->id) % 100 < self::RANDOM_SHOW_PERCENT;
    }

    /**
     * Messages people actually wrote. In a live session everyone sees the
     * session's chat. A recording plays on each viewer's own clock, so there a
     * viewer sees their own messages and what the host wrote to everyone (or
     * to their session) since it started.
     */
    protected function realMessages(WebinarRegistration $registration, ?WebinarSession $liveSession, ?Carbon $start): Collection
    {
        $webinar = $registration->webinar;

        $query = $webinar->chatMessages()
            ->where('is_deleted', false)
            ->whereIn('message_type', [
                WebinarChatMessage::TYPE_TEXT,
                WebinarChatMessage::TYPE_QUESTION,
                WebinarChatMessage::TYPE_ANSWER,
            ]);

        if ($liveSession) {
            $query->where('webinar_session_id', $liveSession->id)
                ->where(fn ($q) => $q->where('is_visible', true)->orWhere('registration_id', $registration->id));
        } else {
            $sessionId = $registration->webinar_session_id;
            $since = $start && $start->lt($registration->created_at) ? $start : $registration->created_at;

            $query->where(function ($q) use ($registration, $sessionId, $since) {
                $q->where('registration_id', $registration->id)
                    ->orWhere(function ($host) use ($sessionId, $since) {
                        $host->whereIn('sender_type', [
                            WebinarChatMessage::SENDER_HOST,
                            WebinarChatMessage::SENDER_MODERATOR,
                            WebinarChatMessage::SENDER_SYSTEM,
                        ])
                            ->where('is_visible', true)
                            ->where('created_at', '>=', $since)
                            ->where(fn ($s) => $s->whereNull('webinar_session_id')
                                ->when($sessionId, fn ($s) => $s->orWhere('webinar_session_id', $sessionId)));
                    });
            });
        }

        return $query->latest('created_at')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->map(fn (WebinarChatMessage $message) => $this->present($message, $registration));
    }

    /**
     * Public shape of a message: no ids of other people, no hint whether a line was scripted.
     */
    protected function present(WebinarChatMessage $message, WebinarRegistration $registration): array
    {
        $isOwn = $message->registration_id === $registration->id;

        return [
            'key' => 'm' . $message->id,
            'sender_name' => $message->sender_name,
            'message' => $message->message,
            'kind' => $message->message_type === WebinarChatMessage::TYPE_QUESTION ? 'question' : 'text',
            'is_host' => $message->is_from_host,
            'is_own' => $isOwn,
            'pending' => $isOwn && !$message->is_visible,
            'at' => $message->created_at->copy()->utc()->toIso8601String(),
        ];
    }
}
