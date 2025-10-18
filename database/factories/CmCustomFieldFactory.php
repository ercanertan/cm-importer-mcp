<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CmCustomField>
 */
class CmCustomFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fieldKey = strtolower($this->faker->unique()->word());

        return [
            'field_key' => $fieldKey,
            'field_name' => ucfirst($fieldKey),
            'data_type' => 'text',
            'options' => null,
            'is_active' => true,
            'last_seen_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function number(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'number',
        ]);
    }

    public function date(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'date',
        ]);
    }

    public function multiSelect(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'multi_select',
            'options' => ['Option 1', 'Option 2', 'Option 3'],
        ]);
    }
}
