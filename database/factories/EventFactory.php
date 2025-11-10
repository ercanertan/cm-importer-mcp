<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->catchPhrase() . ' ' . $this->faker->randomElement(['Conference', 'Webinar', 'Workshop', 'Training', 'Seminar']);

        $startsAt = $this->faker->dateTimeBetween('now', '+3 months');
        $endsAt = (clone $startsAt)->modify('+' . $this->faker->numberBetween(1, 5) . ' hours');

        return [
            'name' => $name,
            'description' => $this->faker->paragraphs(3, true),
            'type' => $this->faker->randomElement(['webinar', 'conference', 'workshop', 'training', 'seminar', 'meetup']),
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $this->faker->randomElement(['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo']),
            'location_type' => $this->faker->randomElement(['virtual', 'physical', 'hybrid']),
            'location_name' => $this->faker->randomElement(['Zoom', 'Microsoft Teams', 'Google Meet', 'Convention Center', 'Hotel Conference Room']),
            'location_address' => $this->faker->optional()->address(),
            'location_url' => $this->faker->optional()->url(),
            'requires_registration' => $this->faker->boolean(80),
            'max_attendees' => $this->faker->optional(0.5)->numberBetween(10, 500),
            'registration_opens_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'registration_closes_at' => $this->faker->optional()->dateTimeBetween('now', $startsAt),
            'status' => $this->faker->randomElement(['draft', 'published', 'cancelled', 'completed']),
            'organizer_id' => User::factory(),
            'organizer_name' => $this->faker->optional()->name(),
            'organizer_email' => $this->faker->optional()->safeEmail(),
            'metadata' => [
                'speakers' => [$this->faker->name(), $this->faker->name()],
                'topics' => $this->faker->words(5),
            ],
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    /**
     * Indicate that the event is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the event is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the event is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'starts_at' => $this->faker->dateTimeBetween('-3 months', '-1 week'),
        ]);
    }

    /**
     * Indicate that the event is upcoming.
     */
    public function upcoming(): static
    {
        $startsAt = $this->faker->dateTimeBetween('+1 week', '+3 months');

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => 'published',
        ]);
    }

    /**
     * Indicate that the event is past.
     */
    public function past(): static
    {
        $startsAt = $this->faker->dateTimeBetween('-3 months', '-1 week');

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the event is a webinar.
     */
    public function webinar(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'webinar',
            'location_type' => 'virtual',
            'location_name' => 'Zoom',
        ]);
    }

    /**
     * Indicate that the event is a conference.
     */
    public function conference(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'conference',
            'location_type' => 'physical',
            'location_name' => 'Convention Center',
        ]);
    }

    /**
     * Indicate that registration is required.
     */
    public function withRegistration(int $maxAttendees = null): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_registration' => true,
            'max_attendees' => $maxAttendees,
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->addMonth(),
        ]);
    }

    /**
     * Indicate that the event is full (max capacity reached).
     */
    public function full(int $maxAttendees = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'max_attendees' => $maxAttendees,
            'requires_registration' => true,
        ]);
    }
}
