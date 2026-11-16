<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag_id' => 1],
                ],
            ],
            'is_active' => true,
        ];
    }

    public function subscriberSignup(): static
    {
        return $this->state(fn () => [
            'trigger_event' => 'subscriber_signup',
        ]);
    }

    public function tagAdded(): static
    {
        return $this->state(fn () => [
            'trigger_event' => 'tag_added',
        ]);
    }

    public function tagRemoved(): static
    {
        return $this->state(fn () => [
            'trigger_event' => 'tag_removed',
        ]);
    }

    public function emailOpened(): static
    {
        return $this->state(fn () => [
            'trigger_event' => 'email_opened',
        ]);
    }

    public function emailClicked(): static
    {
        return $this->state(fn () => [
            'trigger_event' => 'email_clicked',
        ]);
    }

    public function fieldUpdated(): static
    {
        return $this->state(fn () => [
            'trigger_event' => 'field_updated',
        ]);
    }
}
