<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A subscriber has neither opened nor clicked for at least the days one
 * "subscriber_inactive" rule asks for. Raised by InactivityTriggerService for
 * one rule at a time: the context names that rule, so a 30-day rule and a
 * 90-day rule of the same account each run at their own moment.
 */
class SubscriberInactive
{
    use Dispatchable;

    /**
     * @param  int[]  $listIds  lists the subscriber is an active member of
     */
    public function __construct(
        public int $subscriberId,
        public int $userId,
        public int $ruleId,
        public int $inactiveDays,
        public ?string $lastActivityAt,
        public array $listIds = [],
    ) {}

    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'user_id' => $this->userId,
            'target_rule_id' => $this->ruleId,
            'inactive_days' => $this->inactiveDays,
            'last_activity_at' => $this->lastActivityAt,
            'list_ids' => $this->listIds,
        ];
    }

    public function getSubscriber(): ?Subscriber
    {
        return Subscriber::find($this->subscriberId);
    }
}
