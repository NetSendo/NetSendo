<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\User;

/**
 * Token budget of one Brain task: a chat message, the run of a plan, a goal step or a cron task.
 *
 * Brain used to stop for the rest of the day once a daily token count was reached, which an
 * account with many lists and tasks hits by noon. The limit belongs to a single task instead:
 * a task that goes over it is stopped (no further AI call is made for it) and says why, while
 * every other task keeps working.
 *
 * Every AI call Brain makes is charged through AiBrainSettings::addTokensUsed(), which feeds
 * the running task; the AI call sites ask ensureAvailable() before they call the provider.
 * Tasks do not nest: a plan run inside a chat message (autonomous mode) or a goal step started
 * from it counts towards the message's task.
 */
final class TaskTokenBudget
{
    /** @var array{user_id: int, limit: int, used: int, exceeded: ?string}|null */
    private static ?array $task = null;

    /**
     * Run $callback as one task of $user. Inside another task it simply runs as part of it.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(User|int $user, callable $callback): mixed
    {
        if (self::$task !== null) {
            return $callback();
        }

        $userId = $user instanceof User ? $user->id : $user;

        self::$task = [
            'user_id' => $userId,
            'limit' => AiBrainSettings::getForUser($userId)->taskTokenLimit(),
            'used' => 0,
            'exceeded' => null,
        ];

        try {
            return $callback();
        } finally {
            self::$task = null;
        }
    }

    /**
     * Record tokens the running task used (called for every charged AI call).
     */
    public static function charge(int $userId, int $tokens): void
    {
        if (self::$task !== null && self::$task['user_id'] === $userId && $tokens > 0) {
            self::$task['used'] += $tokens;
        }
    }

    /**
     * Stop the running task before another AI call once it has used its limit.
     *
     * @throws TaskTokenLimitExceeded
     */
    public static function ensureAvailable(?int $userId): void
    {
        $task = self::$task;
        if ($task === null || $userId === null || $task['user_id'] !== $userId) {
            return;
        }

        if ($task['exceeded'] === null && ($task['limit'] <= 0 || $task['used'] < $task['limit'])) {
            return;
        }

        // The first stopped call explains the limit; the ones after it (the plan's next AI steps)
        // only say they were skipped, so a plan report does not repeat the explanation
        if ($task['exceeded'] !== null) {
            throw new TaskTokenLimitExceeded(__('brain.task_token_limit_skipped'));
        }

        // Fixed when the limit is hit, so every place that reports it says the same
        self::$task['exceeded'] = __('brain.task_token_limit_reached', [
            'used' => number_format($task['used'], 0, ',', ' '),
            'limit' => number_format($task['limit'], 0, ',', ' '),
        ]);

        throw new TaskTokenLimitExceeded(self::$task['exceeded']);
    }

    /**
     * Whether the running task was stopped by its token limit.
     */
    public static function exceeded(): bool
    {
        return self::$task !== null && self::$task['exceeded'] !== null;
    }

    /**
     * The message that tells the user the running task was stopped, or null.
     */
    public static function exceededMessage(): ?string
    {
        return self::$task['exceeded'] ?? null;
    }

    /**
     * Tokens the running task used so far (0 outside a task).
     */
    public static function used(): int
    {
        return self::$task['used'] ?? 0;
    }
}
