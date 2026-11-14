<?php

namespace Database\Factories;

use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomField>
 */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'field_' . fake()->unique()->lexify('??????'),
            'label' => fake()->words(2, true),
            'description' => null,
            'type' => 'text',
            'options' => null,
            'default_value' => null,
            'is_public' => true,
            'is_required' => false,
            'is_static' => false,
            'scope' => 'global',
            'contact_list_id' => null,
            'sort_order' => 0,
        ];
    }

    public function text(): static
    {
        return $this->state(fn () => [
            'type' => 'text',
            'options' => null,
        ]);
    }

    public function number(): static
    {
        return $this->state(fn () => [
            'type' => 'number',
            'options' => null,
        ]);
    }

    public function date(): static
    {
        return $this->state(fn () => [
            'type' => 'date',
            'options' => null,
        ]);
    }

    public function select(array $options = ['Option 1', 'Option 2', 'Option 3']): static
    {
        return $this->state(fn () => [
            'type' => 'select',
            'options' => $options,
        ]);
    }

    public function checkbox(): static
    {
        return $this->state(fn () => [
            'type' => 'checkbox',
            'options' => null,
        ]);
    }

    public function radio(array $options = ['Option 1', 'Option 2', 'Option 3']): static
    {
        return $this->state(fn () => [
            'type' => 'radio',
            'options' => $options,
        ]);
    }

    public function global(): static
    {
        return $this->state(fn () => [
            'scope' => 'global',
            'contact_list_id' => null,
        ]);
    }

    public function forList(?ContactList $list = null): static
    {
        return $this->state(function (array $attributes) use ($list) {
            if ($list) {
                return [
                    'scope' => 'list',
                    'contact_list_id' => $list->id,
                    'user_id' => $list->user_id,
                ];
            }

            return [
                'scope' => 'list',
                'contact_list_id' => function (array $attrs) {
                    $userId = $attrs['user_id'] ?? User::factory();
                    return ContactList::factory()->create(['user_id' => $userId])->id;
                },
            ];
        });
    }

    public function listScoped(?ContactList $list = null): static
    {
        return $this->forList($list);
    }
}
