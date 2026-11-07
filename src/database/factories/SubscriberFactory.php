<?php

namespace Database\Factories;

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => Subscriber::STATUS_ACTIVE,
            'is_active_global' => true,
            'subscribed_at' => now(),
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => ['status' => Subscriber::STATUS_UNSUBSCRIBED]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => ['status' => Subscriber::STATUS_BOUNCED]);
    }
}
