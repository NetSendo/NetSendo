<?php

namespace App\Services\Connector;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Limits how many connector tool calls run at the same time.
 *
 * A tool call starts a Node.js process and holds a PHP worker until it ends,
 * so without a limit one connected app could take every worker of a small
 * installation. A call needs a free slot of its own app and a free slot of the
 * installation (config connector.concurrency); without both it is not started.
 *
 * A slot is a cache lock, which every cache store of the application provides
 * (file, database, Redis), and it expires by itself if a worker dies.
 */
class ToolCallSlots
{
    /**
     * @param  string  $owner  the connected app or API key the call belongs to
     * @return list<Lock>|null the slots that were taken, or null when none is free
     */
    public function acquire(string $owner): ?array
    {
        $ttl = max(5, (int) config('connector.engine.timeout', 150)) + 30;

        try {
            $own = $this->take('connector.calls.'.$owner, (int) config('connector.concurrency.per_connection', 2), $ttl);

            if ($own === false) {
                return null;
            }

            $shared = $this->take('connector.calls.all', (int) config('connector.concurrency.total', 3), $ttl);

            if ($shared === false) {
                $own?->release();

                return null;
            }
        } catch (Throwable $e) {
            // A cache store without locks: the connector keeps working, unlimited.
            report($e);

            return [];
        }

        return array_values(array_filter([$own, $shared]));
    }

    /** @param  list<Lock>  $slots */
    public function release(array $slots): void
    {
        foreach ($slots as $slot) {
            try {
                $slot->release();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** One of $slots locks, null when the limit is off (0), false when all are taken. */
    private function take(string $name, int $slots, int $ttl): Lock|false|null
    {
        if ($slots <= 0) {
            return null;
        }

        for ($slot = 0; $slot < $slots; $slot++) {
            $lock = Cache::lock($name.'.'.$slot, $ttl);

            if ($lock->get()) {
                return $lock;
            }
        }

        return false;
    }
}
