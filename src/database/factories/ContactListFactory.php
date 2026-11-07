<?php

namespace Database\Factories;

use App\Models\ContactList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContactList>
 */
class ContactListFactory extends Factory
{
    protected $model = ContactList::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'type' => 'email',
        ];
    }

    public function sms(): static
    {
        return $this->state(fn () => ['type' => 'sms']);
    }
}
