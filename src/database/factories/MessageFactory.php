<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => 'email',
            'type' => 'broadcast',
            'status' => 'draft',
            'subject' => fake()->sentence(),
            'content' => '<p>' . fake()->paragraph() . '</p>',
        ];
    }

    public function broadcast(): static
    {
        return $this->state(fn () => [
            'type' => 'broadcast',
        ]);
    }

    public function autoresponder(int $day = 1): static
    {
        return $this->state(fn () => [
            'type' => 'autoresponder',
            'day' => $day,
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn () => [
            'channel' => 'sms',
        ]);
    }
}
