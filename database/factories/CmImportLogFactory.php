<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CmImportLog>
 */
class CmImportLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'filename' => $this->faker->word() . '.csv',
            'file_hash' => md5($this->faker->uuid()),
            'storage_path' => 'imports/' . $this->faker->uuid() . '.csv',
            'file_type' => 'all', // Valid values: all, active, bounced, deleted, unsubscribed
            'status' => 'pending',
            'total_rows' => 0,
            'total_chunks' => 0,
            'completed_chunks' => 0,
            'failed_chunks' => 0,
            'is_chunked' => false,
            'processed_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'memory_peak' => null,
            'memory_current' => null,
            'custom_fields_detected' => [],
            'error_details' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'error_details' => ['error' => 'Test error message'],
        ]);
    }

    public function chunked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_chunked' => true,
            'total_chunks' => 10,
        ]);
    }
}
