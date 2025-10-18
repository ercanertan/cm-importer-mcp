<?php

namespace Database\Factories;

use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncLog>
 */
class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement([
                'sync_all_domains',
                'sync_single_domain',
                'sync_domain_organizations',
                'sync_organization_domains',
                'move_users_to_default_organization',
            ]),
            'status' => 'pending',
            'user_id' => null,
            'total_items' => 0,
            'processed_items' => 0,
            'successful_items' => 0,
            'failed_items' => 0,
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
            'metadata' => [],
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
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
            'error_message' => 'Test error message',
        ]);
    }
}
