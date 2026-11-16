<?php

namespace Database\Factories;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mailbox>
 */
class MailboxFactory extends Factory
{
    protected $model = Mailbox::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true) . ' Mailbox',
            'provider' => Mailbox::PROVIDER_SMTP,
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'is_default' => false,
            'is_active' => true,
            'allowed_types' => Mailbox::getDefaultAllowedTypes(Mailbox::PROVIDER_SMTP),
            'credentials' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => fake()->userName(),
                'password' => fake()->password(),
            ],
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
