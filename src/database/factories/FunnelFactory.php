<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Funnel>
 */
class FunnelFactory extends Factory
{
    protected $model = Funnel::class;

    public function definition(): array
    {
        // The slug is generated from the name by Funnel::boot().
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'status' => Funnel::STATUS_DRAFT,
            'trigger_type' => Funnel::TRIGGER_MANUAL,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Funnel::STATUS_ACTIVE]);
    }
}
