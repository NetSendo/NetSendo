<?php

namespace App\Services\Webinar;

use RuntimeException;

/**
 * The webinar room chat did not take a viewer's message.
 * $reason: disabled | host_only | slow_mode.
 */
class RoomChatRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $retryAfter = 0)
    {
        parent::__construct("Chat message refused: {$reason}");
    }
}
