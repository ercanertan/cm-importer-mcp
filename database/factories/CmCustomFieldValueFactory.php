<?php

namespace Database\Factories;

use App\Models\CmCustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CmCustomFieldValue>
 */
class CmCustomFieldValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cm_custom_field_id' => CmCustomField::factory(),
            'value' => $this->faker->word(),
        ];
    }

    public function withTextValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $this->faker->sentence(),
        ]);
    }

    public function withNumberValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => (string) $this->faker->randomNumber(),
        ]);
    }

    public function withDateValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $this->faker->date(),
        ]);
    }

    public function withMultiSelectValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => implode(',', $this->faker->words(3)),
        ]);
    }
}
