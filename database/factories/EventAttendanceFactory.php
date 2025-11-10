<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventAttendance>
 */
class EventAttendanceFactory extends Factory
{
    protected $model = EventAttendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['registered', 'attended', 'cancelled', 'no_show']);

        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => $status,
            'registered_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'attended_at' => $status === 'attended' ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'cancelled_at' => $status === 'cancelled' ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'attendance_confirmed' => $status === 'attended',
            'registration_source' => $this->faker->randomElement(['web', 'api', 'admin', 'import']),
            'metadata' => [
                'question1' => $this->faker->sentence(),
                'dietary_restrictions' => $this->faker->optional()->randomElement(['vegetarian', 'vegan', 'gluten-free', 'none']),
            ],
        ];
    }

    /**
     * Indicate that the attendance is registered.
     */
    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'registered',
            'attended_at' => null,
            'cancelled_at' => null,
            'attendance_confirmed' => false,
        ]);
    }

    /**
     * Indicate that the user attended.
     */
    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'attended',
            'attended_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'cancelled_at' => null,
            'attendance_confirmed' => true,
        ]);
    }

    /**
     * Indicate that the registration was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'attended_at' => null,
            'cancelled_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'attendance_confirmed' => false,
        ]);
    }

    /**
     * Indicate that the user was a no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_show',
            'attended_at' => null,
            'cancelled_at' => null,
            'attendance_confirmed' => false,
        ]);
    }

    /**
     * Indicate that attendance is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_confirmed' => true,
        ]);
    }

    /**
     * Set the registration source.
     */
    public function source(string $source): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_source' => $source,
        ]);
    }

    /**
     * Set the event for this attendance.
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
        ]);
    }

    /**
     * Set the user for this attendance.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
