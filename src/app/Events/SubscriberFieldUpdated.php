<?php

namespace App\Events;

use App\Models\Subscriber;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A field of an existing subscriber changed value: a standard field
 * (first_name, phone, gender…) or a custom field, named by its `name`.
 * Raised by FieldChangeNotifier.
 */
class SubscriberFieldUpdated
{
    use Dispatchable;

    public function __construct(
        public int $subscriberId,
        public int $userId,
        public string $field,
        public ?string $oldValue,
        public ?string $newValue,
    ) {}

    public function getContext(): array
    {
        return [
            'subscriber_id' => $this->subscriberId,
            'user_id' => $this->userId,
            'field' => $this->field,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
        ];
    }

    public function getSubscriber(): ?Subscriber
    {
        return Subscriber::find($this->subscriberId);
    }
}
