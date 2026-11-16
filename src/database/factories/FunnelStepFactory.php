<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelStep>
 */
class FunnelStepFactory extends Factory
{
    protected $model = FunnelStep::class;

    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'type' => FunnelStep::TYPE_START,
            'name' => fake()->words(2, true),
            'order' => 0,
        ];
    }
}
